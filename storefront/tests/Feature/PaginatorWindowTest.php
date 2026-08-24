<?php

namespace Tests\Feature;

use App\Http\Controllers\ShopController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\View;
use ReflectionClass;
use Tests\TestCase;

/**
 * The paginator's window.
 *
 * Laravel keeps every page number until there are fifteen of them. That was
 * invisible while the shop had two pages and became the loudest thing on the
 * listing the day the Basalam stall arrived: a hundred and forty-three shoes
 * at twelve to a page is thirteen pages, and thirteen tiles wrapped into three
 * rows under the last card on a phone — «این چه وضع افتضاحیه پایین فروشگاه».
 *
 * Two things answer that and this holds both. The page is longer, so there are
 * fewer of them; and the window is ours now and bounded — first, last, the
 * current page and one neighbour either side, with the neighbours dropped
 * again on a phone. The failure it prevents is silent: nothing errors, the
 * page simply grows a paragraph of numbers, and it grows again with every
 * twelve shoes added.
 */
class PaginatorWindowTest extends TestCase
{
    private function render(int $current, int $pages, int $perPage = 24): string
    {
        $paginator = new LengthAwarePaginator(
            items: array_fill(0, $perPage, 'shoe'),
            total: $pages * $perPage,
            perPage: $perPage,
            currentPage: $current,
        );
        $paginator->setPath('/products');

        return View::make('pagination.vikyplus', [
            'paginator' => $paginator,
            'elements' => [],
        ])->render();
    }

    /**
     * What one of the two screens actually shows, in order, arrows included —
     * `.vp-pages` is `direction: ltr`, so the markup's order is the order on
     * the screen. `is-near` is display:none above a phone's width and
     * `is-gap-phone` is display:none below it, so reading the markup with
     * those two rules applied is reading the rendered page.
     *
     * @return list<string>
     */
    private function cells(string $html, string $on): array
    {
        preg_match_all('/<(?:a|span)[^>]*class="vp-page([^"]*)"[^>]*>(.*?)<\/(?:a|span)>/su', $html, $found, PREG_SET_ORDER);

        $cells = [];

        foreach ($found as [, $classes, $inner]) {
            $hidden = $on === 'phone'
                ? str_contains($classes, 'is-near')
                : str_contains($classes, 'is-gap-phone');

            if ($hidden) {
                continue;
            }

            $cells[] = match (true) {
                str_contains($inner, 'fa-chevron-left') => '‹',
                str_contains($inner, 'fa-chevron-right') => '›',
                default => trim(strip_tags($inner)),
            };
        }

        return $cells;
    }

    /**
     * The same list with the arrows taken back out, for the assertions that
     * are about which pages are named rather than how wide the row is.
     *
     * @return list<string>
     */
    private function numbers(string $html, string $on): array
    {
        return array_values(array_filter(
            $this->cells($html, $on),
            fn ($cell) => $cell !== '‹' && $cell !== '›',
        ));
    }

    public function test_neither_screen_is_given_more_cells_than_it_can_hold(): void
    {
        foreach (range(2, 30) as $pages) {
            foreach (range(1, $pages) as $current) {
                $html = $this->render($current, $pages);

                // A cell is 38px with an 8px gap and the listing gives the
                // paginator 336px on a 360px screen, so seven cells fit and an
                // eighth does not. Desktop has room for nine. Neither number is
                // a guess — both are measured, and both are what these counts
                // stand for. Arrows are cells too, which is most of why they go
                // when nothing is missing.
                $this->assertLessThanOrEqual(7, count($this->cells($html, 'phone')), "Phone, page {$current} of {$pages}.");
                $this->assertLessThanOrEqual(9, count($this->cells($html, 'desktop')), "Desktop, page {$current} of {$pages}.");
            }
        }
    }

    public function test_the_numbers_ascend_left_to_right_on_a_page_that_does_not(): void
    {
        // «ریاضی از چپ به راسته». The row is `direction: ltr`, so the markup's
        // order is the screen's order and this assertion means what it says.
        $css = file_get_contents(public_path('assets/css/tweaks.css'));

        $this->assertMatchesRegularExpression('/\.vp-pages \{[^}]*direction: ltr;/s', $css);

        // Previous points left and next points right, which is the way the
        // numbers now run rather than the way the page does.
        $cells = $this->cells($this->render(7, 13), 'desktop');

        $this->assertSame('‹', $cells[0]);
        $this->assertSame('›', $cells[count($cells) - 1]);
    }

