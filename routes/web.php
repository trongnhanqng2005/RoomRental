<?php

use App\Http\Controllers\Admin\AmenityController;
use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RoomCategoryController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TemporaryPasswordController;
use App\Http\Controllers\Landlord\AppointmentController as LandlordAppointmentController;
use App\Http\Controllers\Landlord\ListingController;
use App\Http\Controllers\Landlord\LocationController;
use App\Http\Controllers\Landlord\ViewingSlotController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ListingController as PublicListingController;
use App\Http\Controllers\Renter\AppointmentController as RenterAppointmentController;
use App\Http\Controllers\Renter\FavoriteController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/rooms', [PublicListingController::class, 'index'])->name('public.listings.index');
Route::get('/rooms/{listing}', [PublicListingController::class, 'show'])
    ->whereNumber('listing')
    ->name('public.listings.show');
Route::post('/rooms/{listing}/reports', [ReportController::class, 'store'])
    ->whereNumber('listing')
    ->middleware(['auth', 'throttle:5,1'])
    ->name('reports.store');

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

Route::middleware('auth')->group(function () {
    Route::get('/password/change-required', [TemporaryPasswordController::class, 'create'])->name('password.change.required');
    Route::put('/password/change-required', [TemporaryPasswordController::class, 'update'])->name('password.change.update');
});

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
        Route::patch('/listings/{listing}/unsuspend', [ListingModerationController::class, 'unsuspend'])->whereNumber('listing')->name('listings.unsuspend');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [AdminReportController::class, 'show'])->whereNumber('report')->name('reports.show');
        Route::post('/reports/{report}/dismiss', [AdminReportController::class, 'dismiss'])->whereNumber('report')->name('reports.dismiss');
        Route::post('/reports/{report}/resolve', [AdminReportController::class, 'resolve'])->whereNumber('report')->name('reports.resolve');
        Route::get('/categories', [RoomCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [RoomCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [RoomCategoryController::class, 'edit'])->whereNumber('category')->name('categories.edit');
        Route::put('/categories/{category}', [RoomCategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
        Route::patch('/categories/{category}/hide', [RoomCategoryController::class, 'hide'])->whereNumber('category')->name('categories.hide');
        Route::patch('/categories/{category}/restore', [RoomCategoryController::class, 'restore'])->whereNumber('category')->name('categories.restore');
        Route::get('/amenities', [AmenityController::class, 'index'])->name('amenities.index');
        Route::post('/amenities', [AmenityController::class, 'store'])->name('amenities.store');
        Route::get('/amenities/{amenity}/edit', [AmenityController::class, 'edit'])->whereNumber('amenity')->name('amenities.edit');
        Route::put('/amenities/{amenity}', [AmenityController::class, 'update'])->whereNumber('amenity')->name('amenities.update');
        Route::patch('/amenities/{amenity}/hide', [AmenityController::class, 'hide'])->whereNumber('amenity')->name('amenities.hide');
        Route::patch('/amenities/{amenity}/restore', [AmenityController::class, 'restore'])->whereNumber('amenity')->name('amenities.restore');
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->whereNumber('user')->name('users.show');
        Route::patch('/users/{user}/lock', [UserManagementController::class, 'lock'])->whereNumber('user')->name('users.lock');
        Route::patch('/users/{user}/unlock', [UserManagementController::class, 'unlock'])->whereNumber('user')->name('users.unlock');
        Route::post('/users/{user}/password-reset', [UserManagementController::class, 'resetPassword'])->whereNumber('user')->name('users.password-reset');
        Route::post('/users/{user}/promote', [UserManagementController::class, 'promote'])->whereNumber('user')->name('users.promote');
        Route::delete('/users/{user}/admin-role', [UserManagementController::class, 'revoke'])->whereNumber('user')->name('users.revoke');
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
        Route::post('/listings/{listing}/renew', [ListingController::class, 'renew'])->name('listings.renew');
        Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
    });
});
