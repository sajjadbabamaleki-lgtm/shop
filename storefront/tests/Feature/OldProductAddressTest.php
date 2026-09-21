<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTorobToken;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Addresses the shop published before this site existed.
 *
 * ترب, 2026-09-21: «همچنان ۳۸ تا از محصولات ما در دسترس نیستن», with one
 * example. **Measured from a GitHub runner against the live site**, which is
 * what turned a guess into this file:
 *
 *   they hold  /product/کفش/کتونی-نایک-مدل-وومرو-۵-رنگ-قهوه-ای-nike-vomero-5  → 404
 *   /products/ with that same slug, either case                             → 404
 *   the shop   /products/کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای          → on sale
 *
 * **The fixtures below are the live shop, read off it rather than imagined**,
 * and the first version of this file is the reason that sentence is here. It
 * seeded shoes titled «کتونی نایک وومرو Nike Vomero 5 رنگ قهوه ای» — which is
 * this shop's *slug* spelled with spaces, and a title no product here has ever
 * carried. Every test passed and every one of these addresses answered 404 on
 * the live site, because on a catalogue made by `basalam:import` the title is
 * terse and the slug carries the identity. Measured 2026-09-21, from the live
 * sitemap (148 products) and the live product pages:
 *
 *   title  کتونی نایک وومرو                              ← all five colourways, identical
 *   slug   کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای
 *
 * So every shoe here is seeded with a **real live slug** and the terse title
 * its family really carries. Where a family's title was not itself measured
 * the terse form is used, which is the weaker case: less to score against, so
 * a rule that passes here passes on a richer title too.
 *
 * **Products are looked up by slug, never by title**, because on this shop a
 * title names a dozen shoes.
 */
class OldProductAddressTest extends TestCase
{
    use RefreshDatabase;

