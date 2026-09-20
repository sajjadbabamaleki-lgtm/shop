<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTorobToken;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Tests\Support\ARetiredShoe;
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
    use ARetiredShoe;
    use RefreshDatabase;

    private const URL = '/torob_api/v3/products';

    /** The same feed on the path Torob's bot asks for. See routes/torob.php. */
    private const PREFIXED_URL = '/api/torob_api/v3/products';

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

    private function ask(array $body, ?string $token = null, string $url = self::URL)
    {
        return $this->withHeaders([
            'X-Torob-Token' => $token ?? $this->token(),
            'Accept' => 'application/json',
        ])->postJson($url, $body);
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

    /**
     * **The route must not be in the `web` group, and nothing else here can
     * tell you so.**
     *
     * The first version of this feature was registered in `routes/web.php`.
     * That group carries `ValidateCsrfToken`, and a POST from Torob's servers
     * has no CSRF token and no session to have got one from — so the live
     * endpoint answered **419 «صفحه منقضی شد»** to every request, before a line
     * of this feature ran. Torob's bot reported it as a failure to reach the
     * address at all.
     *
     * **Every other case in this file passed the whole time.** Laravel's own
     * `ValidateCsrfToken` skips itself when it detects a test run, so the suite
     * was exercising a middleware stack production does not have. That is the
     * shape of bug this repository keeps paying for: a guard that cannot fail
     * on the one thing that is wrong.
     *
     * So this asserts the stack itself rather than the behaviour. Session
     * middleware is named too — the feed is a machine reading JSON, and a
     * hundred pages of catalogue should not write a hundred sessions.
     */
    public function test_the_route_carries_no_session_or_csrf_middleware(): void
    {
        foreach (['torob.products', 'torob.products.prefixed'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "The feed has no route named {$name}.");

            // **Group names have to be expanded first.**
            // `gatherRouteMiddleware` returns `'web'` as the bare string rather
            // than the classes behind it, so a check for the CSRF class alone
            // passes on a route that is squarely inside the group — measured,
            // and it is how the first version of this very case came back green
            // on the bug it was written to catch.
            $groups = app(Router::class)->getMiddlewareGroups();

            $middleware = collect(app(Router::class)->gatherRouteMiddleware($route))
                ->flatMap(fn ($middleware) => $groups[$middleware] ?? [$middleware])
                ->map(fn ($middleware) => is_string($middleware) ? $middleware : $middleware::class)
                ->all();

            foreach (['web', ValidateCsrfToken::class, StartSession::class] as $unwanted) {
                $this->assertNotContains(
                    $unwanted,
                    $middleware,
                    "The feed's {$name} is behind {$unwanted}. Torob posts with no cookie and no ".
                    'token; in production that is a 419 on every request, and the test suite cannot '.
                    'see it because Laravel skips CSRF while testing. Keep this route out of the web group.'
                );
            }

            $this->assertContains(VerifyTorobToken::class, $middleware);
        }
    }

    /**
     * **The feed answers on `/api/torob_api/v3/products` as well.**
     *
     * Torob was given `https://vikyplus.ir/torob_api/v3/products` and reported
     * a 404 against it twice; both reports name the path their bot actually
     * asked for — «مسیر api/torob_api/v3/products در سرور شما یافت نشد» — with
     * an `api/` in front that appears in nothing we published. The second
     * report is timed half an hour after the endpoint was measured answering
     * 401 on both hosts, so it is not a deploy that had not landed.
     *
     * Serving both is a line of routing. Losing it means another week of a
     * feed that is correct and unreachable, which is what this case is here to
     * prevent.
     */
    public function test_the_feed_answers_on_the_path_torobs_bot_asks_for(): void
    {
        $canonical = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();
        $prefixed = $this->ask(['page' => 1, 'sort' => 'date_added_desc'], null, self::PREFIXED_URL)
            ->assertOk()->json();

        $this->assertSame($canonical, $prefixed, 'The two addresses must serve the same feed.');

        // And it is the same door, not an open one: the token is checked there
        // too. A second path that skipped the middleware would put the shop's
        // whole price list on a public URL.
        //
        // `flushHeaders` first — `withHeaders` sets the *default* headers for
        // the rest of the test, so without this the token from the two calls
        // above is still attached and the request is not tokenless at all. It
        // answered 200 and the case passed for the wrong reason; measured.
        $this->flushHeaders()
            ->postJson(self::PREFIXED_URL, ['page' => 1, 'sort' => 'date_added_desc'])
            ->assertStatus(401);
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

    // ---- the address a shoe leaves behind ----------------------------------

    /**
     * **ترب's third ticket about `golden-goose`, and the one the first two
     * fixes could not close.**
     *
     * 2026-09-20: «محصول نمونه‌ای که قبلاً با آدرس کوتاه golden-goose ثبت شده
     * بود، هنوز در فهرست فعلی محصولات ارسالی سایت یافت نمی‌شود». Both earlier
     * rounds worked on the product *page* — it stopped being a 404, then it
     * started redirecting to the living colourway — and neither touched the
     * feed, which is what «اطلاعات ارسالی سایت» means. Asked for that address,
     * the feed answered with an empty list, which is exactly how their schema
     * spells «this product no longer exists».
     *
     * Their instruction: «آدرس نهایی و عمومی همین محصول … را در اطلاعات ارسالی
     * سایت قرار دهد و آدرس‌های قدیمی را اصلاح کند». So the old address is
     * answered with the shoe that replaced it, carrying that shoe's own id and
     * its own final, public address.
     */
    public function test_an_old_address_answers_with_the_shoe_that_replaced_it(): void
    {
        $retired = $this->retire('golden-goose');
        $living = $this->aColourwayOf($retired, $retired->title.' رنگ صورتی Golden Goose');

        $body = $this->ask(['page_urls' => [url('/products/golden-goose')]])->assertOk()->json();

        $this->assertCount(1, $body['products'], 'The address ترب holds still answers with nothing.');
        $this->assertSame((string) $living->id, $body['products'][0]['page_unique']);
        $this->assertSame(storefront_route('product', $living), $body['products'][0]['page_url']);
        $this->assertSame(1, $body['total']);
    }

    /** The same correction when they ask by the id they filed rather than the address. */
    public function test_an_old_id_answers_with_the_shoe_that_replaced_it(): void
    {
        $retired = $this->retire('golden-goose');
        $living = $this->aColourwayOf($retired, $retired->title.' رنگ صورتی Golden Goose');

        $body = $this->ask(['page_uniques' => [(string) $retired->id]])->assertOk()->json();

        $this->assertCount(1, $body['products']);
        $this->assertSame((string) $living->id, $body['products'][0]['page_unique']);
    }

    /**
     * Asked for the old address and the new one together, they get one row.
     *
     * Their index keys on `page_unique`; the same id twice in one answer is a
     * contradiction rather than a duplicate, and duplicates are a thing they
     * have already complained about on this shop.
     */
    public function test_the_old_address_and_the_new_one_are_one_row(): void
    {
        $retired = $this->retire('golden-goose');
        $living = $this->aColourwayOf($retired, $retired->title.' رنگ صورتی Golden Goose');

        $body = $this->ask(['page_urls' => [
            url('/products/golden-goose'),
            storefront_route('product', $living),
        ]])->assertOk()->json();

        $this->assertCount(1, $body['products']);
        $this->assertSame((string) $living->id, $body['products'][0]['page_unique']);
    }

    /**
     * The address the feed hands back opens on a real product page.
     *
     * The other half of «آدرس نهایی و عمومی»: a corrected address that lands
     * on the «دیگر عرضه نمی‌شود» panel would be the same ticket again in a
     * different place.
     */
    public function test_the_corrected_address_opens_a_real_product_page(): void
    {
        $retired = $this->retire('golden-goose');
        $this->aColourwayOf($retired, $retired->title.' رنگ صورتی Golden Goose');

        $row = $this->ask(['page_urls' => [url('/products/golden-goose')]])->assertOk()->json('products.0');

        $this->get(parse_url($row['page_url'], PHP_URL_PATH))
            ->assertOk()
            ->assertDontSee('دیگر در فروشگاه عرضه نمی‌شود', false);
    }

    /**
     * A retired shoe with nothing like it on the shelf is still an empty list.
     *
     * The correction is a correction, not a habit of always answering with
     * *something*: where the shop really has stopped selling a shoe and has
     * nothing in its place, «this is gone» is the true answer and the one
     * their schema asks for.
     */
    public function test_a_retired_shoe_with_no_successor_is_still_an_empty_list(): void
    {
        $retired = $this->retire('golden-goose');

        $body = $this->ask(['page_urls' => [url('/products/golden-goose')]])->assertOk()->json();

        $this->assertSame([], $body['products']);
        $this->assertSame(0, $body['total']);
        $this->assertNull($body['products'][0]['page_unique'] ?? null);
        $this->assertSame('archived', $retired->fresh()->status);
    }

    /**
     * Correcting the old address does not put the retired shoe back in the shop.
     *
     * The listing must carry the living colourway and not the retired row —
     * one entry for one shoe. Undoing the client's own retirement
     * («این موارد اوایل راه اندازی سایت قرار داده شدن») would be a worse
     * answer to ترب than the one being fixed.
     */
    public function test_the_retired_shoe_is_still_out_of_the_listing(): void
    {
        $retired = $this->retire('golden-goose');
        $living = $this->aColourwayOf($retired, $retired->title.' رنگ صورتی Golden Goose');

        $rows = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json('products');

        $uniques = array_column($rows, 'page_unique');

        $this->assertNotContains((string) $retired->id, $uniques);
        $this->assertSame([(string) $living->id], array_values(array_filter(
            $uniques,
            fn (string $unique) => $unique === (string) $living->id,
        )));
    }

    /**
     * Somebody else's id shape is an empty list, and stays one.
     *
     * `page_unique` here is this shop's product id and the column is an
     * integer, while their own document's example id is «12412_1». Measured
     * on Postgres 16, a text comparison against a `bigint` column answers
     * with no rows rather than with an error, so this passed before the
     * lookup was touched and is here to keep it passing: the shape of an id
     * this shop did not mint must never become a 500, which their crawler
     * would read as a broken shop.
     */
    public function test_an_id_that_is_not_a_number_is_an_empty_list(): void
    {
        $body = $this->ask(['page_uniques' => ['12412_1']])->assertOk()->json();

        $this->assertSame([], $body['products']);
        $this->assertSame(0, $body['total']);
    }

    // ---- their cursor-based pagination -------------------------------------

    /** The envelope carries `next_cursor` in every shape, null where there is none. */
    public function test_the_envelope_always_carries_next_cursor(): void
    {
        $page = $this->ask(['page' => 1, 'sort' => 'date_added_desc'])->assertOk()->json();

        $this->assertArrayHasKey('next_cursor', $page);
        $this->assertNull($page['next_cursor']);

        $lookup = $this->ask(['page_uniques' => [(string) $this->aProduct()->id]])->assertOk()->json();

        $this->assertArrayHasKey('next_cursor', $lookup);
        $this->assertNull($lookup['next_cursor']);
    }

    /** Their first cursor page: sort alone, no page, no cursor. */
    public function test_a_first_cursor_page_is_the_newest_ids_first(): void
    {
        $body = $this->ask(['sort' => 'product_id_desc'])->assertOk()->json();

        $this->assertSame('torob_api_v3', $body['api_version']);
        $this->assertSame(1, $body['current_page']);

        $ids = array_map('intval', array_column($body['products'], 'page_unique'));
        $sorted = $ids;
        rsort($sorted);

        $this->assertSame($sorted, $ids, 'product_id_desc did not order the answer by descending id.');
        $this->assertNull($body['next_cursor'], 'A catalogue under a hundred is one page.');
    }

    /** A cursor really does exclude everything at or above it. */
    public function test_a_cursor_starts_below_the_id_it_names(): void
    {
        $ids = array_map('intval', array_column(
            $this->ask(['sort' => 'product_id_desc'])->assertOk()->json('products'),
            'page_unique',
        ));

        $this->assertGreaterThan(1, count($ids), 'This needs more than one product to mean anything.');

        $body = $this->ask(['cursor' => (string) $ids[0], 'sort' => 'product_id_desc'])->assertOk()->json();

        $this->assertSame(
            array_slice($ids, 1),
            array_map('intval', array_column($body['products'], 'page_unique')),
        );
    }

    /**
     * **The whole catalogue, walked by cursor, arrives exactly once.**
     *
     * This is the case worth the hundred rows it costs to set up. A numbered
     * page is an `OFFSET` and a catalogue that changes under it hands a shoe
     * over twice or never; the cursor exists so that cannot happen, and an
     * off-by-one in the «is there another page» test would lose exactly one
     * product per page, invisibly. Nothing smaller than a real second page
     * can see that.
     */
    public function test_the_cursor_walks_every_product_exactly_once(): void
    {
        $of = $this->aProduct();

        for ($i = 0; $i < 100; $i++) {
            $this->aColourwayOf($of, $of->title." شماره {$i}");
        }

        $total = Product::query()->listable()->count();
        $this->assertGreaterThan(100, $total, 'The walk needs more than one page to test anything.');

        $seen = [];
        $body = ['sort' => 'product_id_desc'];
        $pages = 0;

        do {
            $answer = $this->ask($body)->assertOk()->json();
            $pages++;

            $this->assertSame($pages, $answer['current_page']);
            $this->assertSame($total, $answer['total']);
            $this->assertLessThanOrEqual(100, count($answer['products']));

            $seen = array_merge($seen, array_column($answer['products'], 'page_unique'));

            $body = ['cursor' => $answer['next_cursor'], 'sort' => 'product_id_desc'];
        } while ($answer['next_cursor'] !== null && $pages < 10);

        $this->assertNull($answer['next_cursor'], 'The last page must end the walk.');
        $this->assertCount($total, $seen, 'The walk did not hand over every product.');
        $this->assertSame($seen, array_unique($seen), 'The walk handed a product over twice.');
    }

    /** Every page but the last holds exactly their hundred. */
    public function test_a_full_cursor_page_holds_exactly_a_hundred(): void
    {
        $of = $this->aProduct();

        for ($i = 0; $i < 100; $i++) {
            $this->aColourwayOf($of, $of->title." شماره {$i}");
        }

        $body = $this->ask(['sort' => 'product_id_desc'])->assertOk()->json();

        $this->assertCount(100, $body['products']);
        $this->assertNotNull($body['next_cursor']);
        $this->assertIsString($body['next_cursor']);
        $this->assertSame(end($body['products'])['page_unique'], $body['next_cursor']);
    }

    /** `page`, `limit` and `size` are not sent with a cursor, and saying so beats guessing. */
    public function test_a_numbered_page_beside_a_cursor_sort_is_a_400(): void
    {
        foreach (['page' => 2, 'limit' => 50, 'size' => 50] as $key => $value) {
            $this->ask([$key => $value, 'sort' => 'product_id_desc'])
                ->assertStatus(400)
                ->assertJsonStructure(['error']);
        }
    }

    /** A cursor on a dated sort is a caller mixing the two shapes. */
    public function test_a_cursor_on_a_dated_sort_is_a_400(): void
    {
        $this->ask(['cursor' => '5', 'sort' => 'date_added_desc'])
            ->assertStatus(400)
            ->assertJsonStructure(['error']);
    }

    /** A cursor this feed did not mint is refused rather than silently restarting the crawl. */
    public function test_a_cursor_that_is_not_one_of_ours_is_a_400(): void
    {
        foreach ([5, 'abc', ''] as $cursor) {
            $this->ask(['cursor' => $cursor, 'sort' => 'product_id_desc'])
                ->assertStatus(400)
                ->assertJsonStructure(['error']);
        }
    }

    private function aProduct(): Product
    {
        return Product::query()->listable()->orderBy('id')->firstOrFail();
    }
}
