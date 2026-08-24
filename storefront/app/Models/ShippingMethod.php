<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * How a parcel travels, and how long that takes — §10's shipping methods.
 *
 * Branch-scoped like everything operational here: the post takes a different
 * number of days out of Shiraz than out of Tehran, and a single platform-wide
 * transit estimate is a promise made in one city and broken in another.
 */
class ShippingMethod extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'name', 'carrier',
        'transit_min_days', 'transit_max_days', 'price', 'charge', 'is_active',
    ];

    /** The shop collects this method's price at checkout. */
    public const PREPAID = 'prepaid';

    /** «پس‌کرایه» — the customer pays the carrier on delivery, at their tariff. */
    public const COLLECT = 'collect';

    /**
     * What a shop offers on the day it opens.
     *
     * The client's three, in the order the checkout lists them: «پست پیشتاز /
     * پس‌کرایه», «تیپاکس / پس‌کرایه», «پست معمولی / ۲۰۰,۰۰۰ تومان». Every
     * branch gets them when it is created — see `Branch::booted()` — and every
     * one of them is editable from `/admin/fulfilment` afterwards, so this is
     * a starting point rather than the shop's prices.
     *
     * The migration that put them on the branches that already existed carries
     * its own frozen copy of this list on purpose: a migration that read a
     * constant would change meaning the next time somebody edited the constant,
     * and a migration has to mean the same thing forever.
     *
     * @var list<array{name: string, carrier: string, transit_min_days: int, transit_max_days: int, price: int, charge: string}>
     */
    public const DEFAULTS = [
        ['name' => 'پست پیشتاز', 'carrier' => 'شرکت ملی پست', 'transit_min_days' => 2, 'transit_max_days' => 4, 'price' => 0, 'charge' => self::COLLECT],
        ['name' => 'تیپاکس', 'carrier' => 'تیپاکس', 'transit_min_days' => 2, 'transit_max_days' => 5, 'price' => 0, 'charge' => self::COLLECT],
        ['name' => 'پست معمولی', 'carrier' => 'شرکت ملی پست', 'transit_min_days' => 4, 'transit_max_days' => 8, 'price' => 2_000_000, 'charge' => self::PREPAID],
    ];

    protected function casts(): array
    {
        return [
            'transit_min_days' => 'integer',
            'transit_max_days' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * **What this method adds to the order, which is not the same as its
     * price.**
     *
     * A پس‌کرایه method costs the shop nothing to offer and the customer
     * something the shop cannot know — the carrier's own tariff, quoted at the
     * door. So it adds zero here, and the order records that delivery is owed
     * rather than that it was free.
     *
     * Read through this everywhere rather than off `price` directly: the two
     * differ for exactly the methods where being wrong means charging somebody
     * twice or not at all.
     */
    public function costAtCheckout(): int
    {
        return $this->isCollect() ? 0 : (int) $this->price;
    }

    public function isCollect(): bool
    {
        return $this->charge === self::COLLECT;
    }

    /** What the customer, the panel and the invoice all call it. */
    public function chargeLabel(): string
    {
        return $this->isCollect()
            ? 'پس‌کرایه'
            : ($this->price > 0 ? toman($this->price).' تومان' : 'رایگان');
    }
}
