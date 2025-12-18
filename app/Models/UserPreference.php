<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a user preference value.
     */
    public static function get(int $userId, string $key, $default = null)
    {
        $pref = static::where('user_id', $userId)->where('key', $key)->first();

        return $pref ? $pref->value : $default;
    }

    /**
     * Set a user preference value.
     */
    public static function set(int $userId, string $key, $value): void
    {
        static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value]
        );
    }
}
