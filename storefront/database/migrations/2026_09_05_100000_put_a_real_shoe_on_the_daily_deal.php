<?php

use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * «پیشنهاد روز» shows a shoe the shop sells.
 *
 * «این باید قیمتشو اسمشو از فروشگاه بخونه» — the band was drawing
 * `new-balance-530`, one of the five products this repository seeds a fresh
 * install with, still in production from setup day. Its name and its price are
 * a demo's, not the shop's: ۸٬۲۸۰٬۰۰۰ against the ۵٬۰۹۰٬۰۰۰ every New Balance
 * the shop really sells is priced at.
 *
 * It moves onto the shop's own «کتونی نیوبالانس New balance 530 رنگ سفید مشکی»
 * — the same shoe the band was standing in for, and the one already on the
 * first best-seller tile, so the two agree about what it costs.
 *
 * **Written as a placement, so the shop can change it without a deploy.**
 * `/admin/front-page` owns this band; the slug lives only in the live
 * catalogue, and putting it in `config/storefront.php` would put a name in
 * front of every test and both copies of the home page that nothing local can
 * resolve. A database without the product is left alone, which is every test
 * and this repository's own five sneakers.
 */
return new class extends Migration
{
    private const SHOE = 'کتونی-نیوبالانس-New-balance-530-رنگ-سفید-مشکی';

    public function up(): void
    {
        $id = Product::query()->where('slug', self::SHOE)->value('id');

        if ($id === null) {
            return;
        }

        FrontPagePlacement::query()->where('band', 'daily_deal')->delete();

        FrontPagePlacement::create([
            'band' => 'daily_deal',
            'product_id' => $id,
            'position' => 0,
        ]);
    }

    public function down(): void
    {
        FrontPagePlacement::query()->where('band', 'daily_deal')->delete();
    }
};
