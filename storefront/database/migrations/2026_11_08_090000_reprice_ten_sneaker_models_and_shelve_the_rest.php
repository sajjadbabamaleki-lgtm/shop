<?php

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Catalogue\EmptyShelves;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Ten sneaker models get a new price, and every other sneaker goes out of stock.
 *
 * The client, 2026-11-08, one price per model:
 *
 *     جردن ساق بلند ۴٬۹۹۸٬۰۰۰ · جردن ساق کوتاه ۴٬۵۹۸٬۰۰۰ · گلدن گوس ۴٬۵۹۸٬۰۰۰
 *     کتونی سامبا ۳٬۵۹۸٬۰۰۰ · گلدن استار ۳٬۹۹۸٬۰۰۰ · ایرفورس ۳٬۹۹۸٬۰۰۰
 *     نیوبالانس ۵۳۰ ۵٬۵۹۸٬۰۰۰ · ال استار ساق بلند ۳٬۶۹۸٬۰۰۰
 *     آن رانینگ ۴٬۵۹۸٬۰۰۰ · ال استار ساق کوتاه ۳٬۵۹۸٬۰۰۰
 *
 * «بجز بوت ها و این کتونی ها بقیه کتونی ها و ونس های دیگه ناموجود بشن».
 *
 * **Every rule below was written against the live catalogue**, read from a
 * runner the same day (sitemap → 181 product pages, their JSON-LD and their
 * sections), because the two mistakes this would otherwise make are silent:
 *
 *  - **«ال استار ساق کوتاه» is not in its own title.** The live title is
 *    «ونس کانورس ال استار ساق» — cut short by the import — and only the slug
 *    says «ساق-کوتاه». So the low All Star is «ال استار without بلند», read
 *    from title and slug together; the high one says «بلند» in both.
 *  - **A sneaker is its name, not its section.** Five boots and chakmehs are
 *    ticked into every section on the live shop, «ونس و کتونی» included, so
 *    reading the section would have emptied boots — exactly what was
 *    excluded. A product is a sneaker here when its name starts «کتونی»,
 *    «ونس» or «نایک», which is `CategoriseByName`'s own reading.
 *
 * «کتونی سامبا» is the «ونس آدیداس سامبا» family; the «صندل ادیداس سامبا
 * چسبی» is a sandal and keeps its price. «گلدن استار» takes both of its live
 * families, the plain one and «ساق بلند», since the client named one price.
 * What goes out of stock, measured: Jordan Travis Scott (6), Zara Vans (4),
 * Polo Vans (4), Nike V2K (8) and the Vomero (5, already out).
 *
 * The price is every size's, at the central shop only — a franchise sets its
 * own. A struck-through «before» price at or below the new price is cleared,
 * because the table refuses one that is not above it and a migration that
 * throws stops the shop from starting; one still above it stays, so a running
 * discount is not silently ended. Each offer is saved through the model so
 * the audit log says what each price was.
 *
 * Out of stock is `EmptyShelves` — the panel's «ناموجود کردن» — so the shoes
 * stay listed with «ناموجود», and pairs already held by an order are kept.
 *
 * It never throws (see `put_ten_more_pairs_on_every_size`), and on a fresh
 * database — every test, a new install — there is no catalogue yet and it
 * does nothing. `down()` is empty: the old prices are in the audit log, and
 * putting stock back on a shelf is a delivery, not a rollback.
 */
return new class extends Migration
{
    /** [what it is, Toman, test on the folded "title slug" text]. */
    private function models(): array
    {
        $has = fn (string ...$words) => fn (string $text): bool => collect($words)
            ->every(fn (string $w) => str_contains($text, ' '.$w.' '));

        return [
            ['جردن ساق بلند', 4_998_000, $has('جردن وان', 'ساق بلند')],
            ['جردن ساق کوتاه', 4_598_000, $has('جردن وان', 'ساق کوتاه')],
            ['گلدن گوس', 4_598_000, $has('گلدن گوس')],
            ['کتونی سامبا', 3_598_000, fn (string $t) => $has('سامبا')($t) && ! $has('صندل')($t)],
            ['گلدن استار', 3_998_000, $has('گلدن استار')],
            ['ایرفورس', 3_998_000, $has('air force')],
            ['نیوبالانس ۵۳۰', 5_598_000, $has('نیوبالانس', '530')],
            ['ال استار ساق بلند', 3_698_000, $has('ال استار', 'بلند')],
            ['آن رانینگ', 4_598_000, $has('ان رانینگ')],
            ['ال استار ساق کوتاه', 3_598_000, fn (string $t) => $has('ال استار')($t) && ! $has('بلند')($t)],
        ];
    }

    private const SNEAKER_FIRST_WORDS = ['کتونی', 'ونس', 'نایک'];

    public const NOTE = 'Out of stock: «بقیه کتونی ها و ونس ها ناموجود بشن», 2026-11-08.';

    public function up(): void
    {
        $central = Branch::query()->where('type', Branch::CENTRAL)->first();

        if ($central === null || ! Product::query()->exists()) {
            return;
        }

        try {
            app(TenantContext::class)->forBranch($central, fn () => $this->apply($central));
        } catch (Throwable $e) {
            $this->say('stopped: '.$e->getMessage());
        }
    }

    private function apply(Branch $central): void
    {
        $repriced = [];
        $shelve = [];

        foreach (Product::query()->get(['id', 'title', 'slug']) as $product) {
            $text = ' '.preg_replace('~[\s\-_]+~u', ' ', fold_persian(mb_strtolower($product->title.' '.$product->slug))).' ';
            $model = collect($this->models())->first(fn (array $m) => $m[2]($text));

            if ($model !== null) {
                $repriced[$model[0]][] = $this->reprice($product, $model[1] * 10);

                continue;
            }

            $first = explode(' ', trim(fold_persian($product->title)))[0] ?? '';

            if (in_array($first, self::SNEAKER_FIRST_WORDS, true)) {
                $shelve[] = $product->id;
            }
        }

        foreach ($repriced as $name => $counts) {
            $this->say(sprintf('%s: %d products, %d prices', $name, count($counts), array_sum($counts)));
        }

        $sizes = EmptyShelves::of($shelve, null, $central->id, self::NOTE);
        $this->say(sprintf('out of stock: %d products, %d sizes emptied', count($shelve), $sizes));
    }

    /** Every size's price at the bound branch; how many offers moved. */
    private function reprice(Product $product, int $rial): int
    {
        $moved = 0;
        $variants = Variant::where('product_id', $product->id)->pluck('id');

        foreach (BranchOffer::whereIn('variant_id', $variants)->get() as $offer) {
            $offer->price = $rial;

            if ($offer->compare_at_price !== null && $offer->compare_at_price <= $rial) {
                $offer->compare_at_price = null;
            }

            if ($offer->isDirty()) {
                $offer->save();
                $moved++;
            }
        }

        return $moved;
    }

    private function say(string $line): void
    {
        Log::info('reprice_ten_sneaker_models_and_shelve_the_rest: '.$line);
        fwrite(STDERR, "  reprice_ten_sneaker_models_and_shelve_the_rest: {$line}\n");
    }

    public function down(): void
    {
        // Deliberately empty. See the note above.
    }
};
