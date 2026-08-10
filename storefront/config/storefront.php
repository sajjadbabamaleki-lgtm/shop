<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Template pages
    |--------------------------------------------------------------------------
    |
    | The ported markup links to the ThemeForest demo's filenames. This maps
    | the ones that have a page behind them to a route name; page_url() sends
    | everything else to '#'. Add a line here when a page gets built — nothing
    | in the views needs to change.
    |
    */

    'pages' => [
        // The template's own home link, and the demo page this storefront was
        // cut from. Both are this site's front page.
        'electronics-shop.html' => 'home',
        'shoe-shop.html' => 'home',

        // The listing, under every name the template links to it by. Only
        // routes that need no parameters can be mapped here: page_url() takes
        // a filename and nothing else, so a product page — which needs to know
        // which product — is linked with storefront_route('product', $product)
        // from the view that has one.
        'shop.html' => 'shop',
        'shop-grid.html' => 'shop',
        'shop-list.html' => 'shop',
        'shop-grid-left-sidebar.html' => 'shop',
        'shop-grid-right-sidebar.html' => 'shop',
        'search-product.html' => 'search',
        'cart.html' => 'cart',
        'checkout.html' => 'checkout',
        'order-tracking.html' => 'orders.track',
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Delivery, flat and free over a threshold, in integer Rial like every
    | other price in this application.
    |
    | Both numbers are PLACEHOLDERS. Nobody has said what the shop charges to
    | deliver, and rather than leave the checkout unable to add up, it charges
    | 50,000 Toman and delivers free over 5,000,000. They are here, in one
    | place, so that the real numbers are one edit — and so that nobody
    | mistakes them for a decision that was made.
    |
    */

    'checkout' => [
        'shipping_flat' => 500_000,
        'free_shipping_above' => 50_000_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | Which hosts reach the main store. Every host that reaches this
    | application is a row in `branch_domains`; these are the ones seeded for
    | the central branch, so the site answers on its own name and on whatever
    | a developer happens to be using locally.
    |
    | Franchise subdomains are not here. A branch's hosts belong to the branch
    | and are created with it — nothing in the code may decide which host is
    | Shiraz (§34).
    |
    */

    'tenancy' => [
        'central_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'CENTRAL_HOSTS',
            'vikyplus.ir,www.vikyplus.ir,localhost,127.0.0.1'
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | The front page's editorial choices
    |--------------------------------------------------------------------------
    |
    | Which products get the hero deck and which one gets the daily-deal
    | banner are decisions somebody makes, not facts the catalogue can be
    | asked for. They are named by slug so the catalogue stays the authority
    | on everything else about them — photograph, name, price, stock.
    |
    | The hero runs its three twice: six slides over three photographs, so the
    | deck reads as a loop of three rather than six of anything.
    |
    */

    'hero' => [
        'products' => ['new-balance-530', 'jordan-one-air', 'golden-goose'],
        'repeat' => 2,
    ],

    'daily_deal' => [
        'product' => 'new-balance-530',
        // The template's countdown widget reads this one attribute, in the
        // m/d/Y it was written in.
        'ends_at' => '08/08/2026',
    ],

    /*
    |--------------------------------------------------------------------------
    | The stepped sale
    |--------------------------------------------------------------------------
    |
    | «حراج پله‌ای» — a price that falls a step a week until the thing sells.
    | This is the policy, not the catalogue: it says what the steps are and
    | which one is running. A product is in the sale by carrying a promotion
    | whose cut is the live step's, which is what makes the badge on a card and
    | the board above it agree without either being told about the other.
    |
    | `live` is 1-based. Steps before it have run, the one at it is running,
    | the ones after it have not been reached.
    |
    */

    'ladder' => [
        'live' => 2,

        'steps' => [
            ['name' => 'پله اول', 'cut' => 15, 'when' => 'هفته اول'],
            ['name' => 'پله دوم', 'cut' => 30, 'when' => 'هفته دوم'],
            ['name' => 'پله سوم', 'cut' => 45, 'when' => 'هفته سوم'],
            ['name' => 'پله چهارم', 'cut' => 60, 'when' => 'هفته چهارم'],
            ['name' => 'پله نهایی', 'cut' => 70, 'when' => 'پس از هفته چهارم'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admitted placeholders
    |--------------------------------------------------------------------------
    |
    | Content on the page that is not real and is not pretending to be. It sits
    | here, in one place, rather than in the database — seeding an invented
    | number into the catalogue would make it indistinguishable from a measured
    | one, and the whole point of this block is that the difference stays
    | visible. Everything here is waiting on the client for its real value.
    |
    */

    'placeholders' => [

        /*
         | The brand strip.
         |
         | Each tile carries three photographs and a count. Neither is real:
         | we hold one product photograph per brand and the tile wants three,
         | so the client asked for the eight category tiles from the top of the
         | page to stand in — twelve slots against eight photographs, arranged
         | so no two tiles open on the same lead image. The counts are invented
         | outright; nothing in the catalogue adds up to them, and seeding
         | inventory until it did would be inventing stock, not counting it.
         |
         | Keyed by brand slug. A brand with no entry here shows its own three
         | photographs and its real count, which is what should happen as each
         | brand's assets arrive.
         */
        'brand_strip' => [
            'nike' => ['mosaic' => ['sneaker', 'sport-set', 'sandal'], 'stock' => 42],
            'jordan' => ['mosaic' => ['boot', 'college', 'accessory'], 'stock' => 28],
            'new-balance' => ['mosaic' => ['college', 'bag-set', 'sneaker'], 'stock' => 35],
            'golden-goose' => ['mosaic' => ['majlesi', 'accessory', 'sport-set'], 'stock' => 19],
        ],

        /*
         | The best sellers.
         |
         | The six cards are category photographs, not products — the client's
         | own call, since a category photograph is not a SKU. The name and
         | price on each strip are a real product's, cycled through the five
         | we hold, and deliberately do not describe the photograph above
         | them: «put a shoe name and a price in the strip, and don't worry
         | that it doesn't match the photograph».
         |
         | So this is the pairing rule, and it is a placeholder in its
         | entirety: the first six categories, in the row's own order, each
         | taking the next product off this list and starting over when it
         | runs out. Real best sellers are products, and the day they exist
         | this key and the pairing in HomeController go together.
         */
        'best_sellers' => [
            'tiles' => 6,
            'priced_from' => [
                'new-balance-530',
                'jordan-one-air',
                'golden-goose',
                'nike-v2k-run',
                'on-cloudtilt',
            ],
        ],
    ],

];
