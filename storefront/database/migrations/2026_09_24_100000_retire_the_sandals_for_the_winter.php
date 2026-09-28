<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every sandal off the shop until spring.
 *
 * «همه سندلهارو بازنشسته کن چون تا ۵ ماه دیگه نمیخوایم برای فروش بزاریمشون» —
 * 2026-09-24. Five months is a season, not a decision about the shoes, so the
 * whole of this is built to be undone by a person in the panel, one shoe at a
 * time, with nothing to re-enter.
 *
 * **The product goes to `archived` and nothing else moves.** That is the
 * value the panel's «وضعیت» select writes for «غیرفعال» — as of this same
 * change: it used to post `inactive`, which `products.status` (an enum of
 * draft/active/archived) refuses, so the switch this retirement relies on
 * coming back through was a 500. So in five months every one of these comes
 * back by choosing «فعال» on its screen and pressing save.
 *
 * Clearing `published_at`, or turning the offers and variants off as well, as
 * the setup shoes were, would be two or three more switches to find and flip
 * again for every shoe — the sizes' is «بازنشسته کن» on the same screen, which
 * somebody bringing sandals back would reasonably not think to look at.
 *
 * So the prices, the struck-through prices, the sizes, the photographs, the
 * publication date and **the stock** are all exactly as they were, and are all
 * still there when the product is switched back on. `status !== 'active'` is
 * already everything the shop reads: out of the listing, the sections, the
 * search, the sitemap and ترب's feed, and not buyable — `PlaceOrder` refuses
 * it and the product page renders the retired panel instead.
 *
 * **What counts as a sandal is the shop's own «صندل» section, and the names
 * that would put a shoe in it.** The section rather than a list of slugs,
 * because a slug written from memory is a silent no-op (see `ReplacePhotos`);
 * the names as well, because a shoe added in a hurry is in no section at all,
 * and that is how 143 of 148 once were. `CategoriseByName` files «صندل» and
 * «اسلیپر» there — a raffia slipper is an open summer flat, which is the
 * reason the whole section is going away for the winter — and the wedding
 * sandals it also files under «مجلسی» are sandals all the same. The rule is
 * written out rather than called: a migration is frozen history, and the
 * class may learn new words later.
 *
 * **Only products that are on the shop now** — active and published — so a
 * sandal already off, or staged unpublished by an import, stays where it was.
 *
 * **The section is announced «به‌زودی»**, the same flag as the four sections
 * that were empty on 2026-08-31. A tile, a drawer row and a strip mark that
 * open onto «چیزی با این مشخصات پیدا نشد» for five months is a shop that looks
 * broken; with the flag the click puts up the coming-soon card and nothing
 * visible changes. Turning it back off when the sandals return is one line in
 * a migration, as `open_the_boot_section_now_it_has_shoes` did for boots.
 *
 * Front-page placements are left where they are. A band only draws what is
 * listable and falls back to its own query when every name it holds has gone
 * (`FrontPage::filter()`), so a sandal chosen for a band simply stops being
 * drawn — and is drawn again, in the place it was chosen for, the day it is
 * switched back on.
 */
return new class extends Migration
{
    private const SECTION = 'sandal';

    /** `CategoriseByName::BY_FIRST_WORD`'s sandal openings, on 2026-09-24. */
    private const OPENINGS = ['صندل', 'اسلیپر'];

    public function up(): void
    {
        $ids = $this->sandals('active');

        if ($ids !== []) {
            DB::table('products')->whereIn('id', $ids)->update(['status' => 'archived']);
        }

        DB::table('categories')->where('slug', self::SECTION)->update(['coming_soon' => true]);
    }

    /**
     * Every sandal switched off, back on — including any switched off in the
     * panel since, which a rollback cannot tell apart from these. A retirement
     * that also cleared `published_at` (the payment-test product's shape) was
     * somebody else's decision and is not touched.
     */
    public function down(): void
    {
        $ids = $this->sandals('archived');

        if ($ids !== []) {
            DB::table('products')->whereIn('id', $ids)->update(['status' => 'active']);
        }

        DB::table('categories')->where('slug', self::SECTION)->update(['coming_soon' => false]);
    }

    /** @return list<int> */
    private function sandals(string $status): array
    {
        $section = DB::table('categories')->where('slug', self::SECTION)->value('id');

        $filed = $section === null ? collect() : DB::table('product_category')
            ->where('category_id', $section)
            ->pluck('product_id');

        $named = DB::table('products')->where('status', $status)->get(['id', 'title'])
            ->filter(function (object $product): bool {
                $first = explode(' ', trim(preg_replace('/\s+/u', ' ', fold_persian($product->title)) ?? ''))[0];

                return in_array($first, array_map('fold_persian', self::OPENINGS), true);
            })
            ->pluck('id');

        return DB::table('products')
            ->where('status', $status)
            ->whereNotNull('published_at')
            ->whereIn('id', $filed->merge($named)->unique()->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
};
