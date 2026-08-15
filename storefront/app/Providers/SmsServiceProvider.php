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
     * The same check again, at boot, so a production deploy configured to
     * deliver nothing fails on its first request rather than on its first
     * customer.
     *
     * `register()` cannot be where this lives on its own: the binding is a
     * singleton and nothing resolves it until somebody asks for a code, which
     * is a shopper standing at the sign-in wondering why no message came.
     */
    public function boot(): void
    {
        $this->refuseSilentDelivery((string) config('services.sms.driver', 'log'));
    }

    /**
     * `log` delivers nothing. In production that is a sign-in where every
     * shopper waits for a message that is only ever written to a file on the
     * server — the exact shape of silent failure this repository keeps being
     * bitten by, so it is refused with the reason attached rather than
     * discovered by a customer.
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
