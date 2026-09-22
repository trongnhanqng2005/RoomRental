<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
