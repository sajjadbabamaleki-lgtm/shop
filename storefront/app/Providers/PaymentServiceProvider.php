<?php

namespace App\Providers;

use App\Support\Payments\AtTheDoor;
use App\Support\Payments\Gateway;
use App\Support\Payments\ZarinPal;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Which gateway takes the money.
 *
 * Built the same way as `SmsServiceProvider`, and for the same reason: one
 * place that decides, so adding a provider is a class and a line rather than a
 * search through the controllers.
 *
 * **There is no «refuse to run in production» guard here, and that is the
 * difference from the SMS provider.** `log` delivers no sign-in code and is
 * always a misconfiguration; `at-the-door` delivers a real arrangement that
 * this shop has been running on since it opened. Refusing to boot on it would
 * take the shop down over a setting that is correct.
 *
 * What *is* refused is a gateway named and not configured: `PAYMENT_DRIVER` of
 * «zarinpal» with no merchant id is a checkout that offers a card payment and
 * cannot take one, which is worse than one that plainly does not offer it.
 */
class PaymentServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<Gateway>> */
    private const DRIVERS = [
        'at-the-door' => AtTheDoor::class,
        'zarinpal' => ZarinPal::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Gateway::class, function (): Gateway {
            $driver = (string) config('services.payment.driver', 'at-the-door');

            if (! array_key_exists($driver, self::DRIVERS)) {
                throw new RuntimeException(
                    "PAYMENT_DRIVER is «{$driver}», which nothing implements. Known drivers: "
                    .implode(', ', array_keys(self::DRIVERS)).'.'
                );
            }

            return $driver === 'zarinpal' ? $this->zarinpal() : new AtTheDoor;
        });
    }

    private function zarinpal(): ZarinPal
    {
        $merchant = (string) config('services.payment.zarinpal.merchant_id', '');

        // 36 characters with dashes, as ZarinPal issues them. Checked for
        // presence rather than shape — a merchant id that is merely the wrong
        // length still fails at the gateway with a message, while an empty one
        // fails with nothing to read.
        if ($merchant === '') {
            throw new RuntimeException(
                'PAYMENT_DRIVER is «zarinpal» but ZARINPAL_MERCHANT_ID is empty. '
                .'The merchant id comes from the ZarinPal panel and is set as an '
                .'environment variable on the Liara app — see config/services.php.'
            );
        }

        return new ZarinPal(
            merchantId: $merchant,
            sandbox: (bool) config('services.payment.zarinpal.sandbox', false),
        );
    }
}
