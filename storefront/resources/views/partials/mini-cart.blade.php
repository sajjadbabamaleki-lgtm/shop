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
                    <li class="vp-mini-line">
                        <a class="vp-mini-shot" href="{{ storefront_route('product', $variant->product) }}">
                            @if ($variant->product?->primaryMedia())
                                <img src="{{ asset($variant->product->primaryMedia()->path) }}" alt="" loading="lazy">
                            @endif
                        </a>
                        <div class="vp-mini-what">
                            <a class="vp-mini-name" href="{{ storefront_route('product', $variant->product) }}">{{ $variant->product?->title }}</a>
                            <span class="vp-mini-meta">{{ fa_number($line['quantity']) }} × سایز {{ fa_number((int) $variant->size_value) }}</span>
                            @if ($line['offer'] === null)
                                <span class="vp-mini-warn">دیگر موجود نیست</span>
                            @elseif ($line['available'] < $line['quantity'])
                                <span class="vp-mini-warn">فقط {{ fa_number($line['available']) }} عدد موجود است</span>
                            @endif
                        </div>
                        <div class="vp-mini-money">
                            @if ($line['offer'])
                                <strong>{{ toman($line['line_total']) }} <span>تومان</span></strong>
                            @else
                                <strong>—</strong>
                            @endif
                        </div>
                        <form method="post" action="{{ storefront_route('cart.remove') }}">
                            @csrf
                            <input type="hidden" name="variant" value="{{ $variant->id }}">
                            <input type="hidden" name="vendor" value="{{ $line['item']->vendor_id }}">
                            <button type="submit" class="vp-mini-drop" aria-label="حذف {{ $variant->product?->title }}">&times;</button>
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
