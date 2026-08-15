<?php

namespace App\Providers;

use App\Support\Sms\LogSender;
use App\Support\Sms\Sender;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Which sender the sign-in gets.
 *
 * One place, so adding a provider is a class and a line here rather than a
 * search through the controllers for wherever a code gets sent.
 */
class SmsServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<Sender>>
     */
    private const DRIVERS = [
        'log' => LogSender::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Sender::class, function (): Sender {
            $driver = (string) config('services.sms.driver', 'log');

            $this->refuseSilentDelivery($driver);

            $sender = self::DRIVERS[$driver] ?? null;

            if ($sender === null) {
                throw new RuntimeException(
                    "SMS_DRIVER is «{$driver}», which nothing implements. Known drivers: "
                    .implode(', ', array_keys(self::DRIVERS)).'.'
                );
            }

            return $this->app->make($sender);
        });
    }

    /**
     * `log` delivers nothing. In production that is a sign-in where a shopper
     * waits for a message only ever written to a file on the server — the exact
     * shape of silent failure this repository keeps being bitten by — so asking
     * for a sender there is refused with the reason attached rather than
     * answered with one that quietly throws the code away.
     *
     * **This is deliberately inside the singleton's factory and not in
     * `boot()`.** It was in `boot()` for one commit and CI went red on the spot:
     * `composer install` runs `artisan package:discover`, which boots the
     * application before any `.env` exists, and Laravel's own default for a
     * missing `APP_ENV` is «production» — so every install everywhere looked
     * like a misconfigured production deploy. The same throw on Liara would
     * have taken the whole shop down over a messaging setting; a shop that
     * mostly sells shoes without anybody signing in must not stop serving
     * because the SMS account is not open yet.
     *
     * Here, only the code path that would have sent something pays: the
     * sign-in 500s, loudly, and the catalogue, the basket and the checkout are
     * untouched.
     */
    private function refuseSilentDelivery(string $driver): void
    {
        if ($driver === 'log' && $this->app->environment('production')) {
            throw new RuntimeException(
                'SMS_DRIVER is «log», which delivers nothing. The shopper sign-in sends a '
                .'one-time code, so in production this has to be a real provider — see '
                .'config/services.php for what that needs.'
            );
        }
    }
}
