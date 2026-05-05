<?php

namespace App\Support;

use App\Models\UiPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CallCenterUiPreferenceStore
{
    public function get(Request $request, string $scope): array
    {
        $owner = $this->resolveOwner($request);
        if ($owner === null) {
            return [];
        }

        $query = UiPreference::query()->where('scope', $scope);

        if ($owner['user_id'] !== null) {
            $query->where('user_id', $owner['user_id']);
        } else {
            $query->whereNull('user_id')->where('profile_key', $owner['profile_key']);
        }

        $record = $query->first();

        return is_array($record?->preferences) ? $record->preferences : [];
    }

    public function put(Request $request, string $scope, array $preferences): array
    {
        $owner = $this->resolveOwner($request);
        if ($owner === null) {
            return [];
        }

        $attributes = ['scope' => $scope];

        if ($owner['user_id'] !== null) {
            $attributes['user_id'] = $owner['user_id'];
        } else {
            $attributes['user_id'] = null;
            $attributes['profile_key'] = $owner['profile_key'];
        }

        $record = UiPreference::query()->updateOrCreate($attributes, [
            'preferences' => $preferences,
        ]);

        return is_array($record->preferences) ? $record->preferences : [];
    }

    /**
     * @return array{user_id:int|null,profile_key:string|null}|null
     */
    private function resolveOwner(Request $request): ?array
    {
        if (! Schema::hasTable('ui_preferences')) {
            return null;
        }

        $userId = $request->user()?->id;
        if (is_int($userId) && $userId > 0) {
            return [
                'user_id' => $userId,
                'profile_key' => null,
            ];
        }

        $profileKey = trim((string) ($request->header('X-UI-Profile-Key') ?: $request->input('profile_key', '')));
        if ($profileKey === '') {
            return null;
        }

        return [
            'user_id' => null,
            'profile_key' => mb_substr($profileKey, 0, 64),
        ];
    }
}
