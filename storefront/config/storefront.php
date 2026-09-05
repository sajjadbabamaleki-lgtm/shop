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

    /*
    |--------------------------------------------------------------------------
    | «تاس شانس» — the dice game
    |--------------------------------------------------------------------------
    |
    | A band on the front page: one throw of two dice per visitor, and a double
    | six wins a discount code.
    |
    | **The odds are the dice's own: 1 in 36, about 3 people in 100.** That is
    | what «جفت شیش» means and it is not adjustable here on purpose — a game
    | with a hidden thumb on the scale is a different product, and if the shop
    | wants most people to win, the honest version of that is easier odds
    | printed on the card («هر جفتی برنده است»), not dice that lie.
    |
    | Every number below is the shop's to set. `percent` is whole percent.
    | `hours` is how long a winner's code lives — the card says it, so the two
    | come from the same place. The two amounts are in **Rial**, like every
    | other amount in this application; `max_discount_rial` of 0 means no
    | ceiling.
    |
    | `tries` is how many throws one person gets — «کاربر هم ۲ شانس داشته باشه
    | نه یک شانس». It is enforced by the unique key on
    | (branch, player, attempt), not by a count in PHP, so two taps arriving
    | together cannot buy a third. Two throws at 1 in 36 each is about 5 or 6
    | winners in every hundred players, against 3 for one throw. The band's own
    | footnote reads this number, so raising it changes what the page promises
    | in the same breath.
    |
    | **`rig_attempt` makes a throw win on purpose, and it is not a toy.**
    | Set to a throw number, every player's throw of that number comes up a
    | double six — so with 2, *everybody* who throws twice wins whatever
    | `percent` is. That is the shop paying it on effectively every order
    | somebody bothers to play for, not on the 5 or 6 in a hundred the honest
    | dice cost. (It was 30 and is 10 now, which makes that cheaper and does
    | not make it free.) It is here
    | because it was asked for in those words — «فعلا دفعه دوم تستو جفت شیش
    | بزار برای همه» — and «فعلا» is the important word: it is meant to come
    | back off.
    |
    | It reads `GAME_DICE_RIG` from the environment, so it can be turned off on
    | the Liara panel without waiting for a deploy: set GAME_DICE_RIG=0 and
    | restart. Null or 0 means honest dice.
    |
    | `enabled` switches the whole band off, markup included. A game nobody is
    | running should not be a button that does nothing.
    |
    */

    'game' => [
        'enabled' => env('GAME_DICE', true),
        'tries' => 2,
        'rig_attempt' => (int) env('GAME_DICE_RIG', 2) ?: null,
        'percent' => 10,
        'hours' => 24,
        'min_subtotal_rial' => 0,
        'max_discount_rial' => 0,
    ],

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

        /*
         | The content pages.
         |
         | Until these were built, `contact.html`, `about.html` and
         | `course.html` were unmapped and the footer sent **21 of its 47
         | links** to '#' — «راهنمای سایز», «قوانین و مقررات», «حریم خصوصی»,
         | «سوالات متداول», «تماس با ما» and a dozen more. The footer was
         | promising the shop had pages it did not have.
         |
         | `course.html` is the template's own filename for a page we do not
         | have and never will; the footer items that shared it have been
         | pointed at their real filenames in theme/make-rtl-page.js. It stays
         | mapped to the size guide because that is the closest thing to what
         | it was standing in for, and an old link is better resolved than
         | broken.
         */
        'about.html' => 'about',
        'contact.html' => 'contact',
        'faq.html' => 'faq',
        'size-guide.html' => 'size-guide',
        'course.html' => 'size-guide',

        /*
         | «حراج پله‌ای» — the shop's own way of selling, which had no page
         | explaining it and whose links landed on the plain shop listing.
         | «توجه داشته باشید که تخفیف‌دارها با حراج پله‌ای فرق می‌کنه»: a
         | discounted item and a stepped-sale item are two different things,
         | and sending one link to the other's page is what made them look
         | like one.
         */
        'stepped-sale.html' => 'stepped-sale',

        /*
         | «پشتیبانی». The footer has said the word since the template arrived
         | and pointed it at «تماس با ما», which had no way to say anything to
         | anybody. It is a form now, into the same inbox as the other two
         | enquiry pages.
         */
        'support.html' => 'support',
        'terms.html' => 'terms',
        'privacy.html' => 'privacy',

        /*
         | The two things the shop advertises and had no page for.
         |
         | «خرید تکی و عمده» is on the front page's trust row and in the
         | footer's strap line; the branch network is the largest thing in
         | this application. Neither had anywhere for somebody to ask.
         | Filenames of their own, because the footer items that carry them
         | were pointing at contact.html and vendor-register.html — near
         | enough to look right and not the page anybody wanted.
         */
        'wholesale.html' => 'wholesale',
        'franchise.html' => 'franchise',

        /*
         | «مقالات» — «هیچ جایی برای مقالات در سایت نداریم».
         |
         | A filename of its own so that only the two footer slots that name it
         | point there. `blog.html` rather than `articles.html` because that is
         | what the base markup calls a page of writing, and a name a reader
         | recognises is worth more here than one that matches the route.
         */
        'blog.html' => 'articles',

        /*
         | «علاقه‌مندی‌ها», which now exists.
         |
         | The footer item was given a different label for a while — the slot
         | was there, the feature was not, and a link called «علاقه‌مندی‌ها»
         | landing on the size guide is a wrong answer rather than no answer.
         | `/account/wishlist` was built since, so the label is back and this
         | is the line that was promised.
         |
         | `account.wishlist`, not `wishlist`: the route sits behind
         | `auth:customer` and `redirectGuestsTo` chooses between the two
         | sign-ins by matching `*account*` on the route name, so a guest
         | tapping this reaches the shopper's form and not the staff one.
         */
        'wishlist.html' => 'account.wishlist',
    ],

    /*
    |--------------------------------------------------------------------------
    | How to reach the shop
    |--------------------------------------------------------------------------
    |
    | The address, the telephone and the WhatsApp number, in one place, because
    | the contact page and the footer must never disagree about where the shop
    | is. **These are the client's own details, not placeholders**: the address
    | and the telephone came off the footer screenshot they sent, and the
    | WhatsApp number is the one behind the floating button on every page.
    |
    | The footer keeps its own copy of the address and the telephone — it is
    | generated markup, ported from the static preview by theme/make-blade.js,
    | so it cannot read this file. `ContentPagesTest` asserts the two agree, so
    | correcting one and forgetting the other fails the suite rather than
    | putting two addresses on one site.
    |
    */

    'contact' => [
        'address' => 'تهران، سعدی شمالی، روبه‌روی بانک ملی، پلاک ۵۶۵',
        'phone' => '021-3398-3125',
        'phone_href' => 'tel:02133983125',
        /*
         | WhatsApp, which is the shop's support channel — the floating button
         | on every page dials it. The client gave this number for it by name:
         | «۰۹۳۶۶۶۵۹۲۲۴ این شماره هم در پشتیبانی واتسپ». It replaces
         | ۰۹۹۱۸۹۰۵۹۹۳, which was the number before; if both were meant to
         | answer, the second one comes back here as its own pair.
         */
        'whatsapp' => '۰۹۳۶۶۶۵۹۲۲۴',
        'whatsapp_href' => 'https://wa.me/989366659224',

        /*
         | Wholesale is a person, not the shop's switchboard.
         |
         | «در قسمت فروش عمده حتماً شماره تلفن آقا محمدرضا ذکر بشه», and the
         | number and surname came separately: «۰۹۱۲۳۵۴۴۴۳۵ میرهاشمی». Only
         | the surname was given with the number, so only the surname is
         | printed — a first name guessed onto a real person's page is worse
         | than a short one.
         */
        'wholesale_name' => 'آقای میرهاشمی',
        'wholesale_phone' => '۰۹۱۲۳۵۴۴۴۳۵',
        'wholesale_phone_href' => 'tel:09123544435',

        /*
         | When somebody answers.
         |
         | A PLACEHOLDER. Nobody has said what the shop's hours are, and a
         | contact page with no hours on it invites the phone call at eleven
         | at night that nobody picks up. These are ordinary Tehran retail
         | hours, written down so they can be corrected in one edit — and
         | said here rather than in the view so that nobody mistakes them for
         | something the client told us.
         */
        'hours' => 'شنبه تا پنجشنبه، ۱۰ تا ۲۰',
    ],

    /*
    |--------------------------------------------------------------------------
    | The content pages
    |--------------------------------------------------------------------------
    |
    | Everything on «قوانین و مقررات», «حریم خصوصی», «سوالات متداول» and the
    | rest is written to describe what this application actually does — the
    | payment method the checkout really offers, the cancellation rule
    | `Order::isCancellable()` really applies, the data the tables really hold.
    | The two numbers below are the exceptions: they are business decisions
    | nobody has made yet, so they sit here where the client can see them.
    |
    | ⚠️ **The legal text on those two pages is a draft and has not been
    | reviewed by anybody qualified.** It is accurate about the software; it is
    | not a substitute for somebody who knows Iranian consumer law reading it.
    | Say so when handing this over.
    |
    */

    'content' => [
        /*
         | How long somebody has to ask for an exchange, in days.
         |
         | **Three, and no longer a placeholder** — «ظرف مدت ۳ روز بعد از
         | تحویل کالا مشتری باید کالا رو مرجوع کنه». It was seven, which was
         | the usual Iranian retail answer chosen because nobody had said.
         |
         | Still not *enforced* anywhere: there is no returns flow in this
         | application — no route, no table, no order status — so this is what
         | the pages tell somebody to telephone about. When returns get built,
         | this becomes the window the code checks.
         */
        'exchange_days' => 3,

        /*
         | The two halves of «کی می‌رسد», which are different questions and
         | were being answered as one.
         |
         | `dispatch_days` is what the shop controls: how long after an order
         | before it leaves. `delivery_days_*` is the carrier's part, and it
         | depends on how far the address is from Tehran — «بستگی به فاصله آن
         | شهر تا تهران داره و از ۳ تا ۵ روز متناوب هست. کالا بعد از سفارش
         | ظرف مدت سه روز ارسال می‌شه».
         |
         | Said as a range and never as one number, because the shop cannot
         | promise the second half and a single figure would read as a promise.
         */
        'dispatch_days' => 3,
        'delivery_days_min' => 3,
        'delivery_days_max' => 5,

        /*
         | Whether anybody may buy in person, and on what terms.
         |
         | «تاکید بشه که فروش حضوری فقط بابت فروش عمده هست و در فروش تکی اصلاً
         | مورد حضوری نداریم». The «درباره ما» page used to invite people to
         | come and try shoes on, which is the opposite of this.
         */
        'in_person_is_wholesale_only' => true,

        /*
         | The date on the foot of the two legal pages, ISO in, Jalali out
         | through fa_date(). Move it when the text changes and not before —
         | a date that says today on a page nobody has edited for a year is
         | worse than no date.
         */
        'legal_updated' => '2026-08-15',
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
    | The eyebrow above each heading is the slide's reason for being there, one
    | per shoe — «بجای اسم تکراری هم این ۳ تا بیاد رو هر کفش یکیش». It used to
    | be the product's own name, printed a second time directly above the
    | heading that already says it. Which line goes on which shoe is an
    | editorial choice like the slugs beside them, so it is written here: swap a
    | string and the deck says something else.
    |
    | It is a claim about the shoe, not a fact read off the catalogue — nothing
    | checks that «موجودی محدود» is true of the stock, and nothing should
    | without somebody deciding what the threshold is.
    |
    */

    'hero' => [
        'products' => [
            'on-cloudtilt' => 'پر فروش این هفته',
            'jordan-one-air' => 'یه پیشنهاد ویژه',
            'golden-goose' => 'موجودی محدود',
        ],

        /*
        | The photograph a hero slide draws, when it may not be the product's
        | own.
        |
        | «عکس های فروشگاه بکگراند دارن و مواردی که ما تو هیرو میزاریم باید بی
        | بکگراند باشن که بشینن رو شیشه هیرو» — and that is a real constraint
        | rather than a preference: the slide's photograph sits on the glass
        | pane with nothing behind it, so a shot with a studio ground draws a
        | grey rectangle on the pane. Every other place in the shop shows the
        | catalogue's own photograph, ground and all, and should.
        |
        | So this is an override and not a second catalogue: a slug listed here
        | draws the cut-out named, and a slug that is not falls back to
        | `imagePath()` exactly as the deck always did. It is keyed by slug and
        | not by position, so it holds whether the deck came from the list
        | above or from `/admin/front-page`.
        |
        | The files are made by `theme/make-hero-photos.js` from the cut-outs in
        | `theme/hero-src/`: trimmed to the shoe and laid on one canvas, so the
        | three read as one set rather than three crops.
        */
        'photos' => [
            // «این دوتا هم تو هیرو عوض بشن» — the client's newer shot of the
            // Cloudtilt, and a different shoe from the one it replaces: the old
            // cut-out is all black (1% white), this one carries the white
            // CloudTec sole (38%). It arrived on a **black** background rather
            // than on transparency, which is the one thing a hero shot may not
            // be; `theme/lift-off-black.js` is what cut it, and its own notes
            // say how a black shoe comes off a black ground.
            'on-cloudtilt' => 'assets/img/hero/vikyplus-hero-cloudtilt-on-black.webp',

            // The black suede Golden Goose, on the shop's own black Golden
            // Goose — ۳٬۱۹۰٬۰۰۰, where the seeded `golden-goose` this slide
            // used to point at is ۴٬۵۳۶٬۰۰۰ and is not a shoe anybody sells.
            // Seven Golden Geese are listed and exactly one of them is «مشکی».
            'کتونی-گلدن-گوس-رنگ-مشکی-Golden-Goose' => 'assets/img/hero/vikyplus-hero-goldengoose-suede.webp',

            // The red Air Jordan 1 High — «اینو بزار بجای اون جردن تو هیرو».
            // Which of the shop's twenty-four Jordans it is was measured
            // rather than read off a name: see the migration
            // `2026_09_04_140000_…`, which is also what puts the shoe in the
            // deck. The slug is the live shop's and is in no database here, so
            // this line is inert locally and both copies of the home page are
            // unmoved by it.
            'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-قرمز' => 'assets/img/hero/vikyplus-hero-jordan-chicago.webp',
        ],

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

    /*
    |--------------------------------------------------------------------------
    | Which products the front page shows
    |--------------------------------------------------------------------------
    |
    | Two bands used to answer this from the catalogue and stopped being able
    | to the day the catalogue grew past the five shoes it was drawn around.
    |
    | The stepped sale's row was «every purchasable product with a
    | compare-at price», and the story rings were «the five newest». Both were
    | exactly right while the shop had five products and both are wrong the
    | moment it has a hundred and thirty-seven: the row wraps into a wall of
    | cards it was never laid out for, and the rings become whatever was
    | imported last, in the order it happened to be inserted. Neither is a
    | decision anybody made.
    |
    | So the front page names its own cast, the way the hero and the daily deal
    | already do, and the shop underneath it can grow without redrawing it.
    |
    | **These lists filter; they do not order.** Each band keeps the ordering it
    | already had — the sale row by publication date, the rings by id — so the
    | page reads exactly as it did, which is why `check-parity.js` still prints
    | zero after this. Writing the slugs in a different order here changes
    | nothing, and that is deliberate: an order in two places is an order that
    | will disagree with itself.
    |
    | Empty either list and the old behaviour comes back — the sale row takes
    | everything discounted, the rings take the newest five.
    |
    */

    'front_page' => [
        'ladder_products' => ['jordan-one-air', 'nike-v2k-run', 'new-balance-530', 'on-cloudtilt', 'golden-goose'],
        'story_products' => ['jordan-one-air', 'nike-v2k-run', 'new-balance-530', 'on-cloudtilt', 'golden-goose'],
    ],

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
    | The story strip's own photographs
    |--------------------------------------------------------------------------
    |
    | «عکسای استوری ها اینا باشن» — five images the client supplied for the
    | circles above the listing's search line, and they are campaign art rather
    | than catalogue art.
    |
    | **This deliberately decouples the story's picture from the product's.**
    | Asked how to bind them, the client chose «هر پنج را روی استوری‌ها بگذار»
    | over binding each to the shoe it depicts, so the strip paints these five
    | in this order and the link under each circle still goes to whatever the
    | composer picked.
    |
    | **Four of the five circles now show a shoe they do not sell you.** Only
    | the New Balance 530 still sits on its own product. That is not drift: the
    | order below was set by where the colours wanted to be on the screen, and
    | the decoupling was chosen knowingly before it was. It is written here
    | because a circle whose picture and basket disagree is exactly the kind of
    | thing the next person will read as a bug and quietly "fix".
    |
    | The composer picks the five *latest purchasable* products, so which shoe
    | sits under which picture also moves whenever the catalogue grows. If the
    | pairing ever has to mean something, that is the thing to change first —
    | pin the stories to named products rather than re-ordering this list.
    |
    | Positional, so it is one line to re-order and one line to correct. A story
    | past the end of this list falls back to its product's own photograph, and
    | so does every story if this is emptied — nothing here can leave a circle
    | with no picture at all.
    |
    */

    'stories' => [
        /*
         | **This list reads right to left, because the strip does.** The page
         | is RTL, so the first entry is the circle nearest the right edge and
         | the last is the one nearest the left. Written the other way round it
         | would look correct in the file and come out mirrored on the phone.
         |
         | The order is the client's, given by where things sit on the screen:
         | «عکس سرمه ای که الان اوله بره آخر سمت راست و عکس آخر بیاد جای صورتی و
         | صورتی بیاد از چپ اول». Left to right on a phone that is now pink,
         | red, blue, teal, navy.
         */
        'photos' => [
            'assets/img/story/story-nb530-bag.webp',
            'assets/img/story/story-vomero.webp',
            'assets/img/story/story-nb530.webp',
            'assets/img/story/story-jordan-bag.webp',
            'assets/img/story/story-cloudtilt.webp',
        ],

        /*
         | What the story shows once it is open — «این عکس هم باید بیاد زمانی که
         | میزنیم استوری باز میشه».
         |
         | The circle is a thumbnail and the island is a poster, and they are
         | not the same picture any more. A thumbnail has to read at 55px with
         | no words on it; a poster is read at arm's length and can carry the
         | campaign, which is what «تخفیف نوروزی ویکی پلاس ۳۰٪» is doing on the
         | one below.
         |
         | **Same index as `photos` above, so it reads right to left too.**
         | `null` means the story opens on its own circle picture, which is what
         | it did before any of these existed — so a missing poster is a story
         | that still works rather than an empty island.
         |
         | Only the fifth is set. It is the On Cloudtilt one, paired with the
         | Cloudtilt circle it sits under. The other four are waiting on
         | artwork; drop a path in and it appears, no code changes.
         |
         | Nothing here is a price or a claim the application can check. The ۳۰
         | printed on that poster is a picture of a number, not
         | `config('storefront.ladder')` — if the sale steps to ۴۵ this file
         | will go on saying ۳۰ until somebody replaces the artwork. That is the
         | cost of putting a rate inside an image and it is worth knowing before
         | the ladder moves.
         */
        'posters' => [
            null,
            null,
            null,
            null,
            'assets/img/story/poster-cloudtilt-nowruz.webp',
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
    | The size row
    |--------------------------------------------------------------------------
    |
    | Every size the product page's row draws, in order — «سایزها باید ۳۷ ۳۸ ۳۹
    | ۴۰ ۴۱». The shop's range, stated, rather than whatever the catalogue
    | happens to hold today: a size nobody has stocked yet is still a size this
    | shop sells, and the row is supposed to say which sizes exist before it
    | says which are in.
    |
    | A size in this list that the shoe has not got is drawn greyed. A size the
    | shoe *has* got and this list has not is still drawn, in its place in the
    | order: this list says what is added to the row, never what is taken out
    | of it. The chip is where the radio is, so a sellable size without one
    | would be a size nobody could put in the basket.
    |
    */

    'size_row' => [37, 38, 39, 40, 41],

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

    /*
    |--------------------------------------------------------------------------
    | The size chart
    |--------------------------------------------------------------------------
    |
    | EU size, the foot length in centimetres it is cut for, and the US and UK
    | equivalents. **The centimetre figure is the foot, not the shoe** — it is
    | the number somebody measuring at home has in front of them.
    |
    | It lived inside `pages/size-guide.blade.php` until the product page
    | needed the same table for its EU/US/CM switch. Two copies of a
    | correspondence table is two answers to «سایز ۴۰ یعنی چند؟», so it moved
    | here and both views read it.
    |
    | It is the ordinary women's correspondence, not measured off our own
    | lasts — the size-guide page says so in as many words, and that caveat is
    | the reason this is a published table rather than a claim about a
    | particular shoe.
    |
    */

    'size_chart' => [
        ['eu' => 35, 'cm' => 22.5, 'us' => 5, 'uk' => 2.5],
        ['eu' => 36, 'cm' => 23, 'us' => 6, 'uk' => 3.5],
        ['eu' => 37, 'cm' => 23.5, 'us' => 6.5, 'uk' => 4],
        ['eu' => 38, 'cm' => 24.5, 'us' => 7.5, 'uk' => 5],
        ['eu' => 39, 'cm' => 25, 'us' => 8.5, 'uk' => 6],
        ['eu' => 40, 'cm' => 25.5, 'us' => 9, 'uk' => 6.5],
        ['eu' => 41, 'cm' => 26.5, 'us' => 10, 'uk' => 7.5],
    ],

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
         | The product page's colour row.
         |
         | «یه لاین انتخاب رنگ هم نیاز داریم». Every variant in the catalogue
         | carries `display_color = نامشخص` and `color_family = unspecified` —
         | the shop has not told us the colours yet — so there is nothing to
         | build a row of swatches from.
         |
         | These three are a stand-in for the layout, the same as the colourway
         | strip above: nothing in the row is selectable and no colour is
         | named, because a named colour is a claim about the shoe. The moment
         | a variant carries a real colour the page draws that instead and this
         | list is not consulted.
         |
         | Five of them — «رنگها هم حداقل ۵ رنگ روبروش باشن» — of which the
         | first `colors_available` are drawn as in stock and the rest greyed,
         | the same three states the size row has. Both numbers are invented,
         | which is the point of them being here rather than in the database.
         |
         | Set it to [] to leave the row out entirely.
         */
        'colors' => ['#3F4147', '#D98F6B', '#E4C378', '#8FA8B8', '#C9C2BA'],

        /*
         | How many of those are in stock — «توش نوشته باشه ۳ رنگ موجود».
         | Counted from the front of the list.
         */
        'colors_available' => 3,

        /*
         | The product page's rating.
         |
         | There is no review table, so there is nothing to average. The client
         | asked for the number anyway — the reference screen carries it beside
         | the name and «دقیقا همین» was the instruction — so it is here, in
         | the one place in this repo where an invented number is allowed to
         | live, rather than seeded into the catalogue where it would be
         | indistinguishable from a counted one.
         |
         | It is the same number on every product, which is the honest shape
         | for a stand-in: nothing about it varies because nothing about it is
         | measured. **Set it to null the day reviews exist** and the stars
         | come off the page until a real average replaces them.
         */
        'rating' => 4.9,

        /*
         | The product page's description.
         |
         | `products.description` is a real column and the panel can edit it;
         | this is what the page prints for a product whose description has not
         | been written yet, so the block under the name is not an empty gap
         | while the shop's copy is still being typed.
         |
         | It says what is true of every shoe in the catalogue and nothing that
         | is true of one — no material, no country, no story. A stand-in that
         | described the shoe would be a claim about it, and the client has had
         | one invented claim taken off this catalogue already.
         |
         | Set it to null to leave the space empty instead.
         */
        'description' => 'مشخصات کامل این محصول هنوز ثبت نشده است. برای سایز، جنس و شرایط ارسال با پشتیبانی ویکی پلاس در تماس باشید.',

        /*
         | The «توضیحات کفش» section, keyed by brand slug.
         |
         | «عنوان توضیحات کفش و یه متن ۴ خطی در مورد گلدن گوس زیرش». No product
         | in the catalogue has a description, so the section had nothing to
         | print for the shoe in front of it.
         |
         | What is here is about the *brand* and not about the shoe: where the
         | maker is from, what it is known for, who wears it. That is the one
         | kind of copy we can write without making a claim the catalogue has
         | not made — a paragraph about this pair's leather, sole or fit would
         | be inventing the shoe's specification, which is what the block this
         | sits in exists to keep out.
         |
         | It is the *third* thing the section looks for. A product's own
         | `description`, typed in the panel, wins over it; the generic line
         | above is the fallback for a brand with no entry here. So filling a
         | product in the panel is what takes its brand's paragraph off that
         | page, one product at a time.
         */
        'brand_blurbs' => [
            'golden-goose' => 'گلدن گوس برند ایتالیایی کفش‌های دست‌دوز است که از سال ۲۰۰۰ در ونیز کار می‌کند. امضای آن ظاهر عمداً کهنه و ستارهٔ دوخته‌شده روی بدنه است و هر جفت با پرداخت دستی ساخته می‌شود. به همین دلیل هیچ دو جفتی کاملاً شبیه هم نیستند.',
            'nike' => 'نایک برند آمریکایی کفش ورزشی است که از سال ۱۹۶۴ در اورگان کار می‌کند. بسیاری از فناوری‌های امروزی کفش دویدن از آن آمده و سری‌های خیابانی‌اش از دههٔ هشتاد به سبک روزمره رسیدند. طراحی‌هایش میان راحتی و ظاهر ورزشی تعادل برقرار می‌کنند.',
            'jordan' => 'جردن زیرمجموعهٔ نایک است که در سال ۱۹۸۴ برای مایکل جردن پایه‌گذاری شد. ایر جردن ۱ که کفش زمین بسکتبال بود، امروز یکی از شناخته‌شده‌ترین کتانی‌های خیابانی جهان است. نشان بال‌دار و طرح دورنگ، امضای ثابت این خانواده از کفش‌هاست.',
            'new-balance' => 'نیوبالانس برند آمریکایی کفش است که از سال ۱۹۰۶ در بوستون کار می‌کند. سال‌ها با کفش دویدن و تنوع عرض قالب شناخته می‌شد و مدل‌های کلاسیکش مثل ۵۳۰ حالا کتانی روزمره‌اند. رنگ‌بندی خنثی و آرام، ویژگی همیشگی طراحی این برند است.',
            'on' => 'اون برند سوئیسی کفش ورزشی است که در سال ۲۰۱۰ در زوریخ تأسیس شد. امضای آن زیرهٔ حفره‌دار است که فرود را نرم و رانش را سفت می‌کند؛ کفش‌هایش از دویدن به سبک روزمره رسیدند. ظاهر ساده و بی‌شلوغی، آن را برای پوشیدن طولانی‌مدت مناسب کرده.',
        ],

        /*
         | The brand strip.
         |
         | Each tile carries three photographs and a count, and a tile says
         | where its photographs come from in one of two ways.
         |
         | `photos` is the brand's own, as files: three asset paths, the first
         | of them the lead. All four tiles have them now — the client supplied
         | a set per brand and named the arrangement, «این ۳ تصویر در ۳ کادر
         | اول که نایک هستش بیاد» with the shoe on its own for the large cell —
         | so every set reads the same way down the tile: shoe, kit, athlete.
         | theme/make-brand-photos.js is what prepares them.
         |
         | `mosaic` is the stand-in, and nothing uses it any more: category
         | slugs, whose photographs are the eight tiles from the top of the
         | page. That was the client's own call («از عکس های اون قسمت هشتایی
         | بالای وبسایت استفاده کن») from when we held one product photograph
         | per brand and each tile wanted three. It is kept because it is what
         | a fifth tile would fall back on, and because dropping it would mean
         | a brand with no set drawing nothing at all.
         |
         | The counts are invented outright, for every tile: nothing in the
         | catalogue adds up to them, and seeding inventory until it did would
         | be inventing stock rather than counting it.
         |
         | **This list also decides which four brands the strip shows** — it is
         | the `whereIn` the query runs. گلدن گوس came out of it when the
         | client's fourth set turned out to be On's, «کادر چهارم آن رانینگ
         | بشه»; it is still an active brand with a shoe, a page and a place in
         | the best-sellers filter, it is simply not one of the four featured.
         |
         | Keyed by brand slug. A brand with no entry here shows its own three
         | photographs and its real count, which is what should happen as each
         | brand's assets arrive.
         */
        'brand_strip' => [
            'nike' => [
                'photos' => [
                    'assets/img/brand/vikyplus-nike-vomero.webp',
                    'assets/img/brand/vikyplus-nike-kit.webp',
                    'assets/img/brand/vikyplus-nike-athlete.webp',
                ],
                'stock' => 42,
            ],
            'jordan' => [
                'photos' => [
                    'assets/img/brand/vikyplus-jordan-one.webp',
                    'assets/img/brand/vikyplus-jordan-kit.webp',
                    'assets/img/brand/vikyplus-jordan-athlete.webp',
                ],
                'stock' => 28,
            ],
            'new-balance' => [
                'photos' => [
                    'assets/img/brand/vikyplus-nb-530.webp',
                    'assets/img/brand/vikyplus-nb-kit.webp',
                    'assets/img/brand/vikyplus-nb-athlete.webp',
                ],
                'stock' => 35,
            ],
            'on' => [
                'photos' => [
                    'assets/img/brand/vikyplus-on-running.webp',
                    'assets/img/brand/vikyplus-on-kit.webp',
                    'assets/img/brand/vikyplus-on-athlete.webp',
                ],
                'stock' => 19,
            ],
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

            /*
            | The photograph a tile draws, when it may not be the product's own.
            |
            | «اینام برای بخش پر فروشها، یدونه کم بهت دادم که از اون جردن صورتی
            | استفاده کن» — cut-outs on transparency, which is what a tile wants
            | and not what the catalogue holds: the shop's own photographs carry
            | the studio's ground and are right everywhere else.
            |
            | **Keyed by slug and not by position**, unlike the story strip's.
            | A tile prints the shoe's name and its price directly under the
            | picture — «از همون عکس های قسمت حراج پله ای استفاده کن» is what
            | put them together — so a picture chosen by position would sooner
            | or later put a Nike over Golden Goose's name at Golden Goose's
            | price. Built positionally once and measured: four of the six tiles
            | were labelled with a different shoe than the one shown.
            |
            | A slug not listed here falls back to the product's own photograph,
            | which is what every tile did before this existed.
            */
            'photos' => [
                // The live shop's own products, which is where these tiles
                // point: the slugs are Persian and are not in the seeded
                // catalogue here, so locally they fall through to the entries
                // below and nothing changes. Which colourway each photograph
                // is of was asked rather than guessed — the shop carries eight
                // New Balance 530s and the colour lives only in the slug.
                'کتونی-نیوبالانس-New-balance-530-رنگ-سفید-مشکی' => 'assets/img/hero/vikyplus-hero-nb530-white.webp',
                'نایک-جردن-تراویس-اسکات-رنگ-یشمی-Nike-jordan-travis-scott' => 'assets/img/hero/vikyplus-hero-jordan-travis.webp',
                // The hero's own shot, on the hero's own shoe — «عکس گلدن گوس
                // که تو هیرو هست همون باید تو گلدن گوس پرفروش ترین ها قرار
                // بگیره». One product, one picture, wherever the front page
                // draws it.
                'کتونی-گلدن-گوس-رنگ-مشکی-Golden-Goose' => 'assets/img/hero/vikyplus-hero-goldengoose-suede.webp',

                // A fresh install's five, which is what this repository seeds.
                'new-balance-530' => 'assets/img/hero/vikyplus-hero-nb530-white.webp',
                'golden-goose' => 'assets/img/hero/vikyplus-hero-goldengoose-black.webp',
                'jordan-one-air' => 'assets/img/hero/vikyplus-hero-jordan.webp',

                // The same shoe the hero draws with the same cut-out — one
                // product, one picture. It moved to the client's newer shot
                // when the hero did: the two are different colourways of the
                // Cloudtilt, and leaving the tile on the old one would have put
                // two different shoes under one product's name.
                'on-cloudtilt' => 'assets/img/hero/vikyplus-hero-cloudtilt-on-black.webp',

                // The same shoe the hero draws, with the same cut-out. It
                // takes the sixth tile from `new-balance-530` — a product this
                // repository *seeds a fresh install with*, still in production
                // from setup day at a price no New Balance in the shop is
                // charged, which the row had been falling back to because the
                // band's own sixth shoe sold out. «یکی از نیو بالانس ها به
                // فروشگاه لینک نمیشه و اضافست، باید بجاش این جردن بیاد و از
                // فروشگاه خونده بشه.»
                'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-قرمز' => 'assets/img/hero/vikyplus-hero-jordan-chicago.webp',

                // `nike-v2k-run` is deliberately absent and keeps its own
                // photograph. The sixth cut-out is a cream Nike Air Zoom, which
                // is a different model from the V2K Run, and no Air Zoom exists
                // in the shop for it to sit on. `theme/hero-src/` holds it
                // wired to nothing until that product is created in the panel
                // or the shop says which shoe it is.
            ],

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
