<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('relationship_type', ['mentor', 'mentee', 'peer', 'industry_contact', 'recruiter', 'client', 'other'])
                ->default('peer');
            $table->string('company')->nullable();
            $table->string('met_through')->nullable();
            $table->date('last_contact_date')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->text('goal')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_contacts');
    }
};
