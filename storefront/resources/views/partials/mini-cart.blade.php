{{--
    The mini basket, behind the header's basket button.

    Hand-owned. What was here was the ThemeForest demo — «فروشگاهping Cart»,
    five Nike shoes, dollar prices, remove links pointing at '#' — and it
    survived the whole port because the panel is parked off-screen: no visual
    check can see it, and nobody opens it in a desktop review. The phone drawer
    had exactly this history; `MiniCartTest` is what watches this one.

    Prices are read live from the branch's offers, the same way the cart page
    reads them: the basket stores quantities and nothing else, so a total here
    is never yesterday's. A line the shop can no longer supply keeps its place
    and says so rather than disappearing.

    The empty state is what the static preview carries too, so check-parity.js
    compares like with like — a visitor with an empty basket is what both pages
    draw.

    `sidemenu-wrapper`, `sidemenu-content` and `sideMenuCls` are the template's
    own: its script opens and closes the panel by those names.
--}}
<div class="sidemenu-wrapper sidemenu-cart">
        <div class="sidemenu-content">
            <div class="vp-mini">
                <div class="vp-mini-head">
                    <h2 class="vp-mini-title">سبد خرید</h2>
                    <button type="button" class="closeButton sideMenuCls" aria-label="بستن سبد خرید"><i class="fal fa-times" aria-hidden="true"></i></button>
                </div>
                @if ($miniCart->isEmpty())
                <div class="vp-mini-empty">
                    <span class="vp-mini-empty-mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M10 16 h28 l-3 22 a3 3 0 0 1 -3 3 h-16 a3 3 0 0 1 -3 -3 z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path><path d="M18 16 a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg></span>
                    <p class="vp-mini-say">سبد خریدت خالی است.</p>
                    <a class="vp-mini-out" href="{{ storefront_route('shop') }}">رفتن به فروشگاه</a>
                </div>
                @else
                <ul class="vp-mini-lines">
                    @foreach ($miniCart->lines() as $line)
                    @php $variant = $line['item']->variant; @endphp
                    {{-- «سبد خریدی که تو هدر بصورت جزیره ای باز میشه نسخه قدیمیه باید
                         بشه شکل این چیز جدیدی که ساختی» — so it is not *like* the
                         basket page's card, it *is* it: the same `.vp-cart-line`
                         and the same children, sized by the same block measured
                         off the client's reference. Two copies of one card styled
                         apart is how this panel came to be a version behind in the
                         first place, and `vp-mini-*` line classes are what let that
                         happen quietly. What stays `vp-mini-*` is the panel around
                         the cards — its head, its foot and its empty state. --}}
                    <li class="vp-cart-line{{ $line['offer'] === null || $line['available'] < $line['quantity'] ? ' is-short' : '' }}">
                        <a class="vp-cart-shot" href="{{ storefront_route('product', $variant->product) }}">
                            @if ($variant->product?->primaryMedia())
                                <img src="{{ asset($variant->product->primaryMedia()->path) }}" alt="" loading="lazy">
                            @endif
                        </a>
                        <div class="vp-cart-what">
                            <a class="vp-cart-name" href="{{ storefront_route('product', $variant->product) }}">{{ $variant->product?->title }}</a>

                            <div class="vp-cart-money">
                                @if ($line['offer'])
                                    <strong>{{ toman($line['line_total']) }} <span>تومان</span></strong>
                                @else
                                    <strong>—</strong>
                                @endif
                            </div>

                            <span class="vp-cart-spec">سایز: {{ fa_number((int) $variant->size_value) }}</span>

                            <div class="vp-cart-last">
                                <span class="vp-cart-spec">رنگ: {{ $variant->display_color }}</span>

                                <div class="vp-cart-tools">
                                    {{-- The same stepper as the page's card. Both of its
                                         forms post to `cart.update`, which returns to the
                                         page they were pressed on — see CartController;
                                         before that it always landed on the basket page,
                                         which from inside a drawer means being thrown off
                                         whatever you were reading to change a quantity by
                                         one. --}}
                                    <div class="vp-cart-qty">
                                        <form method="post" action="{{ storefront_route('cart.update') }}">
                                            @csrf
                                            <input type="hidden" name="variant" value="{{ $variant->id }}">
                                            <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                                            <input type="hidden" name="quantity" value="{{ $line['quantity'] - 1 }}">
                                            <button type="submit" class="vp-cart-less" aria-label="یکی کمتر" @disabled($line['quantity'] <= 1)>&minus;</button>
                                        </form>
                                        <span class="vp-cart-count" aria-label="تعداد">{{ fa_number($line['quantity']) }}</span>
                                        <form method="post" action="{{ storefront_route('cart.update') }}">
                                            @csrf
                                            <input type="hidden" name="variant" value="{{ $variant->id }}">
                                            <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                                            <input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}">
                                            <button type="submit" class="vp-cart-more" aria-label="یکی بیشتر" @disabled($line['quantity'] >= $line['available'])>+</button>
                                        </form>
                                    </div>

                                    {{-- «کنارش با فاصله یه آیکون مقایسه بیاد» — past the stepper,
                                         at the card's own far edge, level with the bin above it.
                                         The exact mark the client sent (assets/img/icon/vp-compare.png),
                                         not a font-icon guess at it — see theme/make-*.js for how it
                                         was cut from the reference. The same honesty `.vp-best-fav`'s
                                         own heart states: there is no compare feature behind it yet,
                                         no page it goes to and nothing it stores. --}}
                                    <button type="button" class="vp-cart-compare" aria-label="مقایسه {{ $variant->product?->title }}"><img src="{{ asset('assets/img/icon/vp-compare.png') }}" alt="" aria-hidden="true"></button>
                                </div>
                            </div>

                            @if ($line['offer'] === null)
                                <span class="vp-cart-warn">دیگر موجود نیست</span>
                            @elseif ($line['available'] < $line['quantity'])
                                <span class="vp-cart-warn">فقط {{ fa_number($line['available']) }} عدد موجود است</span>
                            @endif
                        </div>

                        <form method="post" action="{{ storefront_route('cart.remove') }}">
                            @csrf
                            <input type="hidden" name="variant" value="{{ $variant->id }}">
                            <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                            <button type="submit" class="vp-cart-drop" aria-label="حذف {{ $variant->product?->title }}">
                                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                            </button>
                        </form>
                    </li>
                    @endforeach
                </ul>
                <div class="vp-mini-foot">
                    {{-- Goods only. Delivery is decided at the checkout and this panel
                         does not quote a number the checkout might then change. --}}
                    <div class="vp-mini-sum"><span>جمع کالاها</span><span>{{ toman($miniCart->subtotal()) }} تومان</span></div>
                    <a class="vp-mini-go" href="{{ storefront_route('cart') }}">مشاهده سبد</a>
                    <a class="vp-mini-pay" href="{{ storefront_route('checkout') }}">تسویه حساب</a>
                </div>
                @endif
            </div>
        </div>
    </div>