    public function test_a_listing_that_fits_prints_every_page_and_no_arrows(): void
    {
        // The shop is six pages — twenty-four to a page over a hundred and
        // forty-three shoes — so this is the case the storefront is in. With
        // every page named the arrows say nothing the numbers beside them do
        // not: «وقتی ما همه ۶ تارو داریم چرا دیگه فلش ۲ طرف باشه؟»
        foreach (range(1, 6) as $current) {
            $html = $this->render($current, 6);
            $all = ['۱', '۲', '۳', '۴', '۵', '۶'];

            $this->assertSame($all, $this->cells($html, 'desktop'), "Desktop, standing on {$current}.");
            $this->assertSame($all, $this->cells($html, 'phone'), "Phone, standing on {$current}.");
        }

        $this->assertStringNotContainsString('is-near', $this->render(3, 6), 'Six pages hide nothing from the phone.');
        $this->assertStringNotContainsString('fa-chevron', $this->render(3, 6), 'And spend nothing on arrows.');
    }

    public function test_the_eighth_page_is_where_the_window_starts(): void
    {
        // Seven numbers is 314px and fits; eight is 360px and does not, so the
        // eighth page is where the arrows and the ellipsis come back.
        $this->assertSame(['۱', '۲', '۳', '۴', '۵', '۶', '۷'], $this->cells($this->render(2, 7), 'phone'));

        $this->assertSame(['‹', '۱', '۲', '۳', '…', '۸', '›'], $this->cells($this->render(2, 8), 'desktop'));
        $this->assertSame(['‹', '۱', '۲', '…', '۸', '›'], $this->cells($this->render(2, 8), 'phone'));
    }

    public function test_the_window_is_the_first_the_last_and_the_current_page(): void
    {
        $html = $this->render(7, 13);

        $this->assertSame(['۱', '…', '۶', '۷', '۸', '…', '۱۳'], $this->numbers($html, 'desktop'));
        $this->assertSame(['۱', '…', '۷', '…', '۱۳'], $this->numbers($html, 'phone'));
    }

    public function test_a_page_next_to_an_end_folds_into_it_rather_than_printing_twice(): void
    {
        // Page one would otherwise be both "first" and "current - 1".
        $this->assertSame(['۱', '۲', '…', '۱۳'], $this->numbers($this->render(1, 13), 'desktop'));
        $this->assertSame(['۱', '…', '۱۲', '۱۳'], $this->numbers($this->render(13, 13), 'desktop'));
    }

    public function test_a_short_paginator_prints_every_page_with_no_gap(): void
    {
        $this->assertSame(['۱', '۲', '۳'], $this->cells($this->render(2, 3), 'desktop'));
        $this->assertSame(['۱', '۲', '۳'], $this->cells($this->render(2, 3), 'phone'));
        $this->assertSame([], $this->cells($this->render(1, 1), 'desktop'), 'One page is no paginator at all.');
    }

    public function test_a_gap_the_phone_alone_needs_is_drawn_for_the_phone_alone(): void
    {
        // Eight pages, standing on the third. The full window is ۱ ۲ ۳ ۴ … ۸,
        // so there is no skip between one and three; hide the neighbours and
        // there is one. Without the phone's own ellipsis the page would read
        // ۱ ۳ … ۸ and claim one and three are next to each other.
        $html = $this->render(3, 8);

        $this->assertSame(['۱', '۲', '۳', '۴', '…', '۸'], $this->numbers($html, 'desktop'));
        $this->assertSame(['۱', '…', '۳', '…', '۸'], $this->numbers($html, 'phone'));
    }

    public function test_a_gap_both_screens_have_is_drawn_once_and_serves_both(): void
    {
        // Standing in the middle of thirteen, ۱ … ۶ ۷ ۸ … ۱۳ skips on both
        // sides for everybody. A second ellipsis for the phone here would put
        // «… …» side by side once the six is hidden.
        $html = $this->render(7, 13);

        $this->assertStringNotContainsString('is-gap-phone', $html);
    }

    public function test_the_listing_holds_a_whole_number_of_rows_at_every_width(): void
    {
        $perPage = (new ReflectionClass(ShopController::class))->getConstant('PER_PAGE');

        // The grid is four across above 1200, three between, two on a phone.
        // Anything but a multiple of twelve leaves one of them a ragged row.
        $this->assertSame(0, $perPage % 12, "PER_PAGE is {$perPage}, which is not a whole number of rows at every width.");
    }

    public function test_the_stylesheet_hides_each_screen_what_the_other_one_gets(): void
    {
        $css = file_get_contents(public_path('assets/css/tweaks.css'));

        $this->assertMatchesRegularExpression(
            '/\.vp-page\.is-gap-phone \{\s*display: none;/',
            $css,
            'Without this the desktop gets an ellipsis it has the numbers for.',
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 575\.98px\) \{\s*\.vp-page\.is-near \{\s*display: none;\s*\}\s*\.vp-page\.is-gap-phone \{\s*display: grid;/',
            $css,
            'Without these the phone gets nine cells and wraps.',
        );
    }
}
