<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\BookAppointmentRequest;
use App\Http\Requests\Appointment\CancelAppointmentRequest;
use App\Models\Appointment;
use App\Models\ViewingSlot;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request, AppointmentService $appointmentService): View
    {
        Gate::authorize('viewAnyRenter', Appointment::class);

        $appointments = $request->user()->appointments()
            ->with([
                'slot.listing.coverImage',
                'slot.listing.category:id,name',
                'slot.listing.landlord' => fn ($query) => $query->select(['id', 'phone']),
                'slot.listing.landlord.profile:user_id,full_name,avatar_url,zalo_number',
            ])
            ->latest('created_at')
            ->paginate(10);

        $appointments->getCollection()->each(function (Appointment $appointment) use ($appointmentService): void {
            $appointment->setAttribute('can_cancel', $appointmentService->canCancel($appointment));
            $appointment->setAttribute('cancellation_reason_label', $this->cancellationReasonLabel($appointment->cancellation_reason));
        });

        return view('renter.appointments.index', compact('appointments'));
    }

    public function store(BookAppointmentRequest $request, ViewingSlot $viewingSlot, AppointmentService $appointmentService): RedirectResponse
    {
        $appointmentService->book($viewingSlot, $request->user(), $request->validated('renter_note'));

        return redirect()->route('appointments.index')->with('status', __('ui.appointments.booked'));
    }

    public function cancel(
        CancelAppointmentRequest $request,
        Appointment $appointment,
        AppointmentService $appointmentService,
    ): RedirectResponse {
        $appointmentService->cancel($appointment, $request->user(), $request->validated('cancellation_reason'));

        return redirect()->route('appointments.index')->with('status', __('ui.appointments.cancelled'));
    }

    private function cancellationReasonLabel(?string $reason): ?string
    {
        return match ($reason) {
            'VIEWING_TIME_PASSED' => __('ui.appointments.cancellation_reasons.overdue'),
            'LISTING_RENTED' => __('ui.appointments.cancellation_reasons.rented'),
            null, '' => null,
            default => $reason,
        };
    }
}
