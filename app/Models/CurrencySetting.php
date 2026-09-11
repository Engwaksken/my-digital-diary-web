<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CurrencySetting extends Model
{
    protected $fillable = [
        'base_currency',
        'display_currency',
        'refresh_minutes',
        'allow_user_selection',
    ];

    protected $casts = [
        'refresh_minutes' => 'integer',
        'allow_user_selection' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'base_currency' => config('currency.base_currency', 'UGX'),
            'display_currency' => config('currency.display_currency', 'UGX'),
            'refresh_minutes' => config('currency.refresh_minutes', 60),
            'allow_user_selection' => true,
        ]);
    }
}
