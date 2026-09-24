<?php

use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Landlord\AppointmentController as LandlordAppointmentController;
use App\Http\Controllers\Landlord\ListingController;
use App\Http\Controllers\Landlord\LocationController;
use App\Http\Controllers\Landlord\ViewingSlotController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ListingController as PublicListingController;
use App\Http\Controllers\Renter\AppointmentController as RenterAppointmentController;
use App\Http\Controllers\Renter\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::get('/rooms', [PublicListingController::class, 'index'])->name('public.listings.index');
Route::get('/rooms/{listing}', [PublicListingController::class, 'show'])
    ->whereNumber('listing')
    ->name('public.listings.show');

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/profile', [ProfileController::class, 'show'])
    ->middleware('auth')
    ->name('profile.show');

Route::patch('/profile', [ProfileController::class, 'update'])
    ->middleware('auth')
    ->name('profile.update');

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', 'role:RENTER'])->group(function () {
    Route::get('/appointments', [RenterAppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/viewing-slots/{viewingSlot}/appointments', [RenterAppointmentController::class, 'store'])
        ->whereNumber('viewingSlot')
        ->name('appointments.store');
    Route::post('/appointments/{appointment}/cancel', [RenterAppointmentController::class, 'cancel'])
        ->whereNumber('appointment')
        ->name('appointments.cancel');

    Route::get('/wishlist', [FavoriteController::class, 'index'])->name('wishlist.index');
    Route::post('/favorites/{listing}', [FavoriteController::class, 'store'])
        ->whereNumber('listing')
        ->name('favorites.store');
    Route::delete('/favorites/{listing}', [FavoriteController::class, 'destroy'])
        ->whereNumber('listing')
        ->name('favorites.destroy');
});

Route::middleware(['auth', 'role:ADMIN,SUPER_ADMIN'])
    ->prefix('admin')
    ->name('admin.')
    ->scopeBindings()
    ->group(function () {
        Route::get('/listing-moderations', [ListingModerationController::class, 'index'])->name('listing-moderations.index');
        Route::get('/listing-moderations/{listing}', [ListingModerationController::class, 'show'])->name('listing-moderations.show');
        Route::post('/listing-moderations/{listing}/moderations/{moderation}/approve', [ListingModerationController::class, 'approve'])->name('listing-moderations.approve');
        Route::post('/listing-moderations/{listing}/moderations/{moderation}/reject', [ListingModerationController::class, 'reject'])->name('listing-moderations.reject');
    });

Route::middleware('auth')->prefix('landlord')->name('landlord.')->group(function () {
    Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
    Route::get('/locations/provinces/{province}/districts', [LocationController::class, 'districts'])->name('locations.districts');
    Route::get('/locations/districts/{district}/wards', [LocationController::class, 'wards'])->name('locations.wards');

    Route::middleware('role:LANDLORD')->group(function () {
        Route::get('/appointments', [LandlordAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments/{appointment}/accept', [LandlordAppointmentController::class, 'accept'])->whereNumber('appointment')->name('appointments.accept');
        Route::post('/appointments/{appointment}/reject', [LandlordAppointmentController::class, 'reject'])->whereNumber('appointment')->name('appointments.reject');
        Route::post('/appointments/{appointment}/complete', [LandlordAppointmentController::class, 'complete'])->whereNumber('appointment')->name('appointments.complete');

        Route::scopeBindings()->group(function () {
            Route::get('/listings/{listing}/viewing-slots', [ViewingSlotController::class, 'index'])->name('viewing-slots.index');
            Route::post('/listings/{listing}/viewing-slots', [ViewingSlotController::class, 'store'])->name('viewing-slots.store');
            Route::patch('/listings/{listing}/viewing-slots/{viewingSlot}/status', [ViewingSlotController::class, 'updateStatus'])
                ->name('viewing-slots.status');
        });

        Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
        Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
        Route::patch('/listings/{listing}/occupancy', [ListingController::class, 'updateOccupancy'])->name('listings.occupancy');
        Route::patch('/listings/{listing}/visibility', [ListingController::class, 'updateVisibility'])->name('listings.visibility');
    });
});
