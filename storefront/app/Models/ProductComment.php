<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one customer thought of one shoe.
 *
 * **Not branch-scoped.** A comment is about the shoe, and the shoe is the same
 * shoe at every branch — what differs is its price and what is left on the
 * shelf, not what somebody thought of wearing it. So this follows
 * `FrontPagePlacement` rather than `Order`. The right to write one is a
 * different question and is checked against the customer's own orders, which
 * are branch-scoped like everything else.
 *
 * Nothing reaches the shop until somebody has read it: `published()` is the
 * only scope the storefront may use, and `PENDING` is what the form writes.
 */
class ProductComment extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

    public const REJECTED = 'rejected';

    /**
     * The three states, and what the panel calls them.
     *
     * «رد شده» is kept rather than deleted so the same person cannot be asked
     * to read the same sentence twice, and so a shopkeeper can see what they
     * turned down.
     */
    public const LABELS = [
        self::PENDING => 'در انتظار تأیید',
        self::PUBLISHED => 'منتشر شده',
        self::REJECTED => 'رد شده',
    ];

    protected $fillable = ['product_id', 'customer_id', 'body', 'rating', 'status', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'rating' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The only scope the shop itself may read. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    /**
     * What the shop prints above the sentence.
     *
     * The name on the account when there is one — and there often is not: a
     * `customers` row is created at checkout off a telephone number, and the
     * name is a field on `/account` that nobody has to fill in.
     *
     * **The number is never printed whole.** It is the credential this shop
     * signs people in with, and a page every visitor can read is the last place
     * for it. The middle four digits are covered and the ends are kept, so a
     * customer can still recognise their own comment.
     *
     * Latin digits stay Latin here on purpose — `fa_number()` would fold a
     * masked number's stars into something the eye reads as one word. The
     * shop's own tests are the only thing that reads this shape.
     *
     * **A masked number has to be printed inside `<bdi dir="ltr">`**, which is
     * what `authorIsNumber()` below is for: digits and stars are all neutral
     * characters, so in an RTL paragraph the bidi algorithm reorders the runs
     * and `0912****566` comes out on screen as `566****0912`. Measured, in
     * Chromium, at 390 — it is not a thing that can be reasoned about from the
     * markup.
     */
    public function authorName(): string
    {
        $name = trim((string) $this->customer?->name);

        if ($name !== '') {
            return $name;
        }

        $phone = (string) $this->customer?->phone;

        return preg_match('/^(\d{4})\d{4}(\d{3})$/', $phone, $m) === 1
            ? $m[1].'****'.$m[2]
            : 'خریدار';
    }

    /** Whether `authorName()` is a masked number, and so needs `dir="ltr"`. */
    public function authorIsNumber(): bool
    {
        return trim((string) $this->customer?->name) === ''
            && preg_match('/^\d{11}$/', (string) $this->customer?->phone) === 1;
    }

    /**
     * The letter in the round mark beside the name.
     *
     * The client's reference has a photograph there. This shop has none — a
     * `customers` row is a telephone number and, if somebody filled it in, a
     * name — and putting stock faces on real customers' comments would be a
     * fabrication on the one part of the page whose whole value is that it is
     * not the shop talking.
     *
     * So it is the initial, on the shop's own gold tint, which is what the
     * mark is for: telling one comment from the next at a glance. A masked
     * number has no letter, so it gets «خ» for «خریدار» rather than a digit —
     * a numeral in a round chip reads as a count.
     */
    public function authorInitial(): string
    {
        if ($this->authorIsNumber()) {
            return 'خ';
        }

        $name = trim((string) $this->customer?->name);

        return $name === '' ? 'خ' : mb_substr($name, 0, 1);
    }
}
