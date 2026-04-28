<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinotelCallFeedback extends Model
{
    protected $table = 'binotel_call_feedbacks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transcription_payload' => 'array',
            'evaluation_payload' => 'array',
            'comparison_runs' => 'array',
            'transcribed_at' => 'datetime',
            'evaluation_requested_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(BinotelApiCallCompleted::class, 'binotel_api_call_completed_id');
    }
}
