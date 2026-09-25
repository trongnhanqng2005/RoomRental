<?php

namespace Tests\Feature\Listing;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\EnforcementAction;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingLifecycleService;
use App\Services\ListingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;
use Tests\Feature\Report\ReportFeatureTestCase;

class ListingLifecycleTest extends ReportFeatureTestCase
{
    public function test_owner_can_renew_an_expired_approved_hidden_listing_for_thirty_days(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $expiryNow = CarbonImmutable::now('UTC')->startOfSecond();
        $this->travelTo($expiryNow);
        $listing = $this->listing($landlord, overrides: [
            'expires_at' => $expiryNow->subSecond(),
            'visibility_status' => 'HIDDEN',
        ]);

        app(ListingLifecycleService::class)->renew($listing, $landlord);

        $listing->refresh();
        $this->assertSame($expiryNow->addDays(30)->toDateTimeString(), $listing->expires_at->toDateTimeString());
        $this->assertSame('APPROVED', $listing->currentModeration->status);
        $this->assertSame('HIDDEN', $listing->visibility_status);
        $this->assertSame('AVAILABLE', $listing->occupancy_status);
        $this->assertSame(1, $listing->moderations()->count());
        $this->assertSame(0, AppNotification::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_renewal_can_repeat_only_after_the_new_expiry_is_reached(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, overrides: ['expires_at' => now()]);

        $this->actingAs($landlord)->post(route('landlord.listings.renew', $listing))->assertRedirect();
        $firstRenewalExpiry = $listing->fresh()->expires_at;

        $this->post(route('landlord.listings.renew', $listing))->assertConflict();
        $this->travelTo($firstRenewalExpiry);
        $this->post(route('landlord.listings.renew', $listing))->assertRedirect();
    }

    public function test_concurrent_renewals_allow_only_one_state_change(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, overrides: ['expires_at' => now()->subSecond()]);

        DB::commit();
        $outcomes = $this->runConcurrentRenewalsBehindListingLock($listing, $landlord);
        sort($outcomes);

        $this->assertSame(['STALE', 'SUCCESS'], $outcomes);
        $this->assertGreaterThan(now(), $listing->fresh()->expires_at);

        DB::table('listings')->where('id', $listing->id)->update(['current_moderation_id' => null]);
        DB::table('listing_moderations')->where('listing_id', $listing->id)->delete();
        DB::table('listings')->where('id', $listing->id)->delete();
        DB::table('user_roles')->where('user_id', $landlord->id)->delete();
        DB::table('user_profiles')->where('user_id', $landlord->id)->delete();
        DB::table('users')->where('id', $landlord->id)->delete();
        $districtId = $this->ward->district_id;
        $provinceId = DB::table('districts')->where('id', $districtId)->value('province_id');
        DB::table('wards')->where('id', $this->ward->id)->delete();
        DB::table('districts')->where('id', $districtId)->delete();
        DB::table('provinces')->where('id', $provinceId)->delete();
        DB::table('room_categories')->where('id', $this->category->id)->delete();
    }

    public function test_renewal_rejects_ineligible_states_and_non_owners(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $otherLandlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, overrides: ['expires_at' => now()->subSecond()]);

        $this->actingAs($otherLandlord)
            ->post(route('landlord.listings.renew', $listing))
            ->assertForbidden();

