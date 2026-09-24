<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreViewingSlotRequest;
use App\Http\Requests\Appointment\UpdateViewingSlotStatusRequest;
use App\Models\Appointment;
use App\Models\Listing;
use App\Models\ViewingSlot;
use App\Services\ViewingSlotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ViewingSlotController extends Controller
{
    public function index(Request $request, Listing $listing): View
    {
        Gate::authorize('manageViewingSlots', $listing);
        $listing->load('coverImage');

        $slots = $listing->viewingSlots()
            ->with(['appointments' => function ($query): void {
                $query->whereIn('status', Appointment::ACTIVE_STATUSES)
                    ->with(['renter' => fn ($renter) => $renter
                        ->select(['id', 'email', 'phone'])
                        ->with('profile:user_id,full_name,zalo_number')]);
            }])
            ->orderBy('viewing_date')
            ->orderBy('start_time')
            ->paginate(10);

        $slots->getCollection()->each(function (ViewingSlot $slot): void {
            $hasActiveAppointment = $slot->appointments->isNotEmpty();
            $canOpen = $slot->status === 'CLOSED'
                && ! $hasActiveAppointment
                && $slot->startAtVietnam()->greaterThan(CarbonImmutable::now(ViewingSlot::TIMEZONE));
            $slot->setAttribute('can_change_status', $slot->status === 'OPEN' ? ! $hasActiveAppointment : $canOpen);
        });

        return view('landlord.viewing-slots.index', compact('listing', 'slots'));
    }

    public function store(StoreViewingSlotRequest $request, Listing $listing, ViewingSlotService $viewingSlotService): RedirectResponse
    {
        $viewingSlotService->create($listing, $request->user(), $request->validated());

        return redirect()->route('landlord.viewing-slots.index', $listing)->with('status', __('ui.appointments.slot_created'));
    }

    public function updateStatus(
        UpdateViewingSlotStatusRequest $request,
        Listing $listing,
        ViewingSlot $viewingSlot,
        ViewingSlotService $viewingSlotService,
    ): RedirectResponse {
        $viewingSlotService->changeStatus($listing, $viewingSlot, $request->user(), $request->validated('status'));

        return redirect()->route('landlord.viewing-slots.index', $listing)->with('status', __('ui.appointments.slot_status_updated'));
    }
}
