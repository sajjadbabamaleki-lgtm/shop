<?php

namespace App\Providers;

use App\Support\Payments\AtTheDoor;
use App\Support\Payments\Gateway;
use App\Support\Payments\Gateways;
use App\Support\Payments\SnappPay;
use App\Support\Payments\ZarinPal;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Which gateways take the money.
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
 * cannot take one, which is worse than one that plainly does not offer it. The
 * same rule now covers `PAYMENT_INSTALMENTS`, and it matters more there, not
 * less: SnappPay needs four credentials and a shop that has three of them
 * would show an instalment button that refuses everybody.
 *
 * **Two variables, not one list**, because the two are not interchangeable:
 * `PAYMENT_DRIVER` is the gateway that takes a card, `PAYMENT_INSTALMENTS` is
 * the one that lends against the basket, and a shop can have either, both or
 * neither. `Gateway::class` keeps resolving to the card one so that everything
 * written when there was only ever one gateway still means what it meant.
 */
class PaymentServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<Gateway>> */
    private const DRIVERS = [
        'at-the-door' => AtTheDoor::class,
        'zarinpal' => ZarinPal::class,
    ];

    /** The instalment providers. None of them can take a card. */
    private const LENDERS = [
        'snapppay' => SnappPay::class,
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

        // **Bound rather than singleton, unlike the gateway itself.** It holds
        // no state worth keeping and building it is a few objects and no
        // network, while a cached list would go stale the moment anything
        // released the gateway under it — which is exactly what a test that
        // changes the driver mid-run does, and what a shop switching provider
        // on a running container would do.
        $this->app->bind(Gateways::class, function (): Gateways {
            $lender = $this->lender();

            // The card first — it is the ordinary way to pay, and the order
            // page draws these in the order they arrive. `at-the-door` is in
            // the list too rather than filtered out here: it answers no to
            // `takesMoneyOnline()`, which is where that decision belongs, and
            // it is what the bare callback address has to resolve to on a shop
            // with no card gateway.
            return new Gateways(...array_filter([$this->app->make(Gateway::class), $lender]));
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

    /** The instalment gateway, if the shop has one. */
    private function lender(): ?Gateway
    {
        $driver = (string) config('services.payment.instalments', '');

        if ($driver === '') {
            return null;
        }

        if (! array_key_exists($driver, self::LENDERS)) {
            throw new RuntimeException(
                "PAYMENT_INSTALMENTS is «{$driver}», which nothing implements. Known providers: "
                .implode(', ', array_keys(self::LENDERS)).'.'
            );
        }

        return $this->snapppay();
    }

    private function snapppay(): SnappPay
    {
        $settings = (array) config('services.payment.snapppay', []);

        // All four or none. SnappPay's own account (username, password) and
        // the integration's (client id, secret) are separate credentials with
        // separate jobs, and a shop holding three of them would put an
        // instalment button on every order page and refuse every shopper who
        // pressed it — the failure this whole guard exists to prevent.
        $missing = array_values(array_filter(
            ['client_id', 'client_secret', 'username', 'password'],
            fn (string $key): bool => (string) ($settings[$key] ?? '') === ''
        ));

        if ($missing !== []) {
            throw new RuntimeException(
                'PAYMENT_INSTALMENTS is «snapppay» but these are empty: '
                .implode(', ', array_map(fn (string $k): string => 'SNAPPPAY_'.strtoupper($k), $missing))
                .'. They come from the integration document SnappPay sends the shop '
                .'and are set as environment variables on the Liara app — see config/services.php.'
            );
        }

        return new SnappPay(
            baseUrl: rtrim((string) ($settings['base_url'] ?? ''), '/') ?: 'https://api.snapppay.ir',
            clientId: (string) $settings['client_id'],
            clientSecret: (string) $settings['client_secret'],
            username: (string) $settings['username'],
            password: (string) $settings['password'],
            commissionType: (int) ($settings['commission_type'] ?? 100),
        );
    }
}
