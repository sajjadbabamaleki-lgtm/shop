<?php

namespace App\Support\Checkout;

use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Session\Session;

/**
 * Finding a code, deciding whether it applies, and saying why not.
 *
 * Every refusal comes back as a sentence a customer can act on. One message
 * meaning six different things is how a shop gets a phone call.
 *
 * A code applies to the **branch's own lines only**. A vendor's price is
 * theirs; discounting it would be the platform spending somebody else's money,
 * or paying the vendor less than they listed for, and neither is a decision
 * anybody here has made. The basket says so rather than quietly counting a
 * smaller subtotal than the one on screen.
 */
class Discounts
{
    /** Where a typed code lives between requests. Per branch, like the basket. */
    private const SESSION = 'discount';

    public function __construct(
        private TenantContext $tenant,
        private Session $session,
    ) {}

    public function remember(string $code): void
    {
        $this->session->put($this->key(), mb_strtoupper(trim($code)));
    }

    public function forget(): void
    {
        $this->session->forget($this->key());
    }

    public function typed(): ?string
    {
        $code = $this->session->get($this->key());

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * The code as it stands against this basket right now.
     *
     * Recomputed on every page rather than stored with the basket, for the
     * same reason prices are: a code that expires, runs out or stops
     * qualifying while somebody shops has to stop applying, and a number
     * written down cannot do that.
     *
     * @return array{code: ?DiscountCode, amount: int, eligible: int, problem: ?string}
     */
    public function on(Cart $cart, ?int $customerId = null): array
    {
        $eligible = $this->eligibleSubtotal($cart);
        $none = ['code' => null, 'amount' => 0, 'eligible' => $eligible, 'problem' => null];

        $typed = $this->typed();

        if ($typed === null) {
            return $none;
        }

        $code = DiscountCode::query()
            ->usableAt($this->tenant->id())
            ->where('code', $typed)
            ->first();

        if ($code === null) {
            return array_merge($none, ['problem' => 'این کد شناخته نشد.']);
        }

        if (! $code->isLive()) {
            return array_merge($none, ['problem' => 'این کد دیگر معتبر نیست.']);
        }

        if ($eligible <= 0) {
            return array_merge($none, ['problem' => 'این کد روی کالای فروشندگان دیگر اعمال نمی‌شود.']);
        }

        if ($eligible < $code->min_subtotal) {
            return array_merge($none, [
                'problem' => 'این کد از '.toman($code->min_subtotal).' تومان به بالا اعمال می‌شود.',
            ]);
        }

        if ($code->usage_limit !== null && $code->redemptions()->count() >= $code->usage_limit) {
            return array_merge($none, ['problem' => 'ظرفیت این کد پر شده.']);
        }

        if ($customerId !== null && $code->usage_limit_per_customer !== null) {
            $mine = DiscountRedemption::where('discount_code_id', $code->id)
                ->where('customer_id', $customerId)
                ->count();

            if ($mine >= $code->usage_limit_per_customer) {
                return array_merge($none, ['problem' => 'این کد را قبلاً استفاده کرده‌ای.']);
            }
        }

        return [
            'code' => $code,
            'amount' => $code->amountOn($eligible),
            'eligible' => $eligible,
            'problem' => null,
        ];
    }

    /**
     * The part of the basket a code may discount: the branch's own lines.
     */
    public function eligibleSubtotal(Cart $cart): int
    {
        return (int) $cart->lines()
            ->filter(fn (array $line) => $line['offer'] !== null && $line['item']->vendor_id === null)
            ->sum('line_total');
    }

    private function key(): string
    {
        return self::SESSION.'.'.($this->tenant->id() ?? 'none');
    }
}
