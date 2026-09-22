<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingFee extends Model
{
    protected $fillable = [
        'listing_id',
        'fee_type_id',
        'fee_unit_id',
        'amount',
        'note',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function feeUnit(): BelongsTo
    {
        return $this->belongsTo(FeeUnit::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
