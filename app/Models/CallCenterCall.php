<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallCenterCall extends Model
{
    protected $fillable = [
        'direction',
        'caller',
        'caller_meta',
        'employee',
        'employee_meta',
        'duration_seconds',
        'started_at',
        'transcript_status',
        'audio_status',
        'score',
        'summary',
        'transcript',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'score' => 'integer',
        ];
    }

    public function scoreItems(): HasMany
    {
        return $this->hasMany(CallCenterCallScoreItem::class)->orderBy('sort_order');
    }

    public function getFormattedDurationAttribute(): string
    {
        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
    }
}
