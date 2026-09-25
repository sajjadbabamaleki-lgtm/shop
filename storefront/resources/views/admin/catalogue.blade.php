@extends('layouts.admin')

@section('title', 'محصولات')

{{--
    The catalogue — §5's product list.

    Not a branch's: a product, its sizes and its photographs are the same in
    every shop, and it is the price and the stock that belong to a branch. So
    this screen is the platform's and the two beside it — «موجودی» and
    «قیمت‌ها» — are this branch's, which is the difference worth noticing
    before editing anything here.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">محصول، سایز و عکس؛ مشترک بین همه شعبه‌ها</p>

    <div class="vp-adm-head-side">
        <form id="vp-cat-filter" class="vp-adm-filters" method="get" action="{{ route('admin.catalogue') }}" role="search">
            <label class="visually-hidden" for="vp-cat-q">جست‌وجو</label>
            <input id="vp-cat-q" class="vp-adm-search" type="search" name="q" value="{{ $q }}" placeholder="نام محصول">
            <button type="submit" class="vp-adm-apply">جست‌وجو</button>
            @if ($q !== '' || $section > 0)
                <a class="vp-adm-clear" href="{{ route('admin.catalogue') }}">پاک کردن</a>
            @endif
        </form>

        <a class="vp-adm-apply" href="{{ route('admin.product.create') }}">محصول تازه</a>
    </div>
</div>

<section class="vp-adm-card">
    {{-- The section to narrow by. Part of the search form above — it carries
         `form="vp-cat-filter"` — but drawn here, because that row was made to
         fit a telephone on one line at the shop's word and a third control
         would put it back on two. Choosing one submits on its own; without a
         script, «جست‌وجو» sends it with the name. --}}
    <div class="vp-adm-filters">
        <label for="vp-cat-section">دسته</label>
        <select id="vp-cat-section" name="category" form="vp-cat-filter" onchange="this.form.submit()">
            <option value="">همه دسته‌ها</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($section === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($products->isEmpty())
        {{-- Two different sentences, because they are two different situations:
             a catalogue with nothing in it, and a search that matched nothing. --}}
        <p class="vp-adm-empty">
            @if ($q === '' && $section === 0)
                هنوز محصولی ثبت نشده. با «محصول تازه» اولی را بساز.
            @else
                محصولی با این نام یا در این دسته پیدا نشد.
            @endif
        </p>
    @else
        {{-- «ب صورت دسته‌ای بتونیم انتقالشون بدیم به دسته بندی های دیگه مثل
             حراج پله ای، و ب صورت دسته‌ای هم بتونیم ناموجود کنیمشون».

             The same bulk bar the orders list has, and for the same reasons
             it is in the document always: the row boxes are ordinary
             checkboxes in an ordinary form, and only «انتخاب همه» needs a
             script. The section select is read by «انتقال» and «افزودن» and
             ignored by the other two. --}}
        <form method="post" action="{{ route('admin.catalogue.bulk') }}"
              onsubmit="var a=this.elements.namedItem('action').value; return a!=='out' || confirm('موجودی همهٔ سایزهای محصولات انتخاب‌شده در این شعبه صفر می‌شود. در فهرست و جست‌وجو می‌مانند و «ناموجود» نشان داده می‌شوند.')">
            @csrf

            <div class="vp-adm-bulk">
                <label class="vp-adm-bulk-all" hidden>
                    <input type="checkbox" data-adm-all>
                    <span>انتخاب همه این صفحه</span>
                </label>

                <div class="vp-adm-bulk-go">
                    <label class="visually-hidden" for="vp-bulk-action">کار</label>
                    <select id="vp-bulk-action" name="action">
                        <option value="move">انتقال به دسته…</option>
                        <option value="add">افزودن به دسته… (دسته‌های قبلی می‌مانند)</option>
                        <option value="ladder">افزودن به حراج پله‌ای ({{ fa_number(max(0, $ladderRoom)) }} جای خالی)</option>
                        <option value="out">ناموجود کردن در این شعبه</option>
                    </select>

                    <label class="visually-hidden" for="vp-bulk-category">دسته مقصد</label>
                    <select id="vp-bulk-category" name="category">
                        <option value="">دسته مقصد</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="vp-adm-apply">اعمال روی انتخاب‌شده‌ها</button>
                </div>
            </div>

            <table class="vp-admin-table">
                <thead><tr><th></th><th>عکس</th><th>محصول</th><th>دسته</th><th>برند</th><th>سایزها</th><th>وضعیت</th><th>انتشار</th><th></th></tr></thead>
                <tbody>
                @foreach ($products as $product)
                    <tr>
                        <td>
                            <label class="visually-hidden" for="vp-p-{{ $product->id }}">انتخاب {{ $product->title }}</label>
                            <input id="vp-p-{{ $product->id }}" type="checkbox" name="products[]" value="{{ $product->id }}" data-adm-row>
                        </td>
                        <td>
                            @if ($shot = $product->primaryMedia())
                                <img class="vp-adm-row-shot" src="{{ asset($shot->path) }}" alt="" loading="lazy">
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.product.edit', $product) }}">{{ $product->title }}</a>
                            <span class="vp-adm-sub">{{ $product->slug }}</span>
                        </td>
                        <td>{{ $product->categories->pluck('name')->implode('، ') ?: 'ندارد' }}</td>
                        <td>{{ $product->brand?->name ?? 'ندارد' }}</td>
                        <td>{{ fa_number($product->variants_count) }}</td>
                        <td>
                            <span class="vp-adm-badge is-{{ $product->status === 'active' ? 'delivered' : 'cancelled' }}">
                                {{ $product->status === 'active' ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td>{{ $product->published_at ? fa_date($product->published_at) : 'منتشر نشده' }}</td>
                        <td><a class="vp-adm-mini is-quiet" href="{{ route('admin.product.edit', $product) }}">ویرایش</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </form>

        <div class="vp-adm-pager">{{ $products->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection
