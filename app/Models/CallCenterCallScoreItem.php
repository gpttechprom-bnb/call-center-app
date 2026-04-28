<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallCenterCallScoreItem extends Model
{
    protected $fillable = [
        'call_center_call_id',
        'title',
        'score',
        'text',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(CallCenterCall::class, 'call_center_call_id');
    }
}
