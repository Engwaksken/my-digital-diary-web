<?php

namespace App\Models;

use App\Notifications\LoginOtpNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class LoginOtp extends Model
{
    protected $fillable = ['user_id', 'code', 'attempts', 'expires_at', 'consumed_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return ! is_null($this->consumed_at);
    }

    /**
     * Generate a fresh 6-digit OTP for the given user, invalidate any
     * previous unconsumed codes, email it, and return the new record.
     * The plain code is never persisted or logged — only its hash is.
     */
    public static function createFor(User $user): self
    {
        static::where('user_id', $user->id)->whereNull('consumed_at')->delete();

        $plainCode = (string) random_int(100000, 999999);

        $otp = static::create([
            'user_id' => $user->id,
            'code' => Hash::make($plainCode),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $user->notify(new LoginOtpNotification($plainCode));

        return $otp;
    }
}