        foreach ([
            ['expires_at' => now()->addDay()],
            ['occupancy_status' => 'RENTED'],
            ['visibility_status' => 'SUSPENDED'],
            ['deleted_at' => now()],
            ['moderation_status' => 'PENDING'],
            ['moderation_status' => 'REJECTED'],
        ] as $overrides) {
            $candidate = $this->listing($landlord, overrides: array_merge(['expires_at' => now()->subSecond()], $overrides));

            $this->actingAs($landlord)
                ->post(route('landlord.listings.renew', $candidate))
                ->assertConflict();
        }
    }

    public function test_locked_landlord_cannot_renew(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, overrides: ['expires_at' => now()->subSecond()]);
        $landlord->account_status = 'LOCKED';
        $landlord->save();

        $this->expectException(ConflictHttpException::class);
        app(ListingLifecycleService::class)->renew($listing, $landlord->fresh());
    }

    public function test_delete_logically_removes_listing_and_cancels_only_future_active_appointments(): void
    {
        Storage::fake('public');
        $landlord = $this->userWithRoles(['LANDLORD']);
        $renter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($landlord);
        $futurePending = $this->appointment($listing, $renter, 3, 'PENDING');
        $futureAccepted = $this->appointment($listing, $renter, 5, 'ACCEPTED');
        $started = $this->appointment($listing, $renter, -1, 'PENDING');
        $terminal = $this->appointment($listing, $renter, 8, 'COMPLETED');
        $image = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'image_url' => 'listings/'.$listing->id.'/retained.jpg',
            'is_cover' => true,
            'display_order' => 0,
            'created_at' => now(),
        ]);
        Storage::disk('public')->put($image->image_url, 'retained image contents');
        $report = $this->report($renter, $listing);
        EnforcementAction::query()->create([
            'report_id' => $report->id,
            'admin_id' => $this->userWithRoles(['ADMIN'])->id,
            'action_type' => 'SUSPEND_LISTING',
            'target_listing_id' => $listing->id,
            'reason' => 'Review history',
            'created_at' => now(),
        ]);

        $this->actingAs($landlord)
            ->delete(route('landlord.listings.destroy', $listing))
            ->assertRedirect(route('landlord.listings.index'));

        $this->assertNotNull($listing->fresh()->deleted_at);
        $this->assertSame('AUTO_CANCELLED', $futurePending->fresh()->status);
        $this->assertSame('AUTO_CANCELLED', $futureAccepted->fresh()->status);
        $this->assertSame('LISTING_DELETED', $futurePending->fresh()->cancellation_reason);
        $this->assertSame($landlord->id, $futurePending->fresh()->cancelled_by);
        $this->assertSame('PENDING', $started->fresh()->status);
        $this->assertSame('COMPLETED', $terminal->fresh()->status);
        $this->assertDatabaseHas('listing_images', ['id' => $image->id, 'listing_id' => $listing->id]);
        Storage::disk('public')->assertExists($image->image_url);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'listing_id' => $listing->id]);
        $this->assertDatabaseHas('enforcement_actions', ['report_id' => $report->id, 'target_listing_id' => $listing->id]);
        $this->assertSame(2, AppNotification::query()->where('notification_type', 'APPOINTMENT_AUTO_CANCELLED')->count());
    }

    public function test_deleted_listing_is_denied_publicly_and_delete_is_stale_when_repeated(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);

        $this->actingAs($landlord)->delete(route('landlord.listings.destroy', $listing))->assertRedirect();
        $this->actingAs($landlord)->delete(route('landlord.listings.destroy', $listing))->assertConflict();
        $this->get(route('public.listings.show', $listing))->assertNotFound();
        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_admin_unsuspend_restores_hidden_and_records_audit_without_changing_other_axes(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, overrides: [
            'visibility_status' => 'SUSPENDED',
            'occupancy_status' => 'RENTED',
            'expires_at' => now()->subDay(),
        ]);
        $expiry = $listing->expires_at->toDateTimeString();

        $this->actingAs($admin)
            ->patch(route('admin.listings.unsuspend', $listing))
            ->assertRedirect();

        $listing->refresh();
        $this->assertSame('HIDDEN', $listing->visibility_status);
        $this->assertSame('RENTED', $listing->occupancy_status);
        $this->assertSame('APPROVED', $listing->currentModeration->status);
        $this->assertSame($expiry, $listing->expires_at->toDateTimeString());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'listing.unsuspended',
            'entity_type' => Listing::class,
            'entity_id' => $listing->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.listings.unsuspend', $listing))->assertConflict();
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_super_admin_can_unsuspend_but_audit_failure_rolls_back_visibility(): void
    {
        $superAdmin = $this->userWithRoles(['SUPER_ADMIN']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, overrides: ['visibility_status' => 'SUSPENDED']);

        $this->actingAs($superAdmin)
            ->patch(route('admin.listings.unsuspend', $listing))
            ->assertRedirect();
        $this->assertSame('HIDDEN', $listing->fresh()->visibility_status);

        $second = $this->listing($landlord, 'Tin thứ hai bị tạm ngưng', ['visibility_status' => 'SUSPENDED']);
        Event::listen('eloquent.creating: '.AuditLog::class, function (): void {
            throw new \RuntimeException('Simulated audit failure.');
        });

        try {
            try {
                app(ListingLifecycleService::class)->unsuspend($second, $superAdmin);
                $this->fail('The simulated audit failure should be raised.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated audit failure.', $exception->getMessage());
            }
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertSame('SUSPENDED', $second->fresh()->visibility_status);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_non_admin_cannot_unsuspend(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, overrides: ['visibility_status' => 'SUSPENDED']);
        $image = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'image_url' => 'listings/'.$listing->id.'/kept.jpg',
            'is_cover' => true,
            'display_order' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($landlord)->patch(route('admin.listings.unsuspend', $listing))->assertForbidden();
        $this->assertDatabaseHas('listing_images', ['id' => $image->id]);
    }

    public function test_notification_failure_does_not_rollback_delete_or_prevent_other_recipients(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $renterOne = $this->userWithRoles(['RENTER']);
        $renterTwo = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($landlord);
        $this->appointment($listing, $renterOne, 3, 'PENDING');
        $this->appointment($listing, $renterTwo, 4, 'PENDING');
        $attempts = 0;
        Event::listen('eloquent.creating: '.AppNotification::class, function () use (&$attempts): void {
            $attempts++;

            if ($attempts === 1) {
                throw new \RuntimeException('Simulated notification failure.');
            }
        });

        try {
            app(ListingLifecycleService::class)->delete($listing, $landlord);
        } finally {
            Event::forget('eloquent.creating: '.AppNotification::class);
        }

        $this->assertNotNull($listing->fresh()->deleted_at);
        $this->assertSame(2, $attempts);
        $this->assertSame(1, AppNotification::query()->count());
    }

    public function test_failed_delete_transaction_keeps_appointments_and_sends_no_notifications(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $renter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($landlord);
        $appointment = $this->appointment($listing, $renter, 3, 'ACCEPTED');
        Event::listen('eloquent.saving: '.Listing::class, function (Listing $model): void {
            if ($model->exists && $model->isDirty('deleted_at')) {
                throw new \RuntimeException('Simulated listing deletion failure.');
            }
        });

        try {
            try {
                app(ListingLifecycleService::class)->delete($listing, $landlord);
                $this->fail('The simulated deletion failure should be raised.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated listing deletion failure.', $exception->getMessage());
            }
        } finally {
            Event::forget('eloquent.saving: '.Listing::class);
        }

        $this->assertNull($listing->fresh()->deleted_at);
        $this->assertSame('ACCEPTED', $appointment->fresh()->status);
        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_duplicate_address_warning_normalizes_case_and_whitespace_but_preserves_punctuation_and_units(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $first = $this->listing($landlord, overrides: ['street_address' => '  12   Nguyễn Trãi / Phòng 2 ']);
        $matching = $this->listing($landlord, overrides: ['street_address' => '12 nguyễn trãi / phòng 2']);
        $unicodeWhitespace = $this->listing($landlord, overrides: ['street_address' => "\u{00A0}12\u{00A0}\u{00A0}nguyễn trãi / phòng 2\u{00A0}"]);
        $differentUnit = $this->listing($landlord, overrides: ['street_address' => '12 nguyễn trãi / phòng 3']);

        $service = app(ListingService::class);
        $this->assertSame($first->id, $service->findDuplicateAddress($matching)?->id);
        $this->assertSame($first->id, $service->findDuplicateAddress($unicodeWhitespace)?->id);
        $this->assertNull($service->findDuplicateAddress($differentUnit));

        $first->deleted_at = now();
        $first->save();
        $unicodeWhitespace->deleted_at = now();
        $unicodeWhitespace->save();
        $this->assertNull($service->findDuplicateAddress($matching));
    }

    /** @return array<int, string> */
    private function runConcurrentRenewalsBehindListingLock(Listing $listing, User $landlord): array
    {
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[4], 'ready');

try {
    app(App\Services\ListingLifecycleService::class)->renew(
        App\Models\Listing::query()->findOrFail((int) $argv[2]),
        App\Models\User::query()->findOrFail((int) $argv[3]),
    );
    echo 'SUCCESS';
} catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo 'STALE';
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
PHP;
        $processes = [];
        $readyPaths = [];
        $lockHeld = false;

        try {
            DB::beginTransaction();
            $lockHeld = true;
            DB::table('listings')->where('id', $listing->id)->lockForUpdate()->first();

            for ($index = 0; $index < 2; $index++) {
                $readyPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'roomrental-renew-race-'.bin2hex(random_bytes(12));
                $readyPaths[] = $readyPath;
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $script,
                    base_path(),
                    (string) $listing->id,
                    (string) $landlord->id,
                    $readyPath,
                ], base_path());
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $readyDeadline = microtime(true) + 30;
            while (count(array_filter($readyPaths, 'is_file')) < count($readyPaths) && microtime(true) < $readyDeadline) {
                usleep(10_000);
            }

            $this->assertCount(count($readyPaths), array_filter($readyPaths, 'is_file'), 'Both renewal workers must reach the listing lock.');
            usleep(100_000);
            DB::commit();
            $lockHeld = false;

            $outcomes = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $outcomes[] = $process->getOutput();
            }

            return $outcomes;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            foreach ($readyPaths as $readyPath) {
                if (is_file($readyPath)) {
                    unlink($readyPath);
                }
            }

            if ($lockHeld && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
