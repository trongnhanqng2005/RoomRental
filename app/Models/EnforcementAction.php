<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnforcementAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'admin_id',
        'action_type',
        'target_user_id',
        'target_listing_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetListing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'target_listing_id');
    }
}
