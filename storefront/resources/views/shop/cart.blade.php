@extends('layouts.storefront')

{{--
    The basket.

    Prices here are read live from the branch's offers every time the page is
    drawn — the basket stores quantities and nothing else — so a total on this
    page is never yesterday's.

    A line the shop cannot supply is marked and kept, not dropped. The customer
    put it there; a basket that quietly loses things is worse than one that
    says what happened.
--}}

@section('content')
<section class="vp-shop-section">
    <div class="container th-container">
        <div class="vp-shop-panel">

            <div class="vp-shop-head">
                <div class="vp-shop-heading">
                    <h1 class="vp-shop-title">سبد خرید</h1>
                    <p class="vp-shop-count">{{ fa_number($cart->count()) }} کالا</p>
                </div>
                <a class="vp-cart-keep" href="{{ storefront_route('shop') }}">ادامه خرید</a>
            </div>

            @if (session('status'))
                <p class="vp-note is-good">{{ session('status') }}</p>
            @endif

            @foreach ($errors->all() as $error)
                <p class="vp-note is-bad">{{ $error }}</p>
            @endforeach

            @if ($cart->isEmpty())
                <div class="vp-empty">
                    <span class="vp-empty-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48"><path d="M10 16 h28 l-3 22 a3 3 0 0 1 -3 3 h-16 a3 3 0 0 1 -3 -3 z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path><path d="M18 16 a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                    </span>
                    <p class="vp-empty-say">سبد خریدت خالی است.</p>
                    <a class="vp-empty-out" href="{{ storefront_route('shop') }}">رفتن به فروشگاه</a>
                </div>
            @else
                @php $lines = $cart->lines(); @endphp

                <div class="vp-cart">
                    <div class="vp-cart-lines">
                        @foreach ($lines as $line)
                            @php
                                $variant = $line['item']->variant;
                                $short = $line['offer'] === null || $line['available'] < $line['quantity'];
                            @endphp
                            <div class="vp-cart-line{{ $short ? ' is-short' : '' }}">
                                <a class="vp-cart-shot" href="{{ storefront_route('product', $variant->product) }}">
                                    @if ($variant->product?->primaryMedia())
                                        <img src="{{ asset($variant->product->primaryMedia()->path) }}" alt="" loading="lazy">
                                    @endif
                                </a>

                                {{-- The reference's card, mirrored: name, then the price
                                     under it in the shop's gold, then the specification
                                     lines. Everything the customer reads is one stack
                                     beside the photograph, which is what makes the card
                                     scan top-to-bottom instead of corner-to-corner. --}}
                                <div class="vp-cart-what">
                                    <a class="vp-cart-name" href="{{ storefront_route('product', $variant->product) }}">{{ $variant->product?->title }}</a>

                                    <div class="vp-cart-money">
                                        @if ($line['offer'])
                                            <strong>{{ toman($line['line_total']) }} <span>تومان</span></strong>
                                            @if ($line['quantity'] > 1)
                                                <span class="vp-cart-each">هر عدد {{ toman($line['offer']->price) }}</span>
                                            @endif
                                        @else
                                            <strong>—</strong>
                                        @endif
                                    </div>

                                    @php
                                        // The reference's «Size: 7.5 / Color: Yellow», built
                                        // as a list so the stepper can always take the last
                                        // one's row.
                                        //
                                        // The colour is here only when there is a colourway
                                        // to name. Every variant in the catalogue is still
                                        // `unspecified`, and «رنگ: نامشخص» on every card is a
                                        // row that tells the customer nothing and reads as a
                                        // fault; the shop rail leaves its colour filter out
                                        // for the same reason and by the same test, and both
                                        // come back on their own when real colourways do.
                                        //
                                        // The seller is the same idea from the other end: a
                                        // line the shop sold through somebody else says so,
                                        // and a line the shop sold itself does not need a row
                                        // to say it.
                                        $specs = ['سایز: ' . fa_number((int) $variant->size_value)];

                                        if ($variant->color_family !== 'unspecified') {
                                            $specs[] = 'رنگ: ' . $variant->display_color;
                                        }

                                        if ($line['item']->vendor_id) {
                                            $specs[] = 'فروشنده: ' . $line['item']->sellerName();
                                        }

                                        $lastSpec = array_pop($specs);
                                    @endphp

                                    @foreach ($specs as $spec)
                                        <span class="vp-cart-spec">{{ $spec }}</span>
                                    @endforeach

                                    {{-- The last specification line and the stepper share the
                                         card's bottom row, as they do in the reference.

                                         The *last* one, whichever it turns out to be, rather
                                         than the colour by name: with no colourway the colour
                                         row is not drawn, and pinning the stepper to it left
                                         a card with a hole in the middle and the stepper
                                         floating under nothing.

                                         One flex row rather than the stepper being parked in
                                         the corner absolutely — a long colour name and an
                                         absolutely-placed stepper would eventually collide,
                                         and nothing would notice. --}}
                                    <div class="vp-cart-last">
                                        <span class="vp-cart-spec">{{ $lastSpec }}</span>

                                        {{-- A stepper, not a number box and an update button.
                                             Two one-button forms posting the quantity either
                                             side of the current one: no script, and nothing to
                                             press afterwards to make it count. Minus stops at 1
                                             — taking the last one out is the bin at the corner
                                             of the card, which says what it does. Plus stops at
                                             what the branch has on the shelf. --}}
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
                                    </div>

                                    @if ($line['offer'] === null)
                                        <span class="vp-cart-warn">این کالا دیگر در این شعبه فروخته نمی‌شود</span>
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
                            </div>
                        @endforeach
                    </div>

                    {{-- Only what is being decided here: how many, and how much.

                         «کلا اینجا یدونه فقط تعداد خرید باشه و جمع کل». What was
                         here also listed «جمع کالاها» and «هزینه ارسال» and carried
                         the discount field — and with delivery decided at the next
                         step and no code typed, «جمع کالاها» and «جمع کل» were the
                         same number printed twice.

                         The code field moved to the checkout rather than being
                         deleted: it was the only place on the storefront a discount
                         code could be typed, and taking it out here would have made
                         `discount_codes` unreachable. --}}
                    {{-- `is-island` is what makes this one float against the foot
                         of the phone in glass. The checkout's summary is the same
                         `.vp-cart-sum` and must *not* — «تو این صفحه ثبت سفارش این
                         شیشه ای اضافست»: there it was landing on top of the form,
                         over the mobile number field. So the treatment is asked for
                         by name here rather than inherited by anything that reuses
                         the block. --}}
                    @php
                        $payable = $cart->subtotal() - $discount['amount'] + $shipping;
                    @endphp

                    <aside class="vp-cart-sum is-island">
                        <div class="vp-cart-row"><span>جمع کالاها</span><span>{{ toman($cart->subtotal()) }} تومان</span></div>
                        <div class="vp-cart-row"><span>تخفیف</span><span>{{ toman($discount['amount']) }} تومان</span></div>
                        <div class="vp-cart-row">
                            <span>هزینه ارسال</span>
                            <span>{{ $shipping === 0 ? 'رایگان' : toman($shipping) . ' تومان' }}</span>
                        </div>

                        <div class="vp-cart-row is-total">
                            <span>مبلغ قابل پرداخت</span>
                            <span>{{ toman($payable) }} تومان</span>
                        </div>

                        @if ($cart->problems()->isNotEmpty())
                            <p class="vp-note is-bad">اول ردیف‌های مشخص‌شده را درست کن.</p>
                        @else
                            <a class="vp-filter-apply vp-cart-go" href="{{ storefront_route('checkout') }}">ادامه ({{ toman($payable) }} تومان)</a>
                        @endif
                    </aside>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
