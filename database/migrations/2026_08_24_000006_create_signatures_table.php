<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the single users.signature_path column with a proper
 * one-to-many table — a user can now save several signatures (e.g. a
 * full signature and a set of initials) and pick which one to use each
 * time they sign a document, instead of only ever having one on file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('file_path');
            $table->timestamps();
        });

        // Carry forward anyone's existing single signature so this
        // change doesn't silently lose what they already uploaded.
        if (Schema::hasColumn('users', 'signature_path')) {
            $existing = DB::table('users')
                ->whereNotNull('signature_path')
                ->get(['id', 'signature_path']);

            foreach ($existing as $user) {
                DB::table('signatures')->insert([
                    'user_id' => $user->id,
                    'label' => 'My Signature',
                    'file_path' => $user->signature_path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('signed_documents', function (Blueprint $table) {
            $table->foreignId('signature_id')->nullable()->after('user_id')->constrained('signatures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signature_id');
        });

        Schema::dropIfExists('signatures');
    }
};
