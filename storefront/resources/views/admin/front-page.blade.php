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
<section class="vp-shop-panel">
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

@foreach ($bands as $band)
<section class="vp-shop-panel">
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
        </div>
    </div>

    @unless ($band['isDefault'])
        <table class="vp-admin-table">
            <thead><tr><th>ترتیب</th><th>محصول</th><th></th></tr></thead>
            <tbody>
            @foreach ($band['placements'] as $placement)
                <tr>
                    <td>{{ fa_number($loop->iteration) }}</td>
                    <td>{{ $placement->product?->title ?? 'حذف شده' }}</td>
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
