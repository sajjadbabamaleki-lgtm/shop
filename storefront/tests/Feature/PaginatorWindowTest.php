<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The paginator's window.
 *
 * Laravel keeps every page number until there are fifteen of them. That was
 * invisible while the shop had two pages and became the loudest thing on the
 * listing the day the Basalam stall arrived: a hundred and forty-three shoes
 * is thirteen pages, and thirteen tiles wrapped into three rows under the last
 * card on a phone — «این چه وضع افتضاحیه پایین فروشگاه».
 *
 * So the window is ours now, and it is bounded: first, last, the current page
 * and one neighbour either side. This holds the bound, because the failure it
 * prevents is silent — nothing errors, the page simply grows a paragraph of
 * numbers, and it grows again with every twelve shoes added.
 */
class PaginatorWindowTest extends TestCase
{
    /**
     * @return list<string> the numbers the paginator printed, in order
     */
    private function numbersOn(int $current, int $pages, int $perPage = 12): array
    {
        $paginator = new LengthAwarePaginator(
            items: array_fill(0, $perPage, 'shoe'),
            total: $pages * $perPage,
            perPage: $perPage,
            currentPage: $current,
        );
        $paginator->setPath('/products');

        $html = View::make('pagination.vikyplus', [
            'paginator' => $paginator,
            'elements' => [],
        ])->render();

        preg_match_all('/<(?:a|span)[^>]*class="vp-page[^"]*"[^>]*>([^<]*)</u', $html, $found);

        return array_values(array_filter(array_map('trim', $found[1]), fn ($cell) => $cell !== ''));
    }

    public function test_thirteen_pages_print_at_most_five_numbers_and_two_gaps(): void
    {
        foreach (range(1, 13) as $current) {
            $cells = $this->numbersOn($current, 13);

            $this->assertLessThanOrEqual(
                7,
                count($cells),
                "Page {$current} of 13 printed ".count($cells).' cells; seven is what 390px holds.',
            );
        }
    }

    public function test_the_window_is_the_first_the_last_and_the_current_page(): void
    {
        $this->assertSame(['۱', '۲', '۳', '…', '۱۳'], $this->numbersOn(2, 13));
        $this->assertSame(['۱', '…', '۶', '۷', '۸', '…', '۱۳'], $this->numbersOn(7, 13));
        $this->assertSame(['۱', '…', '۱۱', '۱۲', '۱۳'], $this->numbersOn(12, 13));
    }

    public function test_a_page_next_to_an_end_folds_into_it_rather_than_printing_twice(): void
    {
        // Current page 1 would otherwise be both "first" and "current - 1".
        $this->assertSame(['۱', '۲', '…', '۱۳'], $this->numbersOn(1, 13));
        $this->assertSame(['۱', '…', '۱۲', '۱۳'], $this->numbersOn(13, 13));
    }

    public function test_a_short_paginator_prints_every_page_with_no_gap(): void
    {
        $this->assertSame(['۱', '۲', '۳'], $this->numbersOn(2, 3));
        $this->assertSame([], $this->numbersOn(1, 1), 'One page is no paginator at all.');
    }

    public function test_the_phone_hides_the_neighbours_and_nothing_else(): void
    {
        $paginator = new LengthAwarePaginator(array_fill(0, 12, 'shoe'), 156, 12, 7);
        $paginator->setPath('/products');

        $html = View::make('pagination.vikyplus', ['paginator' => $paginator, 'elements' => []])->render();

        // Six and eight are the neighbours; one, seven and thirteen are not.
        $this->assertStringContainsString('is-near', $html);
        $this->assertSame(2, substr_count($html, 'is-near'));

        $css = file_get_contents(public_path('assets/css/tweaks.css'));
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 575\.98px\) \{\s*\.vp-page\.is-near \{\s*display: none;/',
            $css,
            'Without this rule the phone gets nine cells and wraps.',
        );
    }
}
