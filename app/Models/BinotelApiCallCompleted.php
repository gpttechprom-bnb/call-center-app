<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BinotelApiCallCompleted extends Model
{
    protected $table = 'binotel_api_call_completeds';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts_counter' => 'integer',
            'call_details_start_time' => 'integer',
            'call_details_waitsec' => 'integer',
            'call_details_billsec' => 'integer',
            'call_details_is_new_call' => 'boolean',
            'interaction_number' => 'integer',
            'call_details_customer_data' => 'array',
            'call_details_call_tracking_first_visit_at' => 'integer',
            'call_details_call_tracking_time_spent_on_site_before_make_call' => 'integer',
            'call_record_url_check_attempts' => 'integer',
            'call_record_url_last_checked_at' => 'datetime',
            'local_audio_size_bytes' => 'integer',
            'local_audio_downloaded_at' => 'datetime',
            'local_audio_expires_at' => 'datetime',
            'alt_auto_started_at' => 'datetime',
            'alt_auto_finished_at' => 'datetime',
            'crm_phone_exists' => 'boolean',
            'crm_missing' => 'boolean',
            'crm_checked_at' => 'datetime',
        ];
    }

    public function historyItems(): HasMany
    {
        return $this->hasMany(BinotelApiCallCompletedHistory::class, 'binotel_api_call_completed_id')
            ->orderBy('sort_order');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(BinotelCallFeedback::class, 'binotel_api_call_completed_id');
    }
}
