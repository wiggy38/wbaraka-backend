<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;

class AfricasTalkingService
{
    private AfricasTalking $client;
    private string $senderId;

    public function __construct()
    {
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

        $result = $this->client->sms()->send($payload);

        return $result['SMSMessageData'] ?? $result;
    }
}
