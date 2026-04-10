<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSubscription;
use App\Models\Notification;
use App\Services\SubscriptionService;
use App\Services\BroadcastService;
use Illuminate\Support\Str;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'app:send-subscription-reminders';
    protected $description = 'Remind users 3 days before their subscription expires';

    public function handle(SubscriptionService $subService, BroadcastService $broadcast)
    {
        $this->info('Checking for expiring subscriptions...');

        $targetDate = now()->addDays(3);

        UserSubscription::with(['user.account', 'plan'])
            ->where('is_active', true)
            ->where('auto_renew', true)
            ->whereDate('expires_at', $targetDate->toDateString())
            ->chunk(100, function ($subscriptions) use ($subService, $broadcast) {
                foreach ($subscriptions as $sub) {
                    $user = $sub->user;
                    $plan = $sub->plan;
                    
                    $renewalPrice = $subService->getBestPrice($user, $plan->id, (float) $plan->price);
                    $hasEnough = $user->account->balance >= $renewalPrice;

                    if ($hasEnough) {
                        $title = "Upcoming Renewal";
                        $message = "Your '{$plan->name_en}' plan will renew in 3 days. {$renewalPrice} GEL will be deducted from your balance.";
                        $status = 'balance_sufficient';
                    } else {
                        $title = "Top-up Required";
                        $message = "Your '{$plan->name_en}' plan expires in 3 days. Please top up at least " . ($renewalPrice - $user->account->balance) . " GEL to keep your service active.";
                        $status = 'insufficient_funds';
                    }

                    $notification = Notification::create([
                        'id' => Str::uuid(),
                        'user_id' => $user->id,
                        'type' => 'subscription_reminder',
                        'title' => $title,
                        'payload' => [
                            'message' => $message,
                            'plan_id' => $plan->id,
                            'renewal_price' => $renewalPrice,
                            'status' => $status
                        ],
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    $broadcast->sendUserNotify($user->id, 'notification_received', [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $message,
                        'type' => 'subscription_reminder'
                    ]);

                    $this->info("Reminder sent to user: {$user->id}");
                }
            });

        $this->info('Reminder process complete.');
    }
}