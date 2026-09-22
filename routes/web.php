<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Landlord\ListingController;
use App\Http\Controllers\Landlord\LocationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth')->prefix('landlord')->name('landlord.')->group(function () {
    Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
    Route::get('/locations/provinces/{province}/districts', [LocationController::class, 'districts'])->name('locations.districts');
    Route::get('/locations/districts/{district}/wards', [LocationController::class, 'wards'])->name('locations.wards');

    Route::middleware('role:LANDLORD')->group(function () {
        Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
        Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
        Route::patch('/listings/{listing}/occupancy', [ListingController::class, 'updateOccupancy'])->name('listings.occupancy');
        Route::patch('/listings/{listing}/visibility', [ListingController::class, 'updateVisibility'])->name('listings.visibility');
    });
});
