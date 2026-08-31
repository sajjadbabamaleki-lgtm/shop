@extends('layouts.admin')

{{--
    Which products the front page shows.

    Five bands, one panel each, and every panel says the same three things:
    what is in it now, whether that is the shop's own choice or the design's
    default, and what may be added. The counts are the layout's rather than
    opinions — the sale row is drawn `row-cols-xl-5` and a deal is one shoe —
    so a sixth is refused here instead of wrapping the page somewhere nobody
    is looking.

    «پیش‌فرض» is a real state and is drawn as one: a band nobody has touched
    reads its list from `config/storefront.php`, and that list is printed under
    the badge, so an untouched panel never looks like an empty band.

    Every class here is one the panel already owns — `.vp-admin-table`,
    `.vp-admin-save` — because tweaks.css is 15,000 lines and a name invented
    in a hurry collides in silence. There is no «ثبت شد» banner and no error
    list either: `layouts.admin` draws both above `@yield('content')`, and a
    second copy is how one save comes to say «اضافه شد» twice.
--}}

@section('content')
<section class="vp-shop-panel vp-adm-band">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h1 class="vp-shop-title">صفحه اصلی</h1>
            <p class="vp-shop-count">انتخاب کن هر بخش صفحه اصلی چه محصولاتی را نشان بدهد</p>
        </div>
    </div>

    <p class="vp-shop-count">
        محصولات واردشده از باسلام خودبه‌خود به هیچ‌کدام از این بخش‌ها اضافه نمی‌شوند؛
        هر محصولی که اینجا انتخاب نشود فقط در فروشگاه دیده می‌شود.
    </p>
</section>

{{-- The stepped sale's switch.

     It is the first panel because it is the one that decides whether three of
     the bands below have anything to show at all — and because its absence is
     what put the shop's front page in a photograph: the sale was seeded with a
     four-week window, the window closed on its own, and nothing short of a
     deploy could reopen it.

     The state is read off the offers rather than off a setting, so this badge
     cannot say «روشن» over a shop that is showing no cut. Both buttons are
     always drawn — the one that would do nothing is the one that is already
     true, and hiding it would make the switch look like a status line. --}}
<section class="vp-shop-panel vp-adm-band">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h2 class="vp-shop-title">حراج پله‌ای</h2>
            <p class="vp-shop-count">
                @if ($saleIsOn)
                    روشن است. قیمت‌های خط‌خورده و نشان تخفیف روی سایت دیده می‌شوند.
                @else
                    خاموش است. سایت هیچ تخفیفی را تبلیغ نمی‌کند و سه بخش پایین خالی می‌مانند.
                @endif
            </p>
        </div>
    </div>

    <p class="vp-shop-count">
        این کلید هیچ قیمتی را عوض نمی‌کند؛ نه قیمتی که مشتری می‌پردازد و نه قیمت
        خط‌خورده. فقط تعیین می‌کند فروشگاه تخفیف را تبلیغ بکند یا نه، و تا وقتی
        خودت عوضش نکنی همان‌طور می‌ماند. روی همه شعبه‌ها اعمال می‌شود.
    </p>

    <div class="vp-admin-inline">
        <form method="post" action="{{ route('admin.front-page.sale') }}">
            @csrf
            <input type="hidden" name="state" value="on">
            <button type="submit" class="vp-admin-save" @disabled($saleIsOn)>روشن کن</button>
        </form>

        <form method="post" action="{{ route('admin.front-page.sale') }}">
            @csrf
            <input type="hidden" name="state" value="off">
            <button type="submit" class="vp-admin-save is-off" @disabled(! $saleIsOn)>خاموش کن</button>
        </form>
    </div>
</section>

@foreach ($bands as $band)
<section class="vp-shop-panel vp-adm-band">
    <div class="vp-shop-head">
        <div class="vp-shop-heading">
            <h2 class="vp-shop-title">{{ $band['label'] }}</h2>
            <p class="vp-shop-count">
                @if ($band['isDefault'])
                    پیش‌فرض: {{ implode('، ', $band['defaults']) ?: 'خالی' }}
                @else
                    {{ fa_number(count($band['placements'])) }} از {{ fa_number($band['max']) }} جای موجود
                @endif
            </p>
            @if ($band['note'])
                <p class="vp-shop-count">{{ $band['note'] }}</p>
            @endif
        </div>
    </div>

    @unless ($band['isDefault'])
        <table class="vp-admin-table">
            <thead><tr><th>ترتیب</th><th>محصول</th>@if ($band['captioned'])<th>{{ $band['captionLabel'] }}</th>@endif<th></th></tr></thead>
            <tbody>
            @foreach ($band['placements'] as $placement)
                <tr>
                    <td>{{ fa_number($loop->iteration) }}</td>
                    <td>{{ $placement->product?->title ?? 'حذف شده' }}</td>
                    @if ($band['captioned'])
                        <td>{{ $placement->caption ?: 'ندارد' }}</td>
                    @endif
                    <td>
                        <div class="vp-admin-inline">
                            @if (! $loop->first)
                                <form method="post" action="{{ route('admin.front-page.move', $placement) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="vp-admin-save" aria-label="بالاتر">بالا</button>
                                </form>
                            @endif

                            @if (! $loop->last)
                                <form method="post" action="{{ route('admin.front-page.move', $placement) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="vp-admin-save" aria-label="پایین‌تر">پایین</button>
                                </form>
                            @endif

                            <form method="post" action="{{ route('admin.front-page.remove', $placement) }}">
                                @csrf
                                <button type="submit" class="vp-admin-save is-off">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endunless

    <div class="vp-admin-inline">
        <form class="vp-admin-inline" method="post" action="{{ route('admin.front-page.add', $band['key']) }}">
            @csrf
            <select name="product" class="vp-admin-pick" aria-label="محصول">
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">{{ $product->title }}</option>
                @endforeach
            </select>
            @if ($band['captioned'])
                {{-- Optional on purpose: left empty the slide falls back to the
                     file's line for that product, and then to its name, rather
                     than opening the shop with a blank space over the title. --}}
                <input type="text" name="caption" class="vp-admin-pick" maxlength="120"
                       placeholder="{{ $band['captionLabel'] }}" aria-label="{{ $band['captionLabel'] }}">
            @endif
            <button type="submit" class="vp-admin-save">افزودن</button>
        </form>

        @unless ($band['isDefault'])
            <form method="post" action="{{ route('admin.front-page.reset', $band['key']) }}">
                @csrf
                <button type="submit" class="vp-admin-save is-off">برگرداندن به پیش‌فرض</button>
            </form>
        @endunless
    </div>
</section>
@endforeach
@endsection
