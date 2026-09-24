<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    public const ACTIVE_STATUSES = ['PENDING', 'ACCEPTED'];

    protected $fillable = [
        'slot_id',
        'renter_id',
        'status',
        'renter_note',
        'landlord_response',
        'cancelled_by',
        'cancellation_reason',
        'responded_at',
        'completed_at',
        'cancelled_at',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ViewingSlot::class, 'slot_id');
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    protected function casts(): array
    {
        return [
            'cancelled_by' => 'integer',
            'responded_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
