<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
class PurgeScheduledAccountDeletions extends Command {
 protected $signature='accounts:purge-scheduled-deletions'; protected $description='Permanently delete accounts whose 30-day deletion grace period has ended';
 public function handle(): int { User::whereNotNull('scheduled_deletion_at')->where('scheduled_deletion_at','<=',now())->chunkById(50,function($users){foreach($users as $user){$this->info("Purging {$user->email}");$user->tokens()->delete();$user->delete();}}); return self::SUCCESS; }
}
