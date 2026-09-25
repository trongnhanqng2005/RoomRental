<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'reporter_id',
        'listing_id',
        'reason_id',
        'description',
        'status',
        'handled_by',
        'resolution_reason',
        'handled_at',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReportReason::class, 'reason_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function enforcementActions(): HasMany
    {
        return $this->hasMany(EnforcementAction::class)->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'handled_by' => 'integer',
            'handled_at' => 'datetime',
        ];
    }
}
