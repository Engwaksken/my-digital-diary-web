<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['borrowed', 'lent'])->comment('borrowed = money I owe someone; lent = money someone owes me');
            $table->string('person_name');
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['outstanding', 'paid'])->default('outstanding');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
