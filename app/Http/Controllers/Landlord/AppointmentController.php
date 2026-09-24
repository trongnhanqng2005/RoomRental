<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\AcceptAppointmentRequest;
use App\Http\Requests\Appointment\RejectAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request, AppointmentService $appointmentService): View
    {
        Gate::authorize('viewAnyLandlord', Appointment::class);

        $appointments = Appointment::query()
            ->whereHas('slot.listing', fn ($query) => $query->where('landlord_id', $request->user()->id))
            ->with([
                'slot.listing.coverImage',
                'slot.listing.category:id,name',
                'renter' => fn ($query) => $query->select(['id', 'email', 'phone'])
                    ->with('profile:user_id,full_name,zalo_number'),
            ])
            ->latest('created_at')
            ->paginate(10);

        $appointments->getCollection()->each(function (Appointment $appointment) use ($appointmentService): void {
            $appointment->setAttribute('can_complete', $appointmentService->canComplete($appointment));
            $appointment->setAttribute('cancellation_reason_label', $this->cancellationReasonLabel($appointment->cancellation_reason));
        });

        return view('landlord.appointments.index', compact('appointments'));
    }

    public function accept(AcceptAppointmentRequest $request, Appointment $appointment, AppointmentService $appointmentService): RedirectResponse
    {
        $appointmentService->accept($appointment, $request->user(), $request->validated('landlord_response'));

        return redirect()->route('landlord.appointments.index')->with('status', __('ui.appointments.accepted'));
    }

    public function reject(RejectAppointmentRequest $request, Appointment $appointment, AppointmentService $appointmentService): RedirectResponse
    {
        $appointmentService->reject($appointment, $request->user(), $request->validated('landlord_response'));

        return redirect()->route('landlord.appointments.index')->with('status', __('ui.appointments.rejected'));
    }

    public function complete(Request $request, Appointment $appointment, AppointmentService $appointmentService): RedirectResponse
    {
        Gate::authorize('complete', $appointment);
        $appointmentService->complete($appointment, $request->user());

        return redirect()->route('landlord.appointments.index')->with('status', __('ui.appointments.completed'));
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
