<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinotelApiCallCompletedHistory extends Model
{
    protected $table = 'binotel_api_call_completed_histories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'waitsec' => 'integer',
            'billsec' => 'integer',
        ];
    }

    public function apiCallCompleted(): BelongsTo
    {
        return $this->belongsTo(BinotelApiCallCompleted::class, 'binotel_api_call_completed_id');
    }
}
