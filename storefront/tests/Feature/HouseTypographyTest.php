<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * No em dash anywhere a customer or a member of staff can read one.
 *
 * «این خط فاصله اضافه چک کن در کل ویکی پلاس و پنل ادمین هرجایی هست حذف بشه»,
 * photographed on `/admin/passwords`: «رمز خودت پرسیده می‌شود — تا اگر…».
 *
 * It is a writing habit rather than a bug, and that is exactly why it needs a
 * test. Persian prose does not break a sentence with «—»; the pause is «،» or
 * «؛» or «:», and an aside is «()». Twenty-six separate strings across the
 * shop and the panel had one, and nothing anywhere would have noticed the
 * twenty-seventh.
 *
 * **Asserted against what renders, not against the source.** There are 424 of
 * these characters in `resources/views` and almost every one is in a Blade
 * comment, which never reaches a browser — a test that grepped the files would
 * fail on prose nobody reads and would still miss a string built in
 * JavaScript. So this renders the page and reads it the way a person does,
 * with the scripts, the styles and the HTML comments taken out first.
 *
 * The en dash «–» is checked with it. It is not used here either, and it is
 * the character somebody reaches for once the em dash is gone.
 */
class HouseTypographyTest extends TestCase
{
    use RefreshDatabase;

    /** What a person can actually read on the page. */
    private function visibleText(string $html): string
    {
        foreach (['~<script\b[^>]*>.*?</script>~is',
            '~<style\b[^>]*>.*?</style>~is',
            '~<!--.*?-->~s'] as $pattern) {
            $html = preg_replace($pattern, ' ', $html);
        }

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertNoDash(string $html, string $where): void
    {
        $text = $this->visibleText($html);

        if (preg_match('~(.{0,60})([—–])(.{0,60})~u', $text, $m) !== 1) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail(sprintf(
            "%s prints «%s».\n…%s…",
            $where,
            $m[2],
            trim(preg_replace('~\s+~u', ' ', $m[1].$m[2].$m[3]))
        ));
    }

    /**
     * The shop's pages that answer without an account. The ones behind a
     * customer sign-in — the wishlist, the order history — are read by the
     * panel half of this test through the same views' own materials.
     *
     * @return list<array{0: string}>
     */
    public static function shopPages(): array
    {
        return [['/'], ['/products'], ['/cart'], ['/account/enter'],
            ['/about'], ['/contact'], ['/size-guide'], ['/faq'], ['/terms'],
            ['/privacy'], ['/wholesale'], ['/franchise']];
    }

    #[DataProvider('shopPages')]
    public function test_no_shop_page_prints_a_dash(string $path): void
    {
        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->assertNoDash(
            $this->get($path)->assertOk()->getContent(),
            $path
        );
    }

    /** @return list<array{0: string}> */
    public static function panelPages(): array
    {
        return [['/admin'], ['/admin/orders'], ['/admin/inbox'], ['/admin/discounts'],
            ['/admin/catalogue'], ['/admin/front-page'], ['/admin/inventory'],
            ['/admin/pricing'], ['/admin/vendors'], ['/admin/settlements'],
            ['/admin/commissions'], ['/admin/reports'], ['/admin/reports/platform'],
            ['/admin/enquiries'], ['/admin/fulfilment'], ['/admin/staff'],
            ['/admin/passwords'], ['/admin/settings']];
    }

    #[DataProvider('panelPages')]
    public function test_no_panel_screen_prints_a_dash(string $path): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $owner = User::create([
            'name' => 'مالک',
            'email' => 'owner@vikyplus.test',
            'password' => 'secret',
        ]);
        $owner->roles()->attach(Role::where('slug', Role::OWNER)->firstOrFail());

        $this->assertNoDash(
            $this->actingAs($owner, 'web')->get($path)->assertOk()->getContent(),
            $path
        );
    }
}
