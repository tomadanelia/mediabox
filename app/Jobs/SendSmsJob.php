<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        protected string $phone,
        protected string $message
    ) {}

    public function handle(): void
    {
        $config = config('services.telecom1');
        
        $response = Http::post($config['url'], [
            'account_id' => (string) $config['account_id'],
            'key'        => (string) $config['key'],
            'from'       => (string) $config['from'],
            'to'         => (string) $this->phone,
            'message'    => (string) $this->message,
        ]);

        if ($response->failed() || $response->json('success') !== 'success') {
            throw new \Exception("SMS Provider Error: " . $response->body());
        }
    }
}