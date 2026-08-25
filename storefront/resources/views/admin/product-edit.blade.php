@extends('layouts.admin')

@section('title', $product->exists ? $product->title : 'محصول تازه')

{{--
    One product: what it is, what sizes it comes in, and its photographs.

    Adding a size does two things at once and says so — it creates the SKU for
    the whole platform and opens it for sale at this branch, at a price typed
    here. A SKU with no offer anywhere would be invisible, and invisible in a
    way nobody finds for a week.

    The three cards are in the order the work is done: the product has to exist
    before it can have a size, and it needs a size before it can be sold. Until
    it is saved once the right-hand column says so rather than showing forms
    that would have nothing to attach to.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">
        @if ($product->exists)
            <bdi dir="ltr">{{ $product->slug }}</bdi>
        @else
            بعد از ثبت، سایز و عکس اضافه می‌شود.
        @endif
    </p>

    <div class="vp-adm-head-side">
        <a class="vp-adm-clear" href="{{ route('admin.catalogue') }}">بازگشت به کاتالوگ</a>
    </div>
</div>

<div class="vp-adm-grid">

    <section class="vp-adm-card vp-adm-span-2">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">مشخصات</h2>
        </div>

        <form class="vp-adm-form" method="post" action="{{ $product->exists ? route('admin.product.update', $product) : route('admin.product.store') }}">
            @csrf

            <label for="p-title">نام</label>
            <input id="p-title" name="title" value="{{ old('title', $product->title) }}" required maxlength="160">

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="p-short">نام کوتاه</label>
                    <input id="p-short" name="short_title" value="{{ old('short_title', $product->short_title) }}" maxlength="80">
                </div>
                <div class="vp-adm-form">
                    <label for="p-brand">برند</label>
                    <select id="p-brand" name="brand_id">
                        <option value="">بدون برند</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <span class="vp-adm-form-label">دسته‌بندی</span>
            <div class="vp-adm-weekdays">
                @foreach ($categories as $category)
                    <label>
                        <input type="checkbox" name="categories[]" value="{{ $category->id }}"
                               @checked($product->exists && $product->categories->contains($category->id))>
                        <span>{{ $category->name }}</span>
                    </label>
                @endforeach
            </div>

            <label for="p-desc">توضیح</label>
            <textarea id="p-desc" name="description" rows="4" maxlength="4000">{{ old('description', $product->description) }}</textarea>

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="p-material">جنس</label>
                    <input id="p-material" name="material" value="{{ old('material', $product->material) }}" maxlength="120">
                </div>
                <div class="vp-adm-form">
                    <label for="p-use">مناسب برای</label>
                    <input id="p-use" name="use_case" value="{{ old('use_case', $product->use_case) }}" maxlength="120">
                </div>
            </div>

            <label for="p-care">نگهداری</label>
            <input id="p-care" name="care_instructions" value="{{ old('care_instructions', $product->care_instructions) }}" maxlength="400">

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="p-status">وضعیت</label>
                    <select id="p-status" name="status">
                        <option value="active" @selected(old('status', $product->status) === 'active')>فعال</option>
                        <option value="inactive" @selected(old('status', $product->status) === 'inactive')>غیرفعال</option>
                    </select>
                </div>
                <div class="vp-adm-form">
                    <label for="p-published">تاریخ انتشار</label>
                    {{-- Empty means unpublished: `purchasable()` asks for a date in
                         the past, so a product with none never reaches the shop. --}}
                    <input id="p-published" type="date" name="published_at"
                           value="{{ old('published_at', $product->published_at?->format('Y-m-d')) }}">
                </div>
            </div>

            <button type="submit" class="vp-adm-apply">{{ $product->exists ? 'ثبت' : 'ساختن محصول' }}</button>
        </form>
    </section>

    @if ($product->exists)
        <section class="vp-adm-card">
            <div class="vp-adm-card-head">
                <h2 class="vp-adm-card-title">عکس‌ها</h2>
            </div>

            @if ($product->media->isEmpty())
                <p class="vp-adm-empty">هنوز عکسی ندارد.</p>
            @else
                <div class="vp-adm-shots">
                    @foreach ($product->media as $shot)
                        <figure @class(['vp-adm-shot', 'is-primary' => $shot->is_primary])>
                            <img src="{{ asset($shot->path) }}" alt="">
                            <figcaption>
                                @unless ($shot->is_primary)
                                    <form method="post" action="{{ route('admin.product.media.primary', [$product, $shot]) }}">
                                        @csrf
                                        <button type="submit" class="vp-adm-mini is-quiet">اصلی کن</button>
                                    </form>
                                @else
                                    <span class="vp-adm-badge is-delivered">اصلی</span>
                                @endunless
                                <form method="post" action="{{ route('admin.product.media.delete', [$product, $shot]) }}">
                                    @csrf
                                    <button type="submit" class="vp-adm-mini is-bad">حذف</button>
                                </form>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif

            <form class="vp-adm-form" method="post" action="{{ route('admin.product.media.store', $product) }}" enctype="multipart/form-data">
                @csrf
                <label for="p-photo">عکس تازه</label>
                <input id="p-photo" type="file" name="photo" accept="image/*" required>
                <button type="submit" class="vp-adm-apply">بارگذاری</button>
            </form>
        </section>

        <section class="vp-adm-card vp-adm-span-3">
            <div class="vp-adm-card-head">
                <h2 class="vp-adm-card-title">سایزها</h2>
                <span class="vp-adm-card-more">افزودن سایز، آن را در «{{ $branch->name }}» هم برای فروش باز می‌کند</span>
            </div>

            @if ($product->variants->isEmpty())
                <p class="vp-adm-empty">هنوز سایزی ندارد، پس در فروشگاه دیده نمی‌شود.</p>
            @else
                <table class="vp-admin-table">
                    <thead><tr><th>سایز</th><th>رنگ</th><th>کد</th><th>قیمت اینجا</th><th>موجودی</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($product->variants->sortBy('size_value', SORT_NATURAL) as $variant)
                        <tr>
                            <td>{{ fa_number((int) $variant->size_value) }}</td>
                            <td>{{ $variant->display_color }}</td>
                            <td>{{ $variant->sku }}</td>
                            <td>
                                @if ($variant->offer)
                                    {{ toman($variant->offer->price) }}
                                @else
                                    <span class="vp-adm-badge is-cancelled">فروخته نمی‌شود</span>
                                @endif
                            </td>
                            <td>{{ $variant->stock ? fa_number($variant->stock->stock_on_hand) : 'ندارد' }}</td>
                            <td>
                                <span class="vp-adm-badge is-{{ $variant->status === 'active' ? 'delivered' : 'cancelled' }}">
                                    {{ $variant->status === 'active' ? 'فعال' : 'بازنشسته' }}
                                </span>
                            </td>
                            <td>
                                <form method="post" action="{{ route('admin.product.variants.retire', [$product, $variant]) }}">
                                    @csrf
                                    <button type="submit" class="vp-adm-mini is-quiet">{{ $variant->status === 'active' ? 'بازنشسته کن' : 'برگردان' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <form class="vp-adm-form vp-adm-add-row" method="post" action="{{ route('admin.product.variants.store', $product) }}">
                @csrf

                <div class="vp-adm-form">
                    <label for="v-size">سایز</label>
                    <input id="v-size" name="size_value" value="{{ old('size_value') }}" required maxlength="16" inputmode="numeric">
                </div>
                <div class="vp-adm-form">
                    <label for="v-colour">رنگ</label>
                    <input id="v-colour" name="display_color" value="{{ old('display_color', 'نامشخص') }}" required maxlength="60">
                </div>
                <div class="vp-adm-form">
                    <label for="v-family">گروه رنگ</label>
                    <input id="v-family" name="color_family" value="{{ old('color_family', 'unspecified') }}" required maxlength="60">
                </div>
                <div class="vp-adm-form">
                    <label for="v-price">قیمت (تومان)</label>
                    <input id="v-price" name="price" value="{{ old('price') }}" required inputmode="numeric">
                </div>
                <div class="vp-adm-form">
                    <label for="v-compare">قبل از تخفیف</label>
                    <input id="v-compare" name="compare_at_price" value="{{ old('compare_at_price') }}" inputmode="numeric">
                </div>
                <div class="vp-adm-form">
                    <label for="v-stock">موجودی</label>
                    <input id="v-stock" type="number" min="0" name="stock_on_hand" value="{{ old('stock_on_hand', 1) }}" required>
                </div>

                <button type="submit" class="vp-adm-apply">افزودن سایز</button>
            </form>
        </section>
    @else
        <section class="vp-adm-card">
            <p class="vp-adm-empty">اول محصول را بساز؛ بعد سایز و عکس اضافه می‌شود.</p>
        </section>
    @endif
</div>
@endsection
