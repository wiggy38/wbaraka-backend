<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\Log;

class AfricasTalkingService
{
    private AfricasTalking $client;
    private string $senderId;

    public function __construct()
    {
        // On Windows/WAMP, cURL has no CA bundle. Set CURL_CA_BUNDLE in .env to a
        // cacert.pem path (e.g. C:/wamp64/bin/php/phpX/extras/ssl/cacert.pem).
        // putenv propagates the value so Guzzle's CurlHandler picks it up at request time.
        if ($ca = env('CURL_CA_BUNDLE')) {
            putenv("CURL_CA_BUNDLE={$ca}");
            putenv("SSL_CERT_FILE={$ca}");
        }

        $this->client = new AfricasTalking(
            config('services.africastalking.username'),
            config('services.africastalking.api_key'),
        );

        $this->senderId = config('services.africastalking.sender_id', '');
    }

    public function sendSms(string $telephone, string $message): array
    {
        $payload = [
            'to'      => $telephone,
            'message' => $message,
        ];

        if ($this->senderId !== '') {
            $payload['from'] = $this->senderId;
        }

        Log::info('AfricasTalking SMS sending', ['to' => $telephone]);

        try {
            $result = $this->client->sms()->send($payload);
        } catch (\Throwable $e) {
            Log::error('AfricasTalking SMS failed', [
                'to'      => $telephone,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        Log::info('AfricasTalking SMS sent', ['result' => $result]);

        return $result['SMSMessageData'] ?? $result;
    }
}
