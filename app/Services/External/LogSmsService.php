<?php

namespace App\Services\External;

use App\Contracts\SmsServiceInterface;
use Illuminate\Support\Facades\Log;

class LogSmsService implements SmsServiceInterface
{
    public function send(string $phone, string $message): void
   { 
     if (config('services.sms.simulate_failure')) {
        throw new \RuntimeException('Simulated SMS provider failure.');
     }

     Log::info('SMS sent successfully', [
        'phone' => $phone,
        'message' => $message,
      ]);
   }
}