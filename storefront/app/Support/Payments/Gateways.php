<?php

namespace App\Support\Payments;

use App\Models\Order;

/**
 * Every way this shop can take money, in the order it offers them.
 *
 * **There used to be one gateway and one binding**, and three places asked the
 * container for it: the order page, the pay route and `PlaceOrder`. Connecting
 * اسنپ‌پی beside زرین‌پال breaks that shape — a shopper now chooses — so the
 * question those three ask becomes «which of them?» and this is the one place
 * that answers it.
 *
 * **The card gateway stays the primary binding.** `Gateway::class` still
 * resolves to it, so anything that only ever meant «the shop's gateway» is
 * unchanged, and a shop with no instalment provider configured behaves exactly
 * as it did. The instalment driver is the addition, and it is a second
 * environment variable rather than a list, because the two are not
 * interchangeable: one takes a card, the other lends against a basket, and a
 * shop can have either, both, or neither.
 *
 * Order matters: the card is first everywhere it is offered, because it is the
 * ordinary way to pay and the instalments are the alternative.
 */
class Gateways
{
    /** @var array<int, Gateway> */
    private array $drivers;

    public function __construct(Gateway ...$drivers)
    {
        $this->drivers = array_values($drivers);
    }

    /**
     * Everything configured, whether or not it can take money.
     *
     * @return array<int, Gateway>
     */
    public function all(): array
    {
        return $this->drivers;
    }

    /**
     * The buttons an order of this size should actually see.
     *
     * Two filters and they are different questions: `takesMoneyOnline()` is
     * «is this a gateway at all» — the no-gateway driver says no — and
     * `canTake()` is «will it take this amount», which is how an instalment
     * provider's floor and ceiling keep a certainly-refused button off the
     * page.
     *
     * @return array<int, Gateway>
     */
    public function offeredFor(int $amount): array
    {
        return array_values(array_filter(
            $this->drivers,
            fn (Gateway $gateway): bool => $gateway->takesMoneyOnline() && $gateway->canTake($amount)
        ));
    }

    /**
     * The gateways that lend rather than take a card.
     *
     * Named here rather than asked of each driver, because the rule that reads
     * it is the shop's and not the gateway's: «کد تخفیف فقط برای خرید نقدی
     * باشه و در خرید قسطی امکان استفاده ازش نباشه».
     */
    public const LENDERS = ['snapppay'];

    public static function lends(Gateway $gateway): bool
    {
        return in_array($gateway->name(), self::LENDERS, true);
    }

    /**
     * The buttons *this order* should see: `offeredFor()` its total, less the
     * lenders when a discount code is on it.
     *
     * **A discount code is for paying in cash, and only for that.** The code
     * is typed at checkout, before the shopper chooses how to pay — so the
     * rule cannot be enforced where the code is typed without asking a
     * question the checkout does not ask. It is enforced here instead, where
     * the choice is made: an order carrying a discount is offered the card
     * and nothing else, and the pay route refuses the lender on the same
     * test, because the page is a render and the post is what charges.
     *
     * @return array<int, Gateway>
     */
    public function offeredForOrder(Order $order): array
    {
        $offered = $this->offeredFor((int) $order->grand_total);

        if (! self::discountBarsLending($order)) {
            return $offered;
        }

        return array_values(array_filter($offered, fn (Gateway $gateway): bool => ! self::lends($gateway)));
    }

    /** Whether this order's discount keeps it off instalments. */
    public static function discountBarsLending(Order $order): bool
    {
        return (int) $order->discount_total > 0;
    }

    /**
     * Whether this shop could offer instalments at all — for the sentence
     * beside the discount field, which is only worth saying where there is an
     * instalment option to lose.
     */
    public function anyLender(): bool
    {
        foreach ($this->drivers as $driver) {
            if (self::lends($driver) && $driver->takesMoneyOnline()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Can this shop take money online at all, by any route?
     *
     * The question `PlaceOrder` asks to decide whether an order says «online»
     * or «پرداخت در محل», and the one the order page asks before it says
     * paying is not possible. Deliberately *not* about the amount: an order
     * too large for instalments is still an order that can be paid for, and
     * this must not depend on which basket happens to be in front of it.
     */
    public function anyOnline(): bool
    {
        foreach ($this->drivers as $driver) {
            if ($driver->takesMoneyOnline()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The driver going by this name, or null.
     *
     * Null rather than a throw, in both of the cases that matter: a URL naming
     * a gateway this shop does not have is a 404 the controller decides on,
     * and a `payments` row naming a provider the shop has since disconnected
     * is a customer who needs a sentence rather than a stack trace.
     *
     * With no name it is the shop's own gateway — which is what the callback
     * address that existed before any of this resolves to, so payments opened
     * by the old code still come home.
     */
    public function named(?string $name): ?Gateway
    {
        if ($name === null || $name === '') {
            return $this->drivers[0] ?? null;
        }

        foreach ($this->drivers as $driver) {
            if ($driver->name() === $name) {
                return $driver;
            }
        }

        return null;
    }
}
