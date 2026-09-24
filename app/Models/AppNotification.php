<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'notification_type',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'read_at',
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
