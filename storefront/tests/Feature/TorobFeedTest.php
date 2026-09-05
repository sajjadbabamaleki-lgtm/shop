<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use App\Support\Torob\Token;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The feed Torob reads.
 *
 * **Everything here is a contract with somebody else's parser**, and their
 * warning is worth repeating: «هرگونه مغایرت فیلدها یا تایپ آن‌ها باعث از
 * دسترس خارج شدن محصول خواهد شد». A field of the wrong type does not fail — it
 * takes the product off Torob, quietly, and the shop finds out from its sales.
 * So the types are asserted as hard as the values.
 *
 * The token is signed with a key pair made here rather than mocked away. It is
 * the one part of this feature that nothing else in the application exercises,
 * it is what stands between a competitor and the shop's whole price list, and
 * a mocked verifier proves only that the mock was called.
 */
class TorobFeedTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/torob_api/v3/products';

    private string $secret;

    private string $pem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());

        // A key pair standing in for Torob's, so a real signature can be made.
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        $this->pem = "-----BEGIN PUBLIC KEY-----\n"
            .base64_encode(hex2bin('302a300506032b6570032100').sodium_crypto_sign_publickey($pair))
            ."\n-----END PUBLIC KEY-----";

        config()->set('services.torob.enabled', true);
        config()->set('services.torob.public_key', $this->pem);
    }

    /** A token the way Torob mints them. */
    private function token(array $claims = [], string $alg = 'EdDSA'): string
    {
        $b64 = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        $head = $b64(['alg' => $alg, 'typ' => 'JWT', 'v' => 1]);
        $body = $b64(array_merge([
            'aud' => 'localhost',
            'exp' => time() + 300,
            'nbf' => time() - 10,
        ], $claims));

        $signature = sodium_crypto_sign_detached($head.'.'.$body, $this->secret);

        return $head.'.'.$body.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function ask(array $body, ?string $token = null)
    {
        return $this->withHeaders([
            'X-Torob-Token' => $token ?? $this->token(),
            'Accept' => 'application/json',
        ])->postJson(self::URL, $body);
    }

    // ---- the token ---------------------------------------------------------

    public function test_a_request_with_no_token_is_refused(): void
    {
        $this->postJson(self::URL, ['page' => 1, 'sort' => 'date_added_desc'])
            ->assertStatus(401);
    }

    public function test_a_signature_from_the_wrong_key_is_refused(): void
    {
        $other = sodium_crypto_sign_keypair();
        $head = rtrim(strtr(base64_encode(json_encode(['alg' => 'EdDSA', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode(json_encode(['aud' => 'localhost', 'exp' => time() + 300])), '+/', '-_'), '=');
        $sig = sodium_crypto_sign_detached($head.'.'.$body, sodium_crypto_sign_secretkey($other));

        $this->ask(
            ['page' => 1, 'sort' => 'date_added_desc'],
            $head.'.'.$body.'.'.rtrim(strtr(base64_encode($sig), '+/', '-_'), '=')
        )->assertStatus(401);
    }

    /**
     * **A token minted for another shop does not open this one.**
     *
     * This is the check a JWT library will not make unless it is handed the
     * expected audience, and it is the one that matters most: everything else
     * about such a token is genuinely Torob's, including the signature.
     */
    public function test_a_token_for_another_host_is_refused(): void
    {
        $this->ask(['page' => 1, 'sort' => 'date_added_desc'], $this->token(['aud' => 'another-shop.ir']))
            ->assertStatus(401);
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->ask(['page' => 1, 'sort' => 'date_added_desc'], $this->token(['exp' => time() - 3600]))
            ->assertStatus(401);
    }

    public function test_a_token_that_is_not_yet_valid_is_refused(): void
    {
        $this->ask(['page' => 1, 'sort' => 'date_added_desc'], $this->token(['nbf' => time() + 3600]))
            ->assertStatus(401);
    }

    /** «alg: none» and its relatives: the token does not get to choose. */
    public function test_a_token_naming_another_algorithm_is_refused(): void
    {
        foreach (['none', 'HS256', 'RS256'] as $alg) {
            $this->ask(['page' => 1, 'sort' => 'date_added_desc'], $this->token([], $alg))
                ->assertStatus(401);
        }
    }

    public function test_the_feed_is_a_404_when_it_is_switched_off(): void
    {
        config()->set('services.torob.enabled', false);

        $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertStatus(404);
    }

    // ---- the request shapes ------------------------------------------------

    public function test_an_empty_body_is_a_400(): void
    {
        $this->ask([])->assertStatus(400)->assertJsonStructure(['error']);
    }

    public function test_a_page_with_no_sort_is_a_400(): void
    {
        $this->ask(['page' => 1])
            ->assertStatus(400)
            ->assertJson(['error' => 'sort parameter is not provided']);
    }

    public function test_an_unknown_sort_is_a_400(): void
    {
        $this->ask(['page' => 1, 'sort' => 'price_asc'])->assertStatus(400);
    }

    /** Their schema says int, so "1" is not a page number. */
    public function test_a_page_that_is_not_an_integer_is_a_400(): void
    {
        $this->ask(['page' => '1', 'sort' => 'date_added_desc'])->assertStatus(400);
        $this->ask(['page' => 0, 'sort' => 'date_added_desc'])->assertStatus(400);
    }

    public function test_an_empty_list_is_a_400(): void
    {
        $this->ask(['page_uniques' => []])->assertStatus(400);
        $this->ask(['page_urls' => []])->assertStatus(400);
    }

    // ---- the answer --------------------------------------------------------

    public function test_a_page_carries_their_envelope(): void
    {
        $body = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();

        $this->assertSame('torob_api_v3', $body['api_version']);
        $this->assertSame(1, $body['current_page']);
        $this->assertIsInt($body['total']);
        $this->assertSame(1, $body['max_pages']);
        $this->assertCount(Product::query()->listable()->count(), $body['products']);
    }

    /**
     * **Every field, and every type.**
     *
     * Asserted one at a time rather than with a structure match, because
     * `assertJsonStructure` is happy with a string where their schema says int
     * — and that is the failure that takes a product off Torob without a word.
     */
    public function test_a_row_matches_their_schema(): void
    {
        $body = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();

        foreach ($body['products'] as $row) {
            $this->assertIsString($row['page_unique']);
            $this->assertStringStartsWith('http', $row['page_url']);
            $this->assertIsString($row['title']);
            $this->assertIsInt($row['current_price']);
            $this->assertIsBool($row['availability']);
            $this->assertIsArray($row['image_links']);
            $this->assertIsArray($row['spec']);
            $this->assertNotNull($row['date_added']);

            // Timezone-aware ISO 8601, which their parser requires.
            $this->assertMatchesRegularExpression(
                '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/',
                $row['date_added']
            );

            foreach (['subtitle', 'old_price', 'category_name', 'short_desc', 'guarantee'] as $optional) {
                $this->assertArrayHasKey($optional, $row);
            }

            $this->assertLessThanOrEqual(200, mb_strlen($row['page_unique']));
            $this->assertLessThanOrEqual(500, mb_strlen($row['title']));
            $this->assertLessThanOrEqual(1500, mb_strlen($row['page_url']));

            foreach ($row['image_links'] as $link) {
                $this->assertStringStartsWith('http', $link, 'Image links must be absolute.');
                $this->assertLessThanOrEqual(1000, mb_strlen($link));
            }
        }
    }

    /**
     * **Toman, not Rial.** Confirmed with their support, and worth a case of
     * its own: being wrong here is a factor of ten in public.
     */
    public function test_the_price_is_in_toman(): void
    {
        $body = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();

        $row = collect($body['products'])->firstWhere('page_unique', (string) $this->aProduct()->id);

        $this->assertSame(
            intdiv($this->aProduct()->offerHere()->price, 10),
            $row['current_price']
        );
    }

    /** A discounted shoe carries the before-price; an undiscounted one carries null. */
    public function test_old_price_is_only_there_while_there_is_a_discount(): void
    {
        $body = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();

        foreach ($body['products'] as $row) {
            $product = Product::find((int) $row['page_unique']);
            $offer = $product->offerHere();

            if ($offer->hasActivePromotion()) {
                $this->assertSame(intdiv($offer->compare_at_price, 10), $row['old_price']);
                $this->assertGreaterThan($row['current_price'], $row['old_price']);
            } else {
                $this->assertNull($row['old_price']);
            }
        }
    }

    /**
     * **A shoe with an empty shelf stays in the feed**, with `availability`
     * false and its price still on it — «برای محصولات ناموجود این فیلد
     * می‌تواند مقدار صفر و یا قیمت قبل از ناموجود شدن را نشان دهد». Taking it
     * out is what happens when a product leaves the shop, not when it sells
     * out, and the two must not be confused: a shoe that comes back would
     * otherwise have to be re-approved by Torob from scratch.
     */
    public function test_a_sold_out_shoe_stays_in_the_feed_as_unavailable(): void
    {
        $product = $this->aProduct();

        BranchInventory::query()
            ->whereIn('variant_id', $product->variants()->pluck('id'))
            ->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

        $body = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();
        $row = collect($body['products'])->firstWhere('page_unique', (string) $product->id);

        $this->assertNotNull($row, 'A sold-out shoe left the feed. It should stay, marked unavailable.');
        $this->assertFalse($row['availability']);
        $this->assertIsInt($row['current_price']);
    }

    /** A shoe taken off the shop really does leave. */
    public function test_an_archived_shoe_leaves_the_feed(): void
    {
        $product = $this->aProduct();
        $product->update(['status' => 'archived']);

        $body = $this->ask(['page_uniques' => [(string) $product->id]])->assertOk()->json();

        $this->assertSame([], $body['products']);
        $this->assertSame(0, $body['total']);
        $this->assertSame(1, $body['max_pages']);
    }

    /**
     * **Both single-product shapes answer identically** — their specification
     * asks for it in as many words: «در هر دو درخواست خروجی دقیقا باید یکسان
     * باشد».
     */
    public function test_a_url_and_an_id_return_the_same_product(): void
    {
        $product = $this->aProduct();

        $byId = $this->ask(['page_uniques' => [(string) $product->id]])->assertOk()->json();
        $byUrl = $this->ask(['page_urls' => [storefront_route('product', $product)]])->assertOk()->json();

        $this->assertCount(1, $byId['products']);
        $this->assertSame($byId, $byUrl);
    }

    /** The address Torob will have is the one the feed itself prints. */
    public function test_the_url_it_publishes_is_one_it_can_read_back(): void
    {
        $page = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();

        foreach ($page['products'] as $row) {
            $back = $this->ask(['page_urls' => [$row['page_url']]])->assertOk()->json();

            $this->assertCount(1, $back['products'], "The feed cannot read back {$row['page_url']}.");
            $this->assertSame($row['page_unique'], $back['products'][0]['page_unique']);
        }
    }

    /** Sorting by either date is accepted and really orders the answer. */
    public function test_both_sorts_are_accepted_and_order_the_answer(): void
    {
        $this->aProduct()->touch();

        foreach (['date_added_desc' => 'date_added', 'date_updated_desc' => 'date_updated'] as $sort => $field) {
            $rows = $this->ask(['page' => 1, 'sort' => $sort])->assertOk()->json('products');

            $dates = array_column($rows, $field);
            $sorted = $dates;
            rsort($sorted);

            $this->assertSame($sorted, $dates, "{$sort} did not order the answer.");
        }
    }

    private function aProduct(): Product
    {
        return Product::query()->listable()->orderBy('id')->firstOrFail();
    }
}
