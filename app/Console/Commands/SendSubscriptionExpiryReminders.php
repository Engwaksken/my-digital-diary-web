<?php
namespace App\Console\Commands;
use App\Models\User;
use App\Notifications\SubscriptionExpiryReminderNotification;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
class SendSubscriptionExpiryReminders extends Command {
    protected $signature='subscriptions:send-expiry-reminders';
    protected $description='Send 8 AM and 8 PM trial/subscription expiry reminders when 14 days or fewer remain';
    public function handle(FcmService $fcm): int {
        User::whereIn('subscription_status',['trialing','active'])->whereNull('suspended_at')->chunkById(100,function($users){
            foreach($users as $user){
                $expiry=$user->relevantExpiryDate(); if(!$expiry) continue;
                $tz=$user->timezone ?: 'Africa/Kampala'; $localNow=now($tz); $hour=(int)$localNow->format('G'); $minute=(int)$localNow->format('i');
                if(!in_array($hour,[8,20],true) || $minute>=30) continue;
                $days=(int)$localNow->copy()->startOfDay()->diffInDays($expiry->copy()->timezone($tz)->startOfDay(),false);
                if($days<0 || $days>14) continue;
                $slot=$localNow->format('Y-m-d-H');
                if($user->last_expiry_reminder_at && $user->last_expiry_reminder_at->timezone($tz)->format('Y-m-d-H')===$slot) continue;
                try { $user->notify(new SubscriptionExpiryReminderNotification($expiry,$days));
                    $fcm->sendToUser($user, $days > 0 ? "Subscription expires in {$days} day(s)" : 'Subscription expires today', 'Renew now to keep full access to My Digital Diary.', ['type'=>'subscription_expiry','days_remaining'=>(string)$days]);
                    $user->forceFill(['last_expiry_reminder_days'=>$days,'last_expiry_reminder_at'=>now()])->save();
                    \App\Models\BillingEventLog::record('reminder_sent',$user->id,['recipient_email'=>$user->email,'details'=>"{$days} days before expiry ({$localNow->format('H:i')} {$tz})"]);
                } catch(Throwable $e){ Log::warning('Could not send subscription expiry reminder.',['user_id'=>$user->id,'error'=>$e->getMessage()]); }
            }
        }); return self::SUCCESS;
    }
}
