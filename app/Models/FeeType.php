<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    public function listingFees(): HasMany
    {
        return $this->hasMany(ListingFee::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
