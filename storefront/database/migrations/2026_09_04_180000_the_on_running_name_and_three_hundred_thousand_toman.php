<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «آن رانینگ» on the Cloudtilt, and ۳۰۰٬۰۰۰ تومان on every price in the shop.
 *
 * **The name.** «اسم این کفش آن رانینگ هستش و چیزی که هست اشتباهه.» The product
 * was seeded as «کتونی اون کلادتیلت», renamed to «کتونی آن کلادتیلت» when the
 * brand's spelling was corrected, and the shop says the shoe is called On
 * Running. It is the only On the catalogue has — «کلادتیلت», «کلاد» and «اون»
 * each return this one row and no other — so there is no other product to point
 * at and the name on this one is what has to change.
 *
 * **The prices.** «یکاری کن ۳۰۰ هزارتومن به قیمت فعلی همه کفش ها اضافه کن.»
 *
 * Three things about that are worth writing down, because getting any of them
 * wrong is money.
 *
 *  - **Amounts here are Rial**, as everywhere else in this application, and
 *    `toman()` divides by ten to print. ۳۰۰٬۰۰۰ تومان is 3,000,000 Rial, and
 *    the constant below says so in both units so the next reader does not have
 *    to trust a bare number.
 *  - **`compare_at_price` rises with it.** It is the struck-through «was»
 *    price, and leaving it still would quietly shrink every discount on the
 *    site by ۳۰۰٬۰۰۰ — a change to the campaign that nobody asked for. It
 *    would also risk the table's own CHECK constraint, which requires
 *    `compare_at_price >= price`: a shoe whose two prices are within ۳۰۰٬۰۰۰
 *    of each other would fail the write outright.
 *  - **Every branch, in one statement.** `BranchOffer` is branch-scoped and a
 *    query with no branch bound correctly returns nothing, which in a migration
 *    would mean a green deploy that changed no price at all. So this goes
 *    through the query builder against the table, deliberately, and the row
 *    count is reported.
 *
 * `cost_price` is left alone: it is what the shop paid, and the shop did not
 * pay more because it decided to charge more.
 *
 * `down()` takes it back off, which is the only reason to write this as one
 * arithmetic step rather than as a list of new prices.
 */
return new class extends Migration
{
    /** ۳۰۰٬۰۰۰ تومان, in the Rial this application counts in. */
    private const RISE = 300_000 * 10;

    public function up(): void
    {
        Product::query()->where('slug', 'on-cloudtilt')->update([
            'title' => 'کتونی آن رانینگ',
            'short_title' => 'آن رانینگ',
        ]);

        $offers = DB::table('branch_offers')->update([
            'price' => DB::raw('price + '.self::RISE),
            // NULL + n is NULL in SQL, so a shoe with no «was» price keeps
            // having none without a case for it.
            'compare_at_price' => DB::raw('compare_at_price + '.self::RISE),
        ]);

        echo "  {$offers} prices raised by ۳۰۰٬۰۰۰ تومان\n";
    }

    public function down(): void
    {
        DB::table('branch_offers')->update([
            'price' => DB::raw('price - '.self::RISE),
            'compare_at_price' => DB::raw('compare_at_price - '.self::RISE),
        ]);

        Product::query()->where('slug', 'on-cloudtilt')->update([
            'title' => 'کتونی آن کلادتیلت',
            'short_title' => 'آن کلادتیلت',
        ]);
    }
};
