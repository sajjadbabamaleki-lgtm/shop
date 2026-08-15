@extends('layouts.storefront')

{{--
    The listing — the shop, a category and a search result, which are one page
    with a different opening filter.

    The white pane, its 20px margins, its 50px radius and its ring are
    .vp-best-panel's, quoted rather than reinvented: the page already has a
    material for "a big card holding a section", and a second one that was
    nearly the same would read as a mistake.

    The rail is a plain GET form. No JavaScript decides what is shown — the URL
    does, so a filtered listing can be linked to, bookmarked, and gone back to.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        {{-- `vp-listing-panel` is the listing's own hook, and it exists so a
             phone rule can take this page's frame off without taking it off
             the basket, the product page, the checkout and the account, which
             are all built on the same `.vp-shop-panel`. --}}
        <div class="vp-shop-panel vp-listing-panel">

            {{-- «تو قسمت فروشگاه بالای سرچ برام ۵ استوری بزار».

                 The strip the home page was given and then had parked —
                 «بنظرم استوری هارو هاید کن» — is the same five circles, so it
                 is included here rather than built again. Its own file says
                 what they are: the catalogue's first five sections, with the
                 photographs and names the category tiles use, so the strip and
                 the tiles cannot describe two different shops.

                 `$strip` and not the facets' categories: it is every active
                 section in `position` order, which is the list the drawer and
                 the home page's tiles read. The facets' list drops a section
                 with nothing purchasable in it, and a story that comes and
                 goes with the stock is not a section of the shop.

                 The home page's copy stays parked. This one is switched on by
                 `.vp-listing-panel .vp-stories` in the stylesheet, so turning
                 one on does not turn the other on. --}}
            @include('home.stories', ['categories' => $strip])

            {{-- The phone's own top bar: back, search, filter.

                 Built from the client's reference screenshot. It is one row on
                 a phone and hidden above 992, where the page keeps the heading
                 and the sidebar it already had — this is a phone layout laid
                 over a desktop one, not a replacement for it.

                 The filter is a <details>, so the panel opens with no
                 JavaScript at all and the page keeps its promise that the URL
                 decides what is shown. --}}
            {{-- The search line: two white boxes, side by side.

                 It was one box carrying both — «سرچ بار باید سفید بشه و دورش
                 سایه بیاد و فیلتر هم بیاد داخل باکس سرچ سمت چپش» — and then
                 «باکس سرچو از فیلتر جدا کن و باکس سرچ ارتفاعش بشه اندازه فیلتر»
                 split them again at equal heights. `.vp-shop-top` is the row
                 now and nothing else: the white, the corner and the light are
                 on `.vp-shop-find` and on the filter's own `<summary>`, and the
                 two heights are one number, set together in the stylesheet.

                 **They are siblings under every version of this, and that is
                 not a layout preference.** `shop.filters` is a <form>, so
                 putting the filter's <details> inside the search form would
                 nest one form in another — which browsers do not merely
                 tolerate: the inner one is dropped, and the phone's whole
                 filter rail would stop submitting with nothing to see.

                 The form comes first and the filter second, which on this rtl
                 page puts the field at the right and the filter at the left —
                 «سمت چپش». --}}
            <div class="vp-shop-top">
                <form class="vp-shop-find" action="{{ storefront_route('search') }}" method="get" role="search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    {{-- Shorter than the desktop's, which is a different input in a
                         different box: at 390 the field is 215 wide and the long
                         one was cut mid-word. --}}
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="چی می‌خوای؟" aria-label="جست‌وجو در محصولات">
                </form>

                {{-- A <details>, so the panel opens with no JavaScript and the
                     page keeps its promise that the URL decides what is shown.
                     The rail itself is unchanged — it is the same include the
                     desktop renders in its sidebar, just folded away here. --}}
                <details class="vp-shop-filter">
                    <summary class="vp-shop-filter-btn"><i class="fa-solid fa-sliders" aria-hidden="true"></i>فیلتر</summary>
                    <div class="vp-shop-filter-panel">@include('shop.filters')</div>
                </details>
            </div>

            {{-- «اون قسمت پاپلر و لاتستو اینا هم باید باشه». Links, not a
                 select: a sort is a place you can be, so it belongs in the URL
                 and in the back button. Each one carries the other filters
                 with it or changing the order would throw the filters away. --}}
            @php
                $carry = array_filter([
                    'q' => $filters['q'],
                    'brand' => $filters['brand'],
                    'size' => $filters['size'],
                    'color' => $filters['color'],
                    'category' => $filters['category']?->slug,
                    'sale' => $filters['sale'] ? 1 : null,
                ]);
                $priceOn = in_array($filters['sort'], \App\Http\Controllers\ShopController::PRICE_TABS, true);
                // The price tab toggles rather than picking: tapping it once
                // sorts up, tapping it again sorts down, which is what the
                // reference's caret means.
                $priceNext = $filters['sort'] === 'cheapest' ? 'dearest' : 'cheapest';
            @endphp
            <nav class="vp-shop-tabs" aria-label="ترتیب">
                @foreach (\App\Http\Controllers\ShopController::TABS as $key)
                    <a class="vp-shop-tab{{ $filters['sort'] === $key ? ' is-on' : '' }}"
                       href="{{ storefront_route('shop') }}?{{ http_build_query($carry + ['sort' => $key]) }}">{{ $sorts[$key] }}</a>
                @endforeach
                {{-- «فیلتر قیمت وقتی باز میشه باید ۲ حالته باشه» — two boxes and
                     a slider, in one panel.

                     The two are one control, not two: the slider writes into
                     the «تا» box as it moves, so a drag and a typed number are
                     the same filter arriving by different hands.

                     **The slider carries no `name` and submits nothing.** An
                     earlier comment here said it degraded to a third way of
                     setting a maximum; it does not, and should not — a named
                     range would post a maximum on every apply, including one
                     nobody touched, and now that the thumb opens in the middle
                     that maximum would be the midpoint. The box is the field.
                     Without JavaScript the slider does nothing and the two
                     boxes still work, which is the honest description.

                     The tab used to sort — cheapest, then dearest, on each tap
                     — and that has not been thrown away: the two sorts are the
                     first thing in the panel. It is the same word doing the
                     same job with more room. --}}
                <details class="vp-shop-tab vp-shop-sheet{{ ($priceOn || $filters['min'] || $filters['max']) ? ' is-on' : '' }}">
                    <summary>قیمت</summary>
                    <div class="vp-sheet-scrim" data-vp-sheet-close></div>
                    <div class="vp-sheet vp-sheet-price">
                        <div class="vp-sheet-head"><span class="vp-sheet-title">قیمت</span><button type="button" class="vp-sheet-x" data-vp-sheet-close aria-label="بستن"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
                        {{-- The whole sheet is one form, and the two sorts are
                             radios in it.

                             They were links, so tapping «ارزان‌ترین» navigated
                             and the sheet shut on the spot — «وقتی مثلا رو
                             ارزانترین زده میشه پاپاپ بسته میشه در صورتی که باید
                             با زدن دکمه اعمال فیلتر بسته بشه». A radio changes
                             nothing but itself; the button at the foot is what
                             applies the order *and* the two boxes together,
                             which is also the only way to set both in one go.

                             The hidden `sort` that used to ride along is gone
                             with them — the radios carry it now, and two fields
                             of the same name would have posted the old value. --}}
                        <form class="vp-price-form" method="get" action="{{ storefront_route('shop') }}">
                            @foreach (collect($carry)->except(['min', 'max', 'brand'])->all() as $name => $value)
                                @if ($value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                            @endforeach
                            @foreach ($filters['brand'] as $slug)
                                <input type="hidden" name="brand[]" value="{{ $slug }}">
                            @endforeach

                            <div class="vp-price-sorts">
                                @foreach (\App\Http\Controllers\ShopController::PRICE_TABS as $key)
                                    <label class="{{ $filters['sort'] === $key ? 'is-on' : '' }}">
                                        <input type="radio" name="sort" value="{{ $key }}" @checked($filters['sort'] === $key)>
                                        <span>{{ $sorts[$key] }}</span>
                                    </label>
                                @endforeach
                            </div>

                            @if ($facets['price']['min'] !== null)

                            {{-- «از» in the left box and «تا» in the right, both
                                 labels on the right of their own box and both
                                 numbers on the left — «تا باید مث از در سمت راست
                                 کادر قرار بگیره». They were mirrored for one
                                 round, facing each other across the middle, and
                                 the client wants the two boxes to read the same
                                 way rather than as a pair of bookends.

                                 The row runs ltr for that: on an rtl page the
                                 first child would sit at the right, and «از» has
                                 to be the left one. It is the same direction the
                                 slider under it now runs in, which is the point —
                                 low on the left, high on the right, one axis for
                                 both controls. --}}
                            <div class="vp-price-boxes">
                                <label class="vp-price-box">
                                    <input type="text" inputmode="numeric" name="min" data-vp-price-min value="{{ $filters['min'] ? fa_number(intdiv($filters['min'], 10)) : '' }}" placeholder="{{ toman($facets['price']['min']) }}" aria-label="کمترین قیمت">
                                    <span>از</span>
                                </label>
                                <label class="vp-price-box">
                                    <input type="text" inputmode="numeric" name="max" data-vp-price-max value="{{ $filters['max'] ? fa_number(intdiv($filters['max'], 10)) : '' }}" placeholder="{{ toman($facets['price']['max']) }}" aria-label="بیشترین قیمت">
                                    <span>تا</span>
                                </label>
                            </div>

                            {{-- Toman, like the boxes beside it: the offers are
                                 stored in rial and every price the shopper sees
                                 is a tenth of it. The step is a hundred thousand
                                 toman, the smallest move worth having on a range
                                 this wide.

                                 The ends are rounded down and up to that step,
                                 and that is not tidiness. A range snaps to
                                 `min + n × step`, so a min of ۳۴۱٬۶۰۰ makes every
                                 stop end in 1,600 — the first drag produced
                                 ۴٬۰۱۶٬۰۰۰, which reads like a bug in a price
                                 filter. Rounded ends make every stop a round
                                 hundred thousand. --}}
                            @php
                                $lo = intdiv(intdiv($facets['price']['min'], 10), 100000) * 100000;
                                $hi = (int) ceil(intdiv($facets['price']['max'], 10) / 100000) * 100000;
                                // «دایره رنج وسط قرار بگیره» — the thumb opens in
                                // the middle rather than at the top of the range.
                                // The «تا» box stays empty until the slider is
                                // moved, so a shopper who opens the sheet and
                                // applies without touching it is not silently
                                // filtered to the midpoint.
                                $now = $filters['max'] ? intdiv($filters['max'], 10) : intdiv($lo + $hi, 2);
                            @endphp
                            <input class="vp-price-range" type="range" data-vp-price-range
                                   min="{{ $lo }}"
                                   max="{{ $hi }}"
                                   step="100000"
                                   value="{{ $now }}"
                                   style="--vp-fill: {{ $hi > $lo ? round(($now - $lo) / ($hi - $lo) * 100, 2) : 100 }}%"
                                   aria-label="بیشترین قیمت، کشویی">

                            @endif

                            <button type="submit" class="vp-price-apply">اعمال فیلتر</button>
                        </form>
                    </div>
                </details>

                {{-- «فیلتر برند و حراج پله ای», in the space at the end of this
                     row. They are filters and the three before them are sorts,
                     which is a real difference — a sort rearranges the same
                     list, a filter shortens it — but they belong to the same
                     glance, and the row had the room.

                     Both keep the sort that is running and every other filter,
                     the same way the sorts keep the filters. --}}
                {{-- The brand picker: a sheet, not a dropdown.

                     «یه کشوی مستطیل افقی باز بشه که یه فضای خالی بالای یه خط
                     باشه زیر اون خط برندهای مختلف تو بیضی باشن به رنگ مشکی و
                     وقتی انتخاب میشن بیان بالای خط به رنگ گلد».

                     **It is a form now, and the chips are checkboxes.** They
                     were links: tapping one navigated, which reloaded the page
                     and shut the sheet — «وقتی مثلا رو یه برند زده میشه پاپاپ
                     بسته میشه و نمیتونم دوتا یا چنتا برند انتخاب کنم». A
                     checkbox changes nothing but itself, so the sheet stays
                     open and several brands can be chosen; «اعمال فیلتر» at the
                     foot is what applies them.

                     No script in it. A label over a hidden checkbox is the same
                     device the size chips on the product page use, and the gold
                     comes from `:checked` rather than from a class the server
                     had to guess.

                     **The chip crosses the rule when it is tapped, not when the
                     page comes back.** «وقتی یه مورد یا چن مورد انتخاب میشه
                     همون موقه که انتخاب میشه باید بره بالای خط قبل از اینکه
                     اعمال فیلتر زده بشه». That does need script — this file's
                     own note used to say so and leave it — and the script is
                     pushed at the foot of this view. The server still renders
                     the two lists correctly on its own, so what arrives is
                     right before a line of JavaScript has run and the move is
                     the only thing the script is for.

                     `data-vp-rank` is the catalogue's own order, stamped on
                     every chip. Without it a chip sent back below the rule
                     could only be appended to the end, and a shopper who ticked
                     and unticked would slowly shuffle the list; with it, both
                     sides stay in the order `$facets['brands']` came in. It has
                     to come from the whole list rather than from either loop's
                     index, because the two loops are a filter and a reject of
                     the same list and neither one's position survives the
                     split. --}}
                @php
                    $brandOn = collect($facets['brands'])->filter(fn ($b) => in_array($b->slug, $filters['brand'], true));
                    $brandOff = collect($facets['brands'])->reject(fn ($b) => in_array($b->slug, $filters['brand'], true));
                    $brandRank = collect($facets['brands'])->values()->mapWithKeys(fn ($b, $i) => [$b->slug => $i])->all();
                @endphp

                <details class="vp-shop-tab vp-shop-sheet{{ $filters['brand'] ? ' is-on' : '' }}">
                    <summary>برند</summary>
                    <div class="vp-sheet-scrim" data-vp-sheet-close></div>
                    <div class="vp-sheet">
                        <div class="vp-sheet-head">
                            <span class="vp-sheet-title">برند</span>
                            <button type="button" class="vp-sheet-x" data-vp-sheet-close aria-label="بستن"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </div>

                        <form class="vp-sheet-form" method="get" action="{{ storefront_route('shop') }}">
                            @foreach (collect($carry)->except('brand')->all() as $name => $value)
                                @if ($value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                            @endforeach
                            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">

                            <div class="vp-sheet-picked" data-vp-chips-on>
                                @foreach ($brandOn as $brand)
                                    <label class="vp-chip" data-vp-rank="{{ $brandRank[$brand->slug] }}">
                                        <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" checked>
                                        <span>{{ $brand->name }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="vp-sheet-rule"></div>

                            <div class="vp-sheet-chips" data-vp-chips-off>
                                @foreach ($brandOff as $brand)
                                    <label class="vp-chip" data-vp-rank="{{ $brandRank[$brand->slug] }}">
                                        <input type="checkbox" name="brand[]" value="{{ $brand->slug }}">
                                        <span>{{ $brand->name }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <button type="submit" class="vp-sheet-apply">اعمال فیلتر</button>
                        </form>
                    </div>
                </details>

                {{-- A toggle, not a link one way: tapping it again takes the
                     filter off, which is what a shopper expects of a thing
                     that looks switched on. --}}
                <a class="vp-shop-tab{{ $filters['sale'] ? ' is-on' : '' }}"
                   href="{{ storefront_route('shop') }}?{{ http_build_query(collect($carry)->except('sale')->all() + array_filter(['sort' => $filters['sort'], 'sale' => $filters['sale'] ? null : 1])) }}">حراج پله‌ای</a>
            </nav>

            {{-- The client's own categories, not the reference's watches and
                 beauty. Same list and same order as the phone drawer and the
                 home page's tiles, with the icons config/storefront.php already
                 carries for them. --}}
            <nav class="vp-shop-strip" aria-label="دسته‌بندی‌ها">
                @foreach ($strip as $cat)
                    <a class="vp-shop-cat{{ $filters['category']?->is($cat) ? ' is-on' : '' }}" href="{{ storefront_route('category', $cat) }}">
                        <img src="{{ asset(config('storefront.category_icons.'.$cat->slug, config('storefront.category_icons.default'))) }}" alt="" aria-hidden="true">
                        <span>{{ $cat->name }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    {{-- The count lives in the bar above the grid and nowhere
                         else: it was printed twice, three centimetres apart. --}}
                    <h1 class="vp-shop-title">{{ $heading }}</h1>
                </div>

                <form class="vp-shop-search" action="{{ storefront_route('search') }}" method="get" role="search">
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="دنبال چی می‌گردی؟" aria-label="جست‌وجو در محصولات">
                    <button type="submit" aria-label="جست‌وجو"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                </form>
            </div>

            <div class="vp-shop-body">

                {{-- The desktop's sidebar. On a phone the same include is
                     inside the top bar's <details> instead, and this copy is
                     hidden — one rail, rendered twice, only ever shown once.
                     Rendering it twice rather than moving it keeps the desktop
                     layout exactly as it was; the phone's copy is inside a
                     closed <details>, so it costs markup and no paint. --}}
                <div class="vp-shop-rail-desktop">@include('shop.filters')</div>

                <div class="vp-shop-main">

                    <div class="vp-shop-bar">
                        {{-- The count sits with the control that changes the order,
                             because "۵ کالا" and "ترتیب" are one thought: what is
                             in this list, and in what order. --}}
                        <span class="vp-shop-bar-count">{{ fa_number($products->total()) }} کالا</span>

                        <form class="vp-shop-sort" method="get" action="{{ storefront_route('shop') }}">
                            {{-- Sorting must not throw the other filters away, so they ride along hidden. --}}
                            @foreach (['q' => $filters['q'], 'size' => $filters['size'], 'color' => $filters['color']] as $name => $value)
                                @if ($value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                            @endforeach
                            {{-- One field per brand: `brand` is a list now, and a
                                 single hidden input would have posted the word
                                 «Array». --}}
                            @foreach ($filters['brand'] as $slug)
                                <input type="hidden" name="brand[]" value="{{ $slug }}">
                            @endforeach
                            @if ($filters['category'])<input type="hidden" name="category" value="{{ $filters['category']->slug }}">@endif
                            @if ($filters['sale'])<input type="hidden" name="sale" value="1">@endif

                            <label for="vp-sort">ترتیب</label>
                            <select id="vp-sort" name="sort" onchange="this.form.submit()">
                                @foreach ($sorts as $key => $label)
                                    <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <noscript><button type="submit">اعمال</button></noscript>
                        </form>
                    </div>

                    @if ($products->isEmpty())
                        <div class="vp-empty">
                            <span class="vp-empty-mark" aria-hidden="true">
                                <svg viewBox="0 0 48 48"><circle cx="21" cy="21" r="13" fill="none" stroke="currentColor" stroke-width="3"></circle><path d="M31 31 L42 42" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                            </span>
                            <p class="vp-empty-say">چیزی با این مشخصات پیدا نشد.</p>
                            <a class="vp-empty-out" href="{{ storefront_route('shop') }}">دیدن همه محصولات</a>
                        </div>
                    @else
                        <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xxl-4 vp-shop-grid">
                            @foreach ($products as $product)
                                <div class="col">@include('shop.card')</div>
                            @endforeach
                        </div>

                        <div class="vp-shop-pages">{{ $products->links('pagination.vikyplus') }}</div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</section>
@endsection

{{--
    The brand sheet's chips cross the rule as they are tapped.

    «تو قسمت فیلترا وقتی یه مورد یا چن مورد انتخاب میشه همون موقه که انتخاب
    میشه باید بره بالای خط قبل از اینکه اعمال فیلتر زده بشه» — picked belongs
    above the line the moment it is picked, not after «اعمال فیلتر» has been
    pressed and the page has come back.

    **Pushed from this view rather than added to `partials/scripts`.** That
    partial is generated by `theme/make-blade.js` out of the preview page, so
    hand-editing it is undone by the next run — and the preview page is the
    home page, which has no brand sheet in it, so the code would ship to every
    page on the site to run on markup only this one has. `@stack('scripts')` is
    what the layout provides for exactly this.

    **It moves the label and nothing else, and that is what keeps the form
    honest.** The checkbox travels inside its own <label>, so it is still the
    same field with the same `name="brand[]"` and the same value; a moved node
    keeps its `checked` state, because that state is a property and not the
    attribute the server wrote. Nothing here writes a value, removes a field or
    remembers a choice of its own — «اعمال فیلتر» still submits the form, and
    with the script blocked or broken the sheet behaves exactly as it did
    before: the chip turns gold where it stands, via `.vp-chip:has(:checked)`,
    and the server sorts the two lists on the way back.

    `data-vp-rank` is what it inserts by, so a chip put back below the rule
    lands where the catalogue says rather than on the end of the row.
--}}
@push('scripts')
<script>
    (function () {
        // Delegated from the document: the sheets are <details>, so a chip can
        // be in a panel that has never been opened, and binding per-element on
        // load would still work — but this also survives anything that
        // re-renders a sheet later, and costs one listener.
        document.addEventListener("change", function (e) {
            var input = e.target;
            if (!input || !input.matches) return;
            // Scoped to the brand sheet's own checkboxes. The price sheet's
            // radios are in `.vp-price-form` and must not be touched: they are
            // a sort, they have no rule to cross, and moving one would tear it
            // out of the group it belongs to.
            if (!input.matches(".vp-sheet-form .vp-chip input[type=checkbox]")) return;

            var chip = input.closest(".vp-chip");
            var form = input.closest(".vp-sheet-form");
            if (!chip || !form) return;

            var to = form.querySelector(input.checked ? "[data-vp-chips-on]" : "[data-vp-chips-off]");
            if (!to || chip.parentNode === to) return;

            // In catalogue order: before the first chip already there that
            // ranks after this one, or at the end if there is no such chip.
            var rank = Number(chip.getAttribute("data-vp-rank"));
            var before = null;
            for (var i = 0; i < to.children.length; i++) {
                if (Number(to.children[i].getAttribute("data-vp-rank")) > rank) {
                    before = to.children[i];
                    break;
                }
            }

            to.insertBefore(chip, before);
        });
    }());
</script>
@endpush
