<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * The content pages: «درباره ما», «تماس با ما», «راهنمای سایز», «حراج پله‌ای»,
 * «سوالات متداول», «قوانین و مقررات» and «حریم خصوصی».
 *
 * Seven pages of copy and no database. They exist because the footer has been
 * linking to them since the template arrived — 21 of its 47 links went to '#'
 * — and because §2 of the specification asks for content pages and customer
 * support features in the same breath as the cart and the checkout.
 *
 * **Nothing on them is invented.** The delivery charge quoted in the FAQ is
 * `storefront.checkout`, the same number the checkout adds up with; the
 * cancellation rule is the one `Order::isCancellable()` applies; the payment
 * method is the one `CheckoutController` really offers, which today is the
 * courier at the door. A content page that describes a shop the software is
 * not is the same lie as a footer link that goes nowhere, told at more length.
 *
 * Registered inside the storefront's route closure like every other page, so a
 * visitor browsing /shiraz stays in /shiraz when they read the size guide.
 */
class PageController extends Controller
{
    /**
     * Path segment → route name, and the view under `pages/` is the path.
     *
     * One list, read by the routes file, so a page cannot be routed without a
     * view or given a name the footer's map does not know.
     *
     * @var array<string, string>
     */
    public const PAGES = [
        'about' => 'about',
        'contact' => 'contact',
        'size-guide' => 'size-guide',
        'stepped-sale' => 'stepped-sale',
        'faq' => 'faq',
        'terms' => 'terms',
        'privacy' => 'privacy',
    ];

    /**
     * Which page is a route default rather than a URL segment — the paths are
     * fixed, so there is nothing here for a visitor to put a value into.
     */
    public function __invoke(string $page): View
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        return view("pages.{$page}");
    }
}
