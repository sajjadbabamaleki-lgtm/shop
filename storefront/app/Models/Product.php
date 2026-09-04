<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One shoe model. Every sellable colour-size combination is a Variant, so a
 * product that comes in four colours and six sizes is one row here and up to
 * twenty-four in variants.
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'title', 'short_title', 'brand_id', 'description', 'material',
        'care_instructions', 'use_case', 'status', 'source', 'source_id',
        'title_latin',
        'default_variant_id', 'seo_title', 'seo_description', 'canonical_url',
        'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /**
     * The variant a listing prices and adds to a basket when the customer has
     * not chosen a colour or size yet.
     */
    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'default_variant_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(VariantMedia::class)->orderBy('position');
    }

    /**
     * What the buyers said about it — every comment, whatever its state.
     *
     * The panel's queue reads this; the shop must not. `publishedComments()`
     * below is the storefront's way in, and it exists so that a view cannot
     * print somebody's unread sentence by writing `$product->comments`.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class);
    }

    /** The comments the shop prints: approved, newest first. */
    public function publishedComments(): HasMany
    {
        return $this->comments()->published()->latest('approved_at');
    }

    /**
     * The pivot is named product_category. Eloquent would infer
     * category_product from the two model names in alphabetical order, so the
     * table has to be named here or the relation queries one that was never
     * created.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Products a customer may actually reach: published, and carrying at
     * least one variant that can be sold (spec 6.2).
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('variants', fn (Builder $v) => $v->sellable());
    }

    /**
     * Products the listing shows, which is not the same set as the ones it can
     * sell.
     *
     * «نمیشه کفشی که موجودیش ۰ هست بیاد تو لیست فقط موجودی بزنه ۰؟» — and it
     * is the right question for a shop with a supplier in it. `purchasable()`
     * answers «what can go in a basket today», and a shoe the shop stocks and
     * has run out of disappears from the catalogue entirely, taking its page,
     * its address and every link to it with it. That is the correct answer for
     * the home page's bands, which exist to sell; it is the wrong one for the
     * shop, where a customer looking for a shoe would rather be told it is out
     * than be told it does not exist.
     *
     * So this is «what this branch sells», stock or no stock: published, and
     * carrying an offer here. A product with no offer at this branch is still
     * absent — a franchise lists what it has chosen to sell, and that has not
     * changed.
     *
     * Nothing else moves to it. `purchasable()` still guards the home page,
     * the story rings, the navigable categories and the basket, so an empty
     * shelf can be *seen* and still cannot be sold.
     */
    public function scopeListable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('variants', fn (Builder $v) => $v
                ->where('status', 'active')
                ->whereHas('offer', fn (Builder $offer) => $offer->active()));
    }

    /**
     * In stock first, then the rest — before whatever the shopper sorted by.
     *
     * A listing that shows what it has run out of has to lead with what it
     * has, or «تازه‌ترین» puts an empty shelf at the top of the shop. The
     * count is a subquery for the same reason the price sort is one: the
     * sellable half of two hundred products is not the sellable half of the
     * page you happen to be on.
     */
    public function scopeInStockFirst(Builder $query): Builder
    {
        return $query->orderByDesc(
            Variant::query()
                ->selectRaw('count(*)')
                ->whereColumn('variants.product_id', 'products.id')
                ->sellable()
        );
    }

    /**
     * Adds `branch_price` — the cheapest sellable offer this branch has for
     * the product — as a column on the row.
     *
     * A listing sorted by price cannot sort in PHP: the cheapest twelve of two
     * hundred products are not the cheapest twelve of the page you happen to
     * be on. So the price the sort uses has to be in the query, and it is the
     * branch's price, through the branch's own scope. With nothing bound the
     * subquery matches nothing and every product prices at null — the same
     * failing-closed as everywhere else.
     */
    public function scopePricedHere(Builder $query): Builder
    {
        $cheapest = BranchOffer::query()
            ->selectRaw('min(branch_offers.price)')
            ->join('variants', 'variants.id', '=', 'branch_offers.variant_id')
            ->whereColumn('variants.product_id', 'products.id')
            ->where('branch_offers.status', 'active');

        return $query->select('products.*')->selectSub($cheapest, 'branch_price');
    }

    /**
     * Adds `units_sold` — how many of the product this branch has actually
     * sold — as a column on the row.
     *
     * Same reason as `pricedHere()`: the ten best sellers of two hundred
     * products are not the ten best sellers of whichever page you are on, so
     * the count has to be in the query.
     *
     * Counted off **paid** orders only, and off `order_items` rather than the
     * catalogue: what sold is a fact about orders, and a basket somebody
     * abandoned is not a sale. `Order` is branch-scoped, so this is what this
     * shop sold and not what the chain did — a franchise's best sellers are its
     * own. `coalesce` so a product nobody has bought sorts as 0 and not as
     * null, which in Postgres would sort *first* on a descending order and put
     * the things that have never sold at the top of the best-seller list.
     *
     * Composes with `pricedHere()` in either order. That needs saying, because
     * `select()` replaces the column list rather than adding to it: two scopes
     * that both opened with `select('products.*')` would leave whichever ran
     * second having quietly dropped the other's column, and the only symptom
     * would be a sort that does nothing.
     */
    public function scopeCountingSales(Builder $query): Builder
    {
        $sold = OrderItem::query()
            ->selectRaw('coalesce(sum(order_items.quantity), 0)')
            ->join('variants', 'variants.id', '=', 'order_items.variant_id')
            ->whereColumn('variants.product_id', 'products.id')
            ->whereIn('order_items.order_id', Order::query()->select('id')->where('status', Order::PAID));

        if (empty($query->getQuery()->columns)) {
            $query->select('products.*');
        }

        return $query->selectSub($sold, 'units_sold');
    }

    /**
     * The same count, but only over the last few weeks.
     *
     * This is what «پرطرفدار» sorts on, and it exists so that tab and
     * «پرفروش‌ترین» are not the same query wearing two labels. All-time units
     * is a record of what has sold; a trailing window is a record of what is
     * selling, and on a shop that has been open a while the two lists are
     * genuinely different — a shoe that sold four hundred pairs two years ago
     * outranks everything on the first and nothing on the second.
     *
     * They do agree today, because the demo catalogue has no paid orders at
     * all and every product ties on nought. That is not a reason to collapse
     * them into one sort; it is a reason to write down why they look identical
     * before the shop takes its first order.
     *
     * The window is a parameter rather than a constant so a caller can widen
     * it without a migration, and it is measured on the order's own date, not
     * the item's — an item has no date of its own.
     */
    public function scopeCountingRecentSales(Builder $query, int $days = 30): Builder
    {
        $sold = OrderItem::query()
            ->selectRaw('coalesce(sum(order_items.quantity), 0)')
            ->join('variants', 'variants.id', '=', 'order_items.variant_id')
            ->whereColumn('variants.product_id', 'products.id')
            ->whereIn('order_items.order_id', Order::query()
                ->select('id')
                ->where('status', Order::PAID)
                ->where('created_at', '>=', now()->subDays($days)));

        if (empty($query->getQuery()->columns)) {
            $query->select('products.*');
        }

        return $query->selectSub($sold, 'units_sold_recent');
    }

    /**
     * The name a card shows, which is not always the whole name.
     *
     * The shop's own products are named in three or four words — «کتونی گلدن
     * گوس» — and a supplier's are not: «کتونی نایک جردن وان ساق کوتاه Air
     * Jordan 1 Low رنگ سفید قرمز» is one product, and on a 177px tile it is
     * three lines of type under the photograph, pushing the price off the
     * card's own shape. «اسم طولانیه در قسمت اسم باید ۴ تا ۶ کلمه نهایتا بیاد
     * باقیش بره تو صفحه جزئیات محصول».
     *
     * Trimmed for display rather than stored short, because the whole name is
     * the product's real name — the product page prints it in full, the search
     * matches it in full, and a card is a label, not a record. Four words,
     * «برای اسم نهایتا ۴ کلمه», with an ellipsis so the card says it is a label
     * rather than pretending the name ends there.
     *
     * A name already inside the ceiling comes back untouched, and every one
     * of this shop's own is: the longest are «کتونی نایک وی۲کی ران» and «کتونی
     * جردن وان ایر» at exactly four. That is why no existing card moves and
     * check-parity.js still prints zero.
     */
    public function cardName(int $words = 4): string
    {
        return Str::words($this->persianTitle(), $words, '…');
    }

    /**
     * The name «پرفروش‌ترین‌ها» prints: the card's name without the kind in
     * front of it.
     *
     * That band's tile is a name and a price on one strip, and the client asked
     * for the kind off the front so the two fit on one line. It is the same
     * name the listing prints, one word shorter — «کتونی گلدن گوس» there,
     * «گلدن گوس» here.
     *
     * **It used to be the `short_title` column, and that was the bug.** The
     * column is nullable, is written by hand in `/admin/catalogue` and by the
     * importer, and **nothing else in the shop reads it** — every other surface
     * prints `title` through `cardName()`. So a product created in the panel
     * with the field left blank drew a tile with no name at all, and one whose
     * title was corrected afterwards kept the old name here and nowhere else.
     * «اسم کفشها و قیمتشون هم باید از فروشگاه خونده بشه.»
     *
     * Only «کتونی» comes off, and only from the front. It is the one word the
     * request was about and the only kind the band's own shoes carry; a bag or
     * a sandal keeps its name whole, which is what the shop calls it.
     */
    public function bandName(int $words = 4): string
    {
        $name = Str::of($this->persianTitle())->trim();

        if ($name->startsWith('کتونی ')) {
            $name = $name->after('کتونی ');
        }

        return Str::words((string) $name, $words, '…');
    }

    /**
     * A title split where it stops being Persian: the name to show, and the
     * tail to keep.
     *
     * A supplier's title carries the same name twice. Basalam sells «کتونی
     * نیوبالانس New balance 530» and «کتونی نایک جردن وان ساق کوتاه Air Jordan
     * 1 Low رنگ سفید قرمز» — the Persian name, then the Latin one, then
     * whatever else was worth typing into a marketplace search box. Printed
     * whole, that is a heading in two alphabets; four words off the front of
     * it is two words of name and two of the same name again.
     *
     * So the tail comes off the *stored* title and lives in `title_latin`,
     * which nothing displays and the search matches — «اسم انگلیسی کفشارو …
     * یجایی گذاشت تو بک اند … که وقتی به انگلیسی سرچ میشه اون کفش بیاد و ولی
     * تو اسم کفش بصورت ظاهری نباشه». `brands.name_latin` has held a brand's
     * Latin name for the same reason since the catalogue was built; this is
     * that, one table along.
     *
     * Digits are not letters, so «کتونی نیوبالانس 530» keeps its 530 in either
     * set of numerals. A title that opens in Latin is left whole rather than
     * emptied — there is no Persian name in it to prefer.
     *
     * @return array{0: string, 1: string} what to show, and what to keep
     */
    public static function splitTitle(string $title): array
    {
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $head = [];

        foreach ($words as $index => $word) {
            if (preg_match('/\p{Latin}/u', $word)) {
                return $head === []
                    ? [trim($title), '']
                    : [implode(' ', $head), implode(' ', array_slice($words, $index))];
            }

            $head[] = $word;
        }

        return [implode(' ', $head), ''];
    }

    /**
     * The stored title is already the Persian half — the migration split every
     * row and the importer splits every new one. This is the same cut applied
     * again, for a title typed into the panel by hand and never through
     * either.
     */
    private function persianTitle(): string
    {
        return static::splitTitle($this->title)[0];
    }

    /**
     * What a card prints where a discount would go.
     *
     * That line is the sale — the cut as a chip and the old price struck
     * through — and on a shoe with no sale it was empty air. «اگه اون کفش تخفیف
     * داشت اولویت اختصاص دادن اون فضا به نمایش تخفیف باشه و اگه تخفیف نداشت
     * اعلام موجودی رنگ و سایز.» So the card asks for this only when there is no
     * cut, and the strategy lives in the view, in those two words.
     *
     * **Only what the catalogue actually knows.** The sizes are the ones this
     * branch can sell today, counted distinctly, so a shoe listed in five and
     * holding two says two. The colours are only mentioned when there is more
     * than one *named* colourway: every variant in this catalogue still carries
     * `color_family = unspecified`, and the product page's own «۳ رنگ موجود» is
     * a placeholder out of config — printing a count off that would be a claim
     * nobody made, on the one line a shopper reads as fact. One named colour is
     * not a choice either, so it is not announced.
     *
     * Null when nothing is sellable. The tile already says «ناموجود», and
     * «۰ سایز موجود» is the same news said worse.
     */
    public function stockLine(): ?string
    {
        $sizes = $this->variants
            ->filter(fn (Variant $variant) => $variant->isSellable())
            ->pluck('size_value')
            ->filter(fn ($size) => $size !== null && $size !== '')
            ->unique()
            ->count();

        if ($sizes === 0) {
            return null;
        }

        $colours = $this->colorways()
            ->filter(fn (array $way) => $way['sellable'] && $way['color_family'] !== 'unspecified')
            ->count();

        $line = fa_number($sizes).' سایز';

        if ($colours > 1) {
            $line .= ' و '.fa_number($colours).' رنگ';
        }

        return $line.' موجود';
    }

    /**
     * The colourways offered by the product page's colour selector, each
     * carrying its own sizes. Changing colour must update media, price and
     * availability together (spec 16.1), so they travel as one structure.
     *
     * `from_price` is the cheapest size **at the branch this request belongs
     * to**, and null where the branch lists none of them — a shop that does
     * not stock a colour has no price to quote for it.
     */
    public function colorways(): Collection
    {
        return $this->variants
            ->groupBy('display_color')
            ->map(fn (Collection $variants) => [
                'display_color' => $variants->first()->display_color,
                'color_family' => $variants->first()->color_family,
                'sellable' => $variants->contains(fn (Variant $v) => $v->isSellable()),
                'from_price' => $variants->map(fn (Variant $v) => $v->offer?->price)->filter()->min(),
                'sizes' => $variants->sortBy('size_value', SORT_NATURAL)->values(),
            ])
            ->values();
    }

    /**
     * The one image a card shows. The primary if a colourway has been marked
     * as such, otherwise whichever comes first by position.
     */
    public function primaryMedia(): ?VariantMedia
    {
        return $this->media->firstWhere('is_primary', true) ?? $this->media->first();
    }

    /**
     * A path there is always an image at.
     *
     * `primaryMedia()` returns null for a product whose photographs have not
     * been uploaded yet — an ordinary state, because the panel creates the
     * product first and the pictures after — and three views on the **home
     * page** read `->path` off it without checking. The hero, the stepped sale
     * and the daily deal each take whatever the catalogue gives them, so the
     * first product added through the panel and not yet photographed takes the
     * front page down for everybody, with a 500 nobody would connect to
     * «عکس نذاشتم».
     *
     * Found by cloning the catalogue tenfold and re-rendering, not by reading:
     * with the five seeded products, which all have photographs, it cannot
     * happen at all.
     *
     * So the null stops here rather than at six call sites, three of which
     * already had their own `@if` and three of which did not. Views ask for a
     * path and get one.
     */
    public function imagePath(): string
    {
        return $this->primaryMedia()?->path ?? 'assets/img/product-placeholder.svg';
    }

    /**
     * What this branch has left to sell, across every colour and size.
     */
    public function sellableStock(): int
    {
        return (int) $this->variants->sum(fn (Variant $variant) => $variant->sellableStock());
    }

    /**
     * The size a card's basket button adds.
     *
     * The default variant when this branch can supply it, and otherwise the
     * first size it can — a listing shows a product while *any* of its sizes
     * is sellable, so the default one is often the one that has just sold out,
     * and a button that adds it would put a line in the basket that the
     * checkout then refuses.
     */
    public function addableVariant(): ?Variant
    {
        $default = $this->defaultVariant;

        if ($default?->isSellable()) {
            return $default;
        }

        return $this->variants->first(fn (Variant $variant) => $variant->isSellable());
    }

    /**
     * The price a listing card shows: the default variant's, at the branch
     * this request belongs to.
     *
     * Falls back off the default variant when this branch does not list it.
     * `purchasable()` promises that *some* size is sellable here, not that the
     * default one is — so a shop that has stopped stocking the default size
     * still has a price to show, and a card for it does not render a blank
     * where the money goes.
     *
     * Null only when the branch lists none of the sizes at all, which the
     * purchasable scope already keeps out of every list a customer sees.
     *
     * Named for where it applies, and not `offer()`, because Variant::offer is
     * an Eloquent relation and two methods of one name doing two different
     * kinds of thing is how a template ends up writing `$product->offer` and
     * getting an exception it cannot read.
     */
    public function offerHere(): ?BranchOffer
    {
        return $this->defaultVariant?->offer
            ?? $this->variants->map(fn (Variant $variant) => $variant->offer)->filter()->sortBy('price')->first();
    }

    /**
     * Gallery for one colourway, falling back to the product-wide images when
     * a colour has none of its own.
     */
    public function mediaFor(?string $displayColor): Collection
    {
        $scoped = $this->media->where('display_color', $displayColor);

        return $scoped->isNotEmpty()
            ? $scoped->values()
            : $this->media->whereNull('display_color')->values();
    }
}