    /** The address ترب holds, exactly as they sent it. */
    private const THEIRS = '/product/کفش/کتونی-نایک-مدل-وومرو-۵-رنگ-قهوه-ای-nike-vomero-5';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
    }

    /**
     * A shoe the way `basalam:import` really makes one: a terse title and a
     * slug carrying the make, the Latin name and the colour. The two are
     * passed separately because on this shop they really are different
     * strings — assuming one could be derived from the other is the mistake
     * this whole file was rewritten to stop repeating.
     */
    private function aShoe(string $title, ?string $slug = null): Product
    {
        $slug ??= trim(preg_replace('~[^\p{L}\p{N}]+~u', '-', $title) ?? '', '-');

        $product = Product::create([
            'slug' => $slug,
            'title' => $title,
            'short_title' => mb_substr($title, 0, 20),
            'status' => 'active',
            'published_at' => now(),
        ]);

        $variant = $product->variants()->create([
            // Off the slug, not the title: a dozen of these shoes share one
            // title, which is the whole point of the fixture.
            'sku' => 'VP-OLD-'.strtoupper(mb_substr(md5($slug), 0, 8)),
            'size_value' => '40',
            'size_system' => 'EU',
            'display_color' => Str::afterLast($slug, 'رنگ-') ?: 'قهوه ای',
            'color_family' => 'other',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => 4_000_000,
            'status' => 'active',
        ]);

        BranchInventory::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);

        return $product;
    }

    /**
     * The five Vomero colourways the live listing really carries, plus decoys.
     *
     * One title, five slugs — that is the shop, not a simplification.
     */
    private function theVomeros(): Product
    {
        $brown = $this->aShoe('کتونی نایک وومرو', 'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای');

        foreach (['سرمه-ای', 'سفید', 'مشکی', 'موکا'] as $colour) {
            $this->aShoe('کتونی نایک وومرو', "کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-{$colour}");
        }

        // Same make, same colour, a different shoe — the one a looser rule
        // would pick by mistake. Eight of these are on the live shop.
        $this->aShoe('کتونی نایک وی تو کی', 'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-قهوه-ای');
        $this->aShoe('کتونی نایک وی تو کی', 'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-موکا');

        return $brown;
    }

    /** The previous site's address opens the shoe it was naming. */
    public function test_the_old_scheme_opens_the_shoe_it_names(): void
    {
        $brown = $this->theVomeros();

        $this->get(self::THEIRS)->assertRedirect(storefront_route('product', $brown));
    }

    /**
     * The colour in the address is what decides, and it really does decide.
     *
     * All five share the title «کتونی نایک وومرو» exactly; only the colour in
     * the slug separates them, and picking the wrong one would send a shopper
     * to a shoe of the wrong colour with nothing going red.
     */
    public function test_it_picks_the_colour_the_address_names(): void
    {
        $this->theVomeros();

        foreach (['قهوه-ای', 'سرمه-ای', 'مشکی', 'سفید', 'موکا'] as $colour) {
            $wanted = Product::where('slug', "کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-{$colour}")->firstOrFail();

            $this->get('/product/کفش/کتونی-نایک-مدل-وومرو-۵-رنگ-'.$colour.'-nike-vomero-5')
                ->assertRedirect(storefront_route('product', $wanted));
        }
    }

    /** An old slug under the *current* path is an old address too. */
    public function test_the_old_slug_under_the_new_path_also_opens_it(): void
    {
        $brown = $this->theVomeros();

        $this->get('/products/کتونی-نایک-مدل-وومرو-۵-رنگ-قهوه-ای-nike-vomero-5')
            ->assertRedirect(storefront_route('product', $brown));
    }

    /**
     * **The feed answers it too, and that is the half ترب actually read.**
     * The page redirecting while the feed says nothing is the mistake that
     * cost two rounds on golden-goose.
     */
    public function test_the_feed_answers_the_old_address_with_the_live_product(): void
    {
        $brown = $this->theVomeros();

        config()->set('services.torob.enabled', true);

        $body = $this->withoutMiddleware(VerifyTorobToken::class)
            ->postJson('/torob_api/v3/products', ['page_urls' => ['https://vikyplus.ir'.self::THEIRS]])
            ->assertOk()
            ->json();

        $this->assertCount(1, $body['products'], 'The feed still answers ترب with nothing.');
        $this->assertSame((string) $brown->id, $body['products'][0]['page_unique']);
        $this->assertSame(storefront_route('product', $brown), $body['products'][0]['page_url']);
    }

    /** Two shoes that answer equally well is the shop unable to tell them apart. */
    public function test_a_tie_is_refused_rather_than_guessed(): void
    {
        // Two rows the shop cannot tell apart: one title, and slugs made of
        // the same words in a different order. This is not hypothetical —
        // `basalam:import` makes one product per supplier listing and the
        // supplier has listed the same colourway twice before.
        $this->aShoe('کتونی نایک وومرو', 'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای');
        $this->aShoe('کتونی نایک وومرو', 'کتونی-نایک-وومرو-رنگ-قهوه-ای-Nike-Vomero-5');

        $this->get(self::THEIRS)->assertNotFound();
    }

    /** An address naming nothing the shop sells is still a 404. */
    public function test_an_address_for_a_shoe_that_is_not_here_is_still_not_found(): void
    {
        $this->theVomeros();

        $this->get('/product/کفش/کفش-اسکیت-سالومون-مدل-اکس-اولترا-رنگ-بنفش')->assertNotFound();
        $this->get('/products/there-has-never-been-such-a-shoe')->assertNotFound();
    }

    /**
     * A short address names a section, not a shoe.
     *
     * Without this, «/product/کفش/کتونی-نایک» would pick whichever Nike
     * trainer happened to score highest and call it a match.
     */
    public function test_too_few_words_is_refused(): void
    {
        $this->theVomeros();

        $this->get('/product/کفش/کتونی-نایک')->assertNotFound();
    }

    /**
     * **Every address ترب sent on 2026-09-21, against one rule.**
     *
     * They sent eleven after being asked for the reason rather than a list —
     * «نباید تک تک درست کنی … این مشکل باید ریشه ای حل بشه» — and they are the
     * reason this test exists in this shape: nothing here is fixed per
     * product, and between them they carry every shape the old site produced.
     *
     *  - a slug cut mid-word: «…-رنگ-سفید-مشکی-ai», «…-air-jor», «…-adidas-sa»
     *  - filler this shop's titles never use: «مدل», «محصول»
     *  - the five in Persian digits: «وومرو-۵»
     *  - two that are not old addresses at all but this site's own retired
     *    setup shoes, reached through the same rule from the other side
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function theAddressesTorobSent(): array
    {
        // [ the address ترب holds , the slug the live shop answers it with ]
        return [
            'vomero brown' => ['/product/کفش/کتونی-نایک-مدل-وومرو-۵-رنگ-قهوه-ای-nike-vomero-5', 'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای'],
            'vomero black' => ['/product/کفش/کتونی-نایک-مدل-وومرو-۵-رنگ-مشکی-nike-vomero-5', 'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-مشکی'],
            'vomero navy' => ['/product/کفش/کتونی-نایک-مدل-وومرو-۵-رنگ-سرمه-ای-nike-vomero-5', 'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-سرمه-ای'],
            'jordan white+black cut' => ['/product/کفش/کتونی-نایک-مدل-جردن-وان-ساق-کوتاه-رنگ-سفید-مشکی-ai', 'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید-مشکی'],
            'jordan white cut' => ['/product/کفش/کتونی-نایک-مدل-جردن-وان-ساق-کوتاه-رنگ-سفید-air-jor', 'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید'],
            'samba brown' => ['/product/کفش/ونس-آدیداس-مدل-سامبا-رنگ-قهوه-ای-adidas-samba', 'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-قهوه-ای'],
            'samba silver cut' => ['/product/کفش/ونس-آدیداس-مدل-سامبا-رنگ-سفید-نقره-ایلمه-adidas-sa', 'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-سفید-نقره-ای-لمه'],
            'samba coffee cut' => ['/product/کفش/محصول-ونس-آدیداس-مدل-سامبا-رنگ-نسکافه-ای-adidas-sa', 'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-نسکافه-ای-سرمه-ای'],
            'knitted college' => ['/product/کفش/کالج-بافتی-رنگ-نسکافه-ای', 'کالج-بافتی-رنگ-نسکافه-ای'],
        ];
    }

    /**
     * The shop as the live listing really is, for the addresses above.
     *
     * Read off the live sitemap on 2026-09-21 — the whole of every family one
     * of those addresses reaches, not just the winner, so every answer below
     * has to be *chosen* against its own colourways rather than being the only
     * thing on the shelf. The Jordan family alone is twelve shoes under one
     * title, four of them white.
     */
    private function theShopTheySearched(): void
    {
        $shop = [
            'کتونی نایک وومرو' => [
                'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای',
                'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-مشکی',
                'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-سرمه-ای',
                'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-سفید',
                'کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-موکا',
            ],
            'کتونی نایک وی تو کی' => [
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-قهوه-ای',
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-موکا',
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-سرمه-ای',
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-نقره-ای',
                'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-مشکی',
            ],
            'کتونی نایک جردن وان ساق کوتاه' => [
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید',
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید-مشکی',
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید-صورتی',
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید-قرمز',
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-مشکی',
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-مشکی-قرمز',
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-مشکی-طوسی',
                'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-کرم-قهوه-ای',
            ],
            'کتونی نایک جردن وان ساق بلند' => [
                'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-مشکی',
                'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-قرمز',
                'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-سفید-صورتی',
                'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-طوسی',
            ],
            'ونس آدیداس سامبا' => [
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-قهوه-ای',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-سفید-نقره-ای-لمه',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-نسکافه-ای-سرمه-ای',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-سفید-طلایی-لمه',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-سفید-مشکی-لمه',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-سرمه-ای',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-مشکی',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-کرم-قهوه-ای',
                'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-سفید-قرمز',
            ],
            'صندل ادیداس سامبا چسبی' => [
                'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-قهوه-ای',
                'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-سرمه-ای',
                'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-کرم-قهوه-ای',
                'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-سفید-مشکی',
            ],
            'کالج بافتی' => [
                'کالج-بافتی-رنگ-نسکافه-ای',
                'کالج-بافتی-رنگ-قهوه-ای',
                'کالج-بافتی-رنگ-مشکی',
                'کالج-بافتی-رنگ-سرمه-ای',
            ],
            'کالج قفل و دایره' => [
                'کالج-قفل-و-دایره-رنگ-نسکافه-ای',
                'کالج-قفل-و-دایره-رنگ-قهوه-ای',
                'کالج-قفل-و-دایره-رنگ-موکا',
                'کالج-قفل-و-دایره-رنگ-مشکی',
            ],
        ];

        foreach ($shop as $title => $slugs) {
            foreach ($slugs as $slug) {
                $this->aShoe($title, $slug);
            }
        }
    }

    #[DataProvider('theAddressesTorobSent')]
    public function test_every_address_torob_sent_opens_the_shoe_it_names(string $address, string $slug): void
    {
        $this->theShopTheySearched();

        $wanted = Product::where('slug', $slug)->firstOrFail();

        $this->get($address)->assertRedirect(storefront_route('product', $wanted));
    }

    /**
     * **The two in their list that are this site's own retired setup shoes,
     * and the one place this rule deliberately still says no.**
     *
     * `/products/jordan-one-air` is «کتونی جردن وان ایر», retired on 07 Sept
     * by the client's own decision. Three of its four words are words this
     * shop uses — «ایر» is not, because the live titles write that part in
     * Latin as «Air» — and **it names no colour at all**. The shop sells
     * several Jordans and the address gives nothing to choose between them, so
     * any answer would be a guess about colour, on an address an aggregator is
     * holding. That is the mistake worth more than the 404 it would replace.
     *
     * So it gets the retired page: **200**, «دیگر عرضه نمی‌شود», and a link to
     * the brand's other shoes, where the shopper picks the colour themselves.
     * It is out of the feed, which for a shoe the shop stopped selling is the
     * true answer and the one ترب's schema asks for.
     *
     * If the live shop turns out to sell a shoe whose title really does carry
     * these words, this starts redirecting on its own and nothing here has to
     * change — which is the point of a rule over a list.
     */
    public function test_a_retired_shoe_the_address_cannot_identify_is_refused(): void
    {
        $this->theShopTheySearched();

        Product::where('slug', 'jordan-one-air')->firstOrFail()
            ->forceFill(['status' => 'archived', 'published_at' => null])->save();

        $this->get('/products/jordan-one-air')
            ->assertOk()
            ->assertSee('دیگر در فروشگاه عرضه نمی‌شود', false);
    }

    /**
     * A retired shoe the address *can* identify is redirected, by the same
     * rule and with no list anywhere.
     *
     * **Its title alone would not be enough**: «ونس آدیداس سامبا» is three
     * words, under SHORTEST, and is what a dozen live shoes are called. It is
     * the retired row's own *slug* that names the colour, which is why
     * `forRetired()` asks with both.
     */
    public function test_a_retired_shoe_the_address_can_identify_is_redirected(): void
    {
        $this->theShopTheySearched();

        $retired = $this->aShoe('ونس آدیداس سامبا', 'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-نسکافه-ای');
        $retired->forceFill(['status' => 'archived', 'published_at' => null])->save();

        $live = Product::where('slug', 'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-نسکافه-ای-سرمه-ای')->firstOrFail();

        $this->get('/products/'.$retired->slug)
            ->assertRedirect(storefront_route('product', $live));
    }

    /** Nothing about the addresses that already worked changes. */
    public function test_the_addresses_that_worked_still_work(): void
    {
        $this->theVomeros();

        $live = Product::query()->listable()->orderBy('id')->firstOrFail();

        $this->get(storefront_route('product', $live))->assertOk();
        $this->get('/products/golden-goose')->assertOk();
    }
}
