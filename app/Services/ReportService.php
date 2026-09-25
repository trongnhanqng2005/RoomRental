<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\EnforcementAction;
use App\Models\Listing;
use App\Models\Report;
use App\Models\ReportReason;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ReportService
{
    public function __construct(
        private AppointmentService $appointmentService,
        private AccountLockService $accountLockService,
    ) {}

    public function submit(User $reporter, int $listingId, int $reasonId, ?string $description): Report
    {
        try {
            return DB::transaction(function () use ($reporter, $listingId, $reasonId, $description): Report {
                $listing = Listing::query()
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->findOrFail($listingId);

                if ((int) $listing->landlord_id === (int) $reporter->id) {
                    throw new AuthorizationException;
                }

                $reason = ReportReason::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->find($reasonId);

                if ($reason === null) {
                    throw ValidationException::withMessages([
                        'reason_id' => __('validation.exists', ['attribute' => __('validation.attributes.reason_id')]),
                    ]);
                }

                $alreadyPending = Report::query()
                    ->where('reporter_id', $reporter->id)
                    ->where('listing_id', $listing->id)
                    ->where('status', 'PENDING')
                    ->exists();

                if ($alreadyPending) {
                    throw $this->duplicatePendingException();
                }

                return Report::query()->create([
                    'reporter_id' => $reporter->id,
                    'listing_id' => $listing->id,
                    'reason_id' => $reason->id,
                    'description' => $description,
                    'status' => 'PENDING',
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (
                (int) ($exception->errorInfo[1] ?? 0) === 1062
                && Str::contains($exception->getMessage(), 'reports_one_pending_per_reporter_listing_unique')
            ) {
                throw $this->duplicatePendingException();
            }

            throw $exception;
        }
    }

    public function dismiss(Report $requestedReport, User $admin, string $resolutionReason): void
    {
        $this->authorizeAdmin($admin);

        $notification = DB::transaction(function () use ($requestedReport, $admin, $resolutionReason): array {
            $report = Report::query()->lockForUpdate()->findOrFail($requestedReport->id);
            $this->requirePending($report);

            $handledAt = now();
            $updated = Report::query()
                ->whereKey($report->id)
                ->where('status', 'PENDING')
                ->update([
                    'status' => 'DISMISSED',
                    'handled_by' => $admin->id,
                    'handled_at' => $handledAt,
                    'resolution_reason' => $resolutionReason,
                    'updated_at' => $handledAt,
                ]);

            if ($updated !== 1) {
                $this->throwStaleAction();
            }

            AuditLog::query()->create([
                'actor_user_id' => $admin->id,
                'action' => 'report.dismissed',
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'created_at' => $handledAt,
            ]);

            return $this->notificationContext($report, 'DISMISSED', null, null, []);
        }, 3);

        $this->notifyHandledReport($notification);
    }

    public function resolve(Report $requestedReport, User $admin, string $actionType, string $reason): void
    {
        $this->authorizeAdmin($admin);

        if (! in_array($actionType, ['WARNING', 'SUSPEND_LISTING', 'LOCK_ACCOUNT'], true)) {
            throw ValidationException::withMessages([
                'action_type' => __('validation.in', ['attribute' => __('ui.reports.enforcement_action')]),
            ]);
        }

        $notification = DB::transaction(function () use ($requestedReport, $admin, $actionType, $reason): array {
            $report = Report::query()->lockForUpdate()->findOrFail($requestedReport->id);
            $this->requirePending($report);

            $landlordId = Listing::query()->whereKey($report->listing_id)->value('landlord_id');

            if ($landlordId === null) {
                abort(404);
            }

            $landlord = User::query()->lockForUpdate()->findOrFail($landlordId);
            $appointmentNotifications = [];

            if ($actionType === 'LOCK_ACCOUNT') {
                if ($landlord->hasAnyRole('ADMIN', 'SUPER_ADMIN')) {
                    throw ValidationException::withMessages([
                        'action_type' => __('ui.reports.protected_admin_target'),
                    ]);
                }

                $listings = Listing::query()
                    ->where('landlord_id', $landlord->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $listing = $listings->firstWhere('id', (int) $report->listing_id);

                if (! $listing) {
                    $this->throwStaleAction();
                }

                $lock = $this->accountLockService->lock($landlord, $admin, cancelAppointmentsWhenAlreadyLocked: true);
                $appointmentNotifications = $lock['appointment_notifications'];
            } else {
                $listing = Listing::query()->lockForUpdate()->findOrFail($report->listing_id);

                if ((int) $listing->landlord_id !== (int) $landlord->id) {
                    $this->throwStaleAction();
                }

                if ($actionType === 'SUSPEND_LISTING') {
                    $listing->forceFill(['visibility_status' => 'SUSPENDED'])->save();
                    $appointmentNotifications = $this->appointmentService->autoCancelFutureForAdminAction(
                        new Collection([$listing]),
                        $admin,
                        'LISTING_SUSPENDED',
                    );
                }
            }

            $handledAt = now();
            EnforcementAction::query()->create([
                'report_id' => $report->id,
                'admin_id' => $admin->id,
                'action_type' => $actionType,
                'target_user_id' => in_array($actionType, ['WARNING', 'LOCK_ACCOUNT'], true) ? $landlord->id : null,
                'target_listing_id' => $actionType === 'SUSPEND_LISTING' ? $listing->id : null,
                'reason' => $reason,
                'created_at' => $handledAt,
            ]);

            $updated = Report::query()
                ->whereKey($report->id)
                ->where('status', 'PENDING')
                ->update([
                    'status' => 'RESOLVED',
                    'handled_by' => $admin->id,
                    'handled_at' => $handledAt,
                    'updated_at' => $handledAt,
                ]);

            if ($updated !== 1) {
                $this->throwStaleAction();
            }

            AuditLog::query()->create([
                'actor_user_id' => $admin->id,
                'action' => match ($actionType) {
                    'WARNING' => 'report.resolved.warning',
                    'SUSPEND_LISTING' => 'report.resolved.suspend_listing',
                    'LOCK_ACCOUNT' => 'report.resolved.lock_account',
                },
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'created_at' => $handledAt,
            ]);

            return $this->notificationContext(
                $report,
                $actionType,
                $landlord,
                $listing,
                $appointmentNotifications,
                $reason,
            );
        }, 3);

        $this->notifyHandledReport($notification);
        $this->notifyEnforcementTarget($notification);
        $this->appointmentService->notifyAutoCancelledAppointments($notification['appointment_notifications']);
    }

    private function authorizeAdmin(User $admin): void
    {
        if (! $admin->hasAnyRole('ADMIN', 'SUPER_ADMIN')) {
            throw new AuthorizationException;
        }
    }

    private function requirePending(Report $report): void
    {
        if ($report->status !== 'PENDING') {
            $this->throwStaleAction();
        }
    }

    private function throwStaleAction(): never
    {
        throw new ConflictHttpException(__('ui.reports.stale_action'));
    }

    /**
     * @param  array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>  $appointmentNotifications
     * @return array{report_id: int, reporter_id: int, listing_id: int, listing_title: string, action_type: string, landlord_id: ?int, reason: ?string, appointment_notifications: array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>}
     */
    private function notificationContext(
        Report $report,
        string $actionType,
        ?User $landlord,
        ?Listing $listing,
        array $appointmentNotifications,
        ?string $reason = null,
    ): array {
        $listing ??= Listing::query()->findOrFail($report->listing_id);

        return [
            'report_id' => (int) $report->id,
            'reporter_id' => (int) $report->reporter_id,
            'listing_id' => (int) $listing->id,
            'listing_title' => (string) $listing->title,
            'action_type' => $actionType,
            'landlord_id' => $landlord?->id,
            'reason' => $reason,
            'appointment_notifications' => $appointmentNotifications,
        ];
    }

    /** @param array<string, mixed> $context */
    private function notifyHandledReport(array $context): void
    {
        $this->createNotification(
            (int) $context['reporter_id'],
            'REPORT_HANDLED',
            __('ui.notifications.report_handled_title'),
            __('ui.notifications.report_handled_message', ['listing' => $context['listing_title']]),
            'report',
            (int) $context['report_id'],
            (int) $context['report_id'],
        );
    }

    /** @param array<string, mixed> $context */
    private function notifyEnforcementTarget(array $context): void
    {
        if ($context['landlord_id'] === null) {
            return;
        }

        [$type, $title, $message, $entityType, $entityId] = match ($context['action_type']) {
            'WARNING' => [
                'LANDLORD_WARNING',
                __('ui.notifications.landlord_warning_title'),
                __('ui.notifications.landlord_warning_message', [
                    'listing' => $context['listing_title'],
                    'reason' => $context['reason'],
                ]),
                'report',
                (int) $context['report_id'],
            ],
            'SUSPEND_LISTING' => [
                'LISTING_SUSPENDED',
                __('ui.notifications.listing_suspended_title'),
                __('ui.notifications.listing_suspended_message', ['listing' => $context['listing_title']]),
                'listing',
                (int) $context['listing_id'],
            ],
            'LOCK_ACCOUNT' => [
                'ACCOUNT_LOCKED',
                __('ui.notifications.account_locked_title'),
                __('ui.notifications.account_locked_message'),
                'report',
                (int) $context['report_id'],
            ],
            default => [null, null, null, null, null],
        };

        if ($type !== null) {
            $this->createNotification(
                (int) $context['landlord_id'],
                $type,
                $title,
                $message,
                $entityType,
                $entityId,
                (int) $context['report_id'],
            );
        }
    }

    private function createNotification(
        int $recipientId,
        string $type,
        string $title,
        string $message,
        string $entityType,
        int $entityId,
        int $reportId,
    ): void {
        try {
            $notification = AppNotification::query()->make([
                'user_id' => $recipientId,
                'notification_type' => $type,
                'title' => Str::limit($title, 255, ''),
                'message' => Str::limit($message, 1000, ''),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'read_at' => null,
                'created_at' => now(),
            ]);

            if (! $notification->save()) {
                throw new RuntimeException('The report notification was not persisted.');
            }
        } catch (Throwable) {
            try {
                Log::warning('Report notification could not be created.', [
                    'report_id' => $reportId,
                    'notification_type' => $type,
                ]);
            } catch (Throwable) {
                // A committed report decision must remain successful.
            }
        }
    }

    private function duplicatePendingException(): ValidationException
    {
        return ValidationException::withMessages([
            'report' => __('ui.reports.duplicate_pending'),
        ]);
    }
}
