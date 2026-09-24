<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViewingSlot extends Model
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    protected $fillable = [
        'listing_id',
        'viewing_date',
        'start_time',
        'end_time',
        'status',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'slot_id');
    }

    public function startAtVietnam(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->viewing_date->format('Y-m-d').' '.$this->start_time,
            self::TIMEZONE,
        );
    }

    public function endAtVietnam(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->viewing_date->format('Y-m-d').' '.$this->end_time,
            self::TIMEZONE,
        );
    }

    protected function casts(): array
    {
        return [
            'viewing_date' => 'date:Y-m-d',
        ];
    }
}
