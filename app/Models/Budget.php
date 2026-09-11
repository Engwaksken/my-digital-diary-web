<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_budget_id',
        'category',
        'amount',
        'period',
        'month_year',
        'notes',
        'is_expensed',
        'expensed_at',
        'application_type',
        'debt_id',
        'applied_amount',
        'is_archived',
        'import_source',
        'import_filename',
        'import_confidence',
        'import_metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'is_expensed' => 'boolean',
        'expensed_at' => 'date',
        'is_archived' => 'boolean',
        'import_confidence' => 'decimal:2',
        'import_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }
}
