<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSubscription;
use Illuminate\Support\Str;
use App\Models\Notification;
use App\Services\SubscriptionService;
use App\Services\BroadcastService;
class ProcessRenewals extends Command
{
    protected $signature = 'app:process-renewals';
    protected $description = 'Process subscription renewals';
public function handle(SubscriptionService $service, BroadcastService $broadcast)
{
    $this->info('Starting subscription renewal process...');
    $renewed = 0;
    $failed = 0;

    UserSubscription::with(['user.account', 'plan'])
        ->where('auto_renew', true)
        ->where('is_active', true)
        ->where('expires_at', '>=', now()->subHours(12))   
        ->where('expires_at', '<=', now()->addHours(24))   
        ->chunk(100, function ($expiringSubs) use ($service, $broadcast, &$renewed, &$failed) {
            foreach ($expiringSubs as $sub) {
                try {
                    $success = $service->renewSubscription($sub);
                    $sub->refresh();

                    $notification = Notification::create([
                        'id'      => Str::uuid(),
                        'user_id' => $sub->user_id,
                        'type'    => 'subscription_renewal',
                        'title'   => $success ? 'Subscription Renewed' : 'Renewal Failed',
                        'payload' => [
                            'plan_name'  => $sub->plan->name_en,
                            'status'     => $success ? 'success' : 'insufficient_funds',
                            'new_expiry' => $success ? $sub->expires_at->toDateString() : null
                        ],
                        'status' => 'pending'
                    ]);

                    $broadcast->sendUserNotify($sub->user_id, 'notification_received', [
                        'id'      => $notification->id,
                        'title'   => $notification->title,
                        'payload' => $notification->payload
                    ]);

                    $notification->update(['status' => 'sent', 'sent_at' => now()]);

                    $success ? $renewed++ : $failed++;
                    $this->info("User {$sub->user_id}: " . ($success ? 'renewed ✓' : 'insufficient funds ✗'));

                } catch (\Exception $e) {
                    $failed++;
                    $this->error("User {$sub->user_id} error: {$e->getMessage()}");
                    \Log::error('Renewal error', [
                        'user_id'    => $sub->user_id,
                        'plan_id'    => $sub->plan_id,
                        'expires_at' => $sub->expires_at,
                        'error'      => $e->getMessage(),
                        'trace'      => $e->getTraceAsString()
                    ]);
                }
            }
        });

    $this->info("Renewal process complete. Renewed: {$renewed}, Failed: {$failed}");
}
}