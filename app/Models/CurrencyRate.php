<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CurrencyRate extends Model
{
    protected $fillable = ['base_currency', 'quote_currency', 'rate', 'fetched_at'];

    protected $casts = [
        'rate' => 'decimal:10',
        'fetched_at' => 'datetime',
    ];
}
