<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Redis;

class OtpService
{
    private const TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        private AfricasTalkingService $sms,
    ) {}

    public function generateAndSend(string $telephone): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        Redis::setex("otp:{$telephone}", self::TTL_SECONDS, $code);

        Otp::create([
            'telephone'  => $telephone,
            'code'       => $code,
            'expires_at' => $expiresAt,
            'used'       => false,
        ]);

        $this->sms->sendSms($telephone, "Votre code de vérification Baraka est : {$code}. Valable 5 minutes.");
    }

    public function verify(string $telephone, string $code): bool
    {
        $stored = Redis::get("otp:{$telephone}");

        if ($stored === null || $stored !== $code) {
            return false;
        }

        Redis::del("otp:{$telephone}");

        Otp::where('telephone', $telephone)
            ->where('code', $code)
            ->where('used', false)
            ->where('expires_at', '>=', now())
            ->latest('created_at')
            ->first()
            ?->update(['used' => true]);

        return true;
    }
}
