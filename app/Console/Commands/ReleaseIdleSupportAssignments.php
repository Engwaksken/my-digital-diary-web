<?php

namespace App\Console\Commands;

use App\Models\SupportConversation;
use Illuminate\Console\Command;

class ReleaseIdleSupportAssignments extends Command
{
    protected $signature = 'support:release-idle-assignments';
    protected $description = 'Release human support assignments when a customer has not replied for five minutes.';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(5);
        $released = 0;

        SupportConversation::query()
            ->whereNotNull('assigned_to_user_id')
            ->whereNull('ended_at')
            ->where('status', 'human')
            ->chunkById(100, function ($conversations) use ($cutoff, &$released) {
                foreach ($conversations as $conversation) {
                    $lastSupport = $conversation->messages()
                        ->where('sender_type', 'support')
                        ->latest('created_at')
                        ->first();

                    if (! $lastSupport || $lastSupport->created_at->gt($cutoff)) {
                        continue;
                    }

                    $hasCustomerReply = $conversation->messages()
                        ->where('sender_type', 'user')
                        ->where('created_at', '>', $lastSupport->created_at)
                        ->exists();

                    if ($hasCustomerReply) {
                        continue;
                    }

                    $conversation->update([
                        'assigned_to_user_id' => null,
                        'assigned_at' => null,
                        'status' => 'ai',
                    ]);
                    $released++;
                }
            });

        $this->info("Released {$released} idle support assignment(s).");
        return self::SUCCESS;
    }
}
