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
        // The template's own home link, the demo page this storefront was cut
        // from, and the file the static preview is actually served as. All
        // three are this site's front page.
        //
        // `index.html` is the one the brand mark links to, in all three places
        // it appears — and until it was listed here, clicking the shop's own
        // logo went to '#'. A link that is the most obvious thing on the page
        // is the last one anybody thinks to test.
        'electronics-shop.html' => 'home',
        'shoe-shop.html' => 'home',
        'index.html' => 'home',

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
        // The template's account link, in the top bar and at the foot of the
        // phone drawer. It went to '#' until customers had accounts.
        'my-account.html' => 'account.enter',
        // Its own filename so that only the footer's «فروشنده شوید» points
        // here — every other footer item still shares contact.html.
        'vendor-register.html' => 'vendors.apply',
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
    | The mark beside a category in the phone drawer
    |--------------------------------------------------------------------------
    |
    | The drawer showed each category's own photograph. At 32px a photograph of
    | a shoe is a brown smudge, and eight of them down one edge of the panel is
    | noise rather than navigation — so the client asked for icons, and these
    | are them.
    |
    | All eight were drawn for this. The template does ship category icons, and
    | half of them nearly fit — but it sells five kinds of footwear against the
    | template's one shoe, and the template's are multi-coloured line art that
    | would have arrived in three different reds beside whatever was drawn to
    | fill the gaps. One hand, one weight, one gold.
    |
    | The gold is baked into the file rather than applied in CSS. Painting an
    | SVG from a stylesheet means `mask-image`, and a `url()` in a custom
    | property resolves against **the stylesheet that reads it**, not the page —
    | so `assets/img/icon/x.svg` set in the markup became
    | `/assets/css/assets/img/icon/x.svg` and every one of the eight 404'd.
    |
    | Keyed by category slug, with a fallback: a category added later gets a
    | plain bag rather than no mark at all, and adding a line here is what gives
    | it its own. This lives in config rather than on the row because nothing
    | can edit a category yet; it moves onto `categories` the day something can.
    |
    */

    'category_icons' => [
        'default' => 'assets/img/icon/vp-cat-bagset.svg',

        'majlesi' => 'assets/img/icon/vp-cat-heel.svg',
        'sneaker' => 'assets/img/icon/vp-cat-sneaker.svg',
        'college' => 'assets/img/icon/vp-cat-college.svg',
        'sandal' => 'assets/img/icon/vp-cat-sandal.svg',
        'boot' => 'assets/img/icon/vp-cat-boot.svg',
        'bag-set' => 'assets/img/icon/vp-cat-bagset.svg',
        'accessory' => 'assets/img/icon/vp-cat-watch.svg',
        'sport-set' => 'assets/img/icon/vp-cat-sport.svg',
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
         | The product page's colourway strip.
         |
         | The reference the client sent shows a row of square photographs under
         | the shot, one per colour of the shoe, with the chosen one lit. We hold
         | **one photograph per product and no colour on any variant**, so there
         | is nothing to build that row from.
         |
         | The client asked for it anyway, in as many words — «وقتی نمیتونی عکس
         | رنگهای مختلف کفشو بزاری یه چیز پیشفرض تکراری بزار تا ما فعلا ui
         | تکمیل کنیم» — so the row repeats the product's own photograph this
         | many times. It is a stand-in for a layout, not a claim about the
         | shoe: nothing under it is selectable and no colour is named.
         |
         | **Set this to 0 the day real colourway photographs arrive**, and the
         | row falls back to the product's actual media. It is here rather than
         | in the view so that removing it is one number in one file.
         */
        'colorway_shots' => 5,

        /*
         | The star on the sale card.
         |
         | The client's reference card carries a rating and nothing in this
         | catalogue is rated — there is no review table and no column for it.
         | This is the number the star shows until there is, the same call as
         | the colourway strip above: «یه چیز پیشفرض تکراری بزار تا ما فعلا ui
         | تکمیل کنیم».
         |
         | **Set this to 0 the day reviews exist** and the star disappears from
         | the card entirely rather than showing a made-up number beside a real
         | one. The static preview carries the same value in
         | theme/make-rtl-page.js as DEAL_RATING; the two have to agree or
         | check-parity.js will say so.
         */
        'rating' => 4,

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
