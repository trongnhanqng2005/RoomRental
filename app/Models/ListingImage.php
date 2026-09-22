<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'listing_id',
        'image_url',
        'is_cover',
        'display_order',
        'created_at',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'display_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
