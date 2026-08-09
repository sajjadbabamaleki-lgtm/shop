<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_the_front_page_renders(): void
    {
        $this->get('/')->assertOk()->assertViewIs('home');
    }

    /**
     * Every section the home page is composed of, named by a class the ported
     * markup carries. A partial that stops being included, or an include that
     * silently resolves to nothing, drops a whole band of the page — this is
     * the cheapest thing that notices.
     */
    public function test_every_section_is_on_the_page(): void
    {
        $this->get('/')->assertSeeInOrder([
            'class="th-header',          // the dark strip and the header island
            'class="th-hero-wrapper',    // the hero deck
            'class="feature-area2',      // the category tiles and the trust row
            'vp-ladder-area',            // «حراج پله‌ای ویکی پلاس»
            'vp-best-section',           // «پرفروش‌ترین‌ها»
            'vp-daily-deal-section',     // «قبل از تمام شدن بخرش!»
            'vp-brands-section',         // «برندهای موجود»
            'class="footer-wrapper',
        ], false);
    }

    /**
     * The four sections taken off the page at the client's request. They are
     * cut in theme/make-rtl-page.js, upstream of the port; this catches a
     * regenerated page quietly putting them back.
     */
    public function test_the_dropped_sections_stay_off_the_page(): void
    {
        $response = $this->get('/');

        foreach (['نظرات مشتریان', 'محصولات منتخب', 'تازه‌ترین مطالب', 'اینستاگرام'] as $heading) {
            $response->assertDontSee($heading, false);
        }
    }

    /**
     * The ported markup links to the template's demo filenames. page_url()
     * sends the ones with no route behind them to '#', so no link on the page
     * can walk a visitor into a 404.
     */
    public function test_no_link_points_at_a_template_page(): void
    {
        $this->get('/')->assertDontSee('href="shop.html"', false);

        $this->assertSame('#', page_url('shop.html'));
        $this->assertSame(route('home'), page_url('shoe-shop.html'));
    }
}
