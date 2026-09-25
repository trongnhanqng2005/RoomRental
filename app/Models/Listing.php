<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Listing extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'monthly_rent',
        'deposit_amount',
        'area_m2',
        'max_occupants',
        'bedroom_count',
        'bathroom_count',
        'gender_requirement',
        'ward_id',
        'street_address',
        'latitude',
        'longitude',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'category_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('display_order');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ListingImage::class)->where('is_cover', true);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'listing_amenities');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(ListingFee::class);
    }

    public function moderations(): HasMany
    {
        return $this->hasMany(ListingModeration::class)->orderByDesc('version_no');
    }

    public function currentModeration(): BelongsTo
    {
        return $this->belongsTo(ListingModeration::class, 'current_moderation_id');
    }

    public function viewingSlots(): HasMany
    {
        return $this->hasMany(ViewingSlot::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function enforcementActions(): HasMany
    {
        return $this->hasMany(EnforcementAction::class, 'target_listing_id');
    }

    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'area_m2' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'max_occupants' => 'integer',
            'bedroom_count' => 'integer',
            'bathroom_count' => 'integer',
            'current_moderation_id' => 'integer',
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }
}
