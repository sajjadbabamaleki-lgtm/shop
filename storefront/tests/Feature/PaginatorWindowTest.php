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
     * What one of the two screens actually shows, in order — the numbers and
     * the ellipses, with the arrows left out. `is-near` is display:none above
     * a phone's width and `is-gap-phone` is display:none below it, so reading
     * the markup with those rules applied is reading the rendered page.
     *
     * @return list<string>
     */
    private function cells(string $html, string $on): array
    {
        preg_match_all('/<(?:a|span)[^>]*class="vp-page([^"]*)"[^>]*>([^<]*)</u', $html, $found, PREG_SET_ORDER);

        $cells = [];

        foreach ($found as [, $classes, $text]) {
            $hidden = $on === 'phone'
                ? str_contains($classes, 'is-near')
                : str_contains($classes, 'is-gap-phone');

            if (! $hidden && trim($text) !== '') {
                $cells[] = trim($text);
            }
        }

        return $cells;
    }

    public function test_neither_screen_is_given_more_cells_than_it_can_hold(): void
    {
        foreach (range(2, 30) as $pages) {
            foreach (range(1, $pages) as $current) {
                $html = $this->render($current, $pages);

                // A phone cell is 34px with a 6px gap and the listing gives the
                // paginator 336px on a 360px screen, so eight cells fit and two
                // of them are the arrows. Desktop keeps 38px and has room for
                // nine. Neither number is a guess — both are measured, and both
                // are what these two counts stand for.
                $this->assertLessThanOrEqual(6, count($this->cells($html, 'phone')), "Phone, page {$current} of {$pages}.");
                $this->assertLessThanOrEqual(7, count($this->cells($html, 'desktop')), "Desktop, page {$current} of {$pages}.");
            }
        }
    }

    public function test_six_pages_are_all_printed_and_nothing_is_hidden(): void
    {
        // The listing is twenty-four to a page and the shop holds a hundred and
        // forty-three shoes, so this is the case the storefront is actually in.
        foreach (range(1, 6) as $current) {
            $html = $this->render($current, 6);
            $all = ['۱', '۲', '۳', '۴', '۵', '۶'];

            $this->assertSame($all, $this->cells($html, 'desktop'), "Desktop, standing on {$current}.");
            $this->assertSame($all, $this->cells($html, 'phone'), "Phone, standing on {$current}.");
        }

        $this->assertStringNotContainsString('is-near', $this->render(3, 6), 'Six pages hide nothing from the phone.');
    }

    public function test_the_seventh_page_is_where_the_window_starts(): void
    {
        // Seven numbers and two arrows is nine cells, 354px, and the narrowest
        // phone has 336px. So seven pages is one too many to print whole.
        $this->assertSame(['۱', '۲', '۳', '…', '۷'], $this->cells($this->render(2, 7), 'desktop'));
        $this->assertSame(['۱', '۲', '…', '۷'], $this->cells($this->render(2, 7), 'phone'));
    }

    public function test_the_window_is_the_first_the_last_and_the_current_page(): void
    {
        $html = $this->render(7, 13);

        $this->assertSame(['۱', '…', '۶', '۷', '۸', '…', '۱۳'], $this->cells($html, 'desktop'));
        $this->assertSame(['۱', '…', '۷', '…', '۱۳'], $this->cells($html, 'phone'));
    }

    public function test_a_page_next_to_an_end_folds_into_it_rather_than_printing_twice(): void
    {
        // Page one would otherwise be both "first" and "current - 1".
        $this->assertSame(['۱', '۲', '…', '۱۳'], $this->cells($this->render(1, 13), 'desktop'));
        $this->assertSame(['۱', '…', '۱۲', '۱۳'], $this->cells($this->render(13, 13), 'desktop'));
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

        $this->assertSame(['۱', '۲', '۳', '۴', '…', '۸'], $this->cells($html, 'desktop'));
        $this->assertSame(['۱', '…', '۳', '…', '۸'], $this->cells($html, 'phone'));
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
            '/\.vp-page \{\s*min-width: 34px;\s*padding-inline: 6px;\s*\}\s*\.vp-page\.is-near \{\s*display: none;\s*\}\s*\.vp-page\.is-gap-phone \{\s*display: grid;/',
            $css,
            'Without these the phone gets nine cells and wraps.',
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 575\.98px\) \{.*?\.vp-pages \{\s*gap: 6px;/s',
            $css,
            'Eight cells only fit at 34px if the gap comes down with them.',
        );
    }
}
