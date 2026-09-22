<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'district_id',
        'code',
        'name',
        'is_active',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
