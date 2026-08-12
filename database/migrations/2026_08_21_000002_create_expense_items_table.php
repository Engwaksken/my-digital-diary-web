<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional line items for an expense (like an invoice) — most expenses
 * are still just a single category + amount, but a user can now itemize
 * one instead (e.g. a grocery run: 3 line items instead of one lump sum).
 * When items exist for an expense, its own `amount` column is kept in
 * sync as the SUM of item totals — see ExpenseController::syncAmountFromItems().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
