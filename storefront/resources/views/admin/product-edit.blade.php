@extends('layouts.admin')

{{--
    One product: what it is, what sizes it comes in, and its photographs.

    Adding a size does two things at once and says so — it creates the SKU for
    the whole platform and opens it for sale at this branch, at a price typed
    here. A SKU with no offer anywhere would be invisible, and invisible in a
    way nobody finds for a week.
--}}

@section('content')
<div class="vp-shop-head vp-admin-title">
    <h1 class="vp-shop-title">{{ $product->exists ? $product->title : 'محصول تازه' }}</h1>
    <a class="vp-cart-keep" href="{{ route('admin.catalogue') }}">بازگشت به کاتالوگ</a>
</div>

<div class="vp-admin-cols">
    <section class="vp-shop-panel">
        <h2 class="vp-shop-title">مشخصات</h2>

        <form class="vp-checkout" method="post" action="{{ $product->exists ? route('admin.product.update', $product) : route('admin.product.store') }}">
            @csrf

            <div class="vp-field">
                <label for="p-title">نام</label>
                <input id="p-title" name="title" value="{{ old('title', $product->title) }}" required maxlength="160">
            </div>

            <div class="vp-field-row">
                <div class="vp-field">
                    <label for="p-short">نام کوتاه</label>
                    <input id="p-short" name="short_title" value="{{ old('short_title', $product->short_title) }}" maxlength="80">
                </div>
                <div class="vp-field">
                    <label for="p-brand">برند</label>
                    <select id="p-brand" name="brand_id" class="vp-admin-pick">
                        <option value="">—</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="vp-field">
                <span class="vp-field-label">دسته‌بندی</span>
                <div class="vp-filter-sizes">
                    @foreach ($categories as $category)
                        <label class="vp-size">
                            <input type="checkbox" name="categories[]" value="{{ $category->id }}"
                                   @checked($product->exists && $product->categories->contains($category->id))>
                            <span>{{ $category->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="vp-field">
                <label for="p-desc">توضیح</label>
                <textarea id="p-desc" name="description" rows="4" maxlength="4000">{{ old('description', $product->description) }}</textarea>
            </div>

            <div class="vp-field-row">
                <div class="vp-field">
                    <label for="p-material">جنس</label>
                    <input id="p-material" name="material" value="{{ old('material', $product->material) }}" maxlength="120">
                </div>
                <div class="vp-field">
                    <label for="p-use">مناسب برای</label>
                    <input id="p-use" name="use_case" value="{{ old('use_case', $product->use_case) }}" maxlength="120">
                </div>
            </div>

            <div class="vp-field">
                <label for="p-care">نگهداری</label>
                <input id="p-care" name="care_instructions" value="{{ old('care_instructions', $product->care_instructions) }}" maxlength="400">
            </div>

            <div class="vp-field-row">
                <div class="vp-field">
                    <label for="p-status">وضعیت</label>
                    <select id="p-status" name="status" class="vp-admin-pick">
                        <option value="active" @selected(old('status', $product->status) === 'active')>فعال</option>
                        <option value="inactive" @selected(old('status', $product->status) === 'inactive')>غیرفعال</option>
                    </select>
                </div>
                <div class="vp-field">
                    <label for="p-published">تاریخ انتشار</label>
                    {{-- Empty means unpublished: `purchasable()` asks for a date in
                         the past, so a product with none never reaches the shop. --}}
                    <input id="p-published" type="date" name="published_at"
                           value="{{ old('published_at', $product->published_at?->format('Y-m-d')) }}">
                </div>
            </div>

            <button type="submit" class="vp-filter-apply vp-cart-go">{{ $product->exists ? 'ثبت' : 'ساختن محصول' }}</button>
        </form>
    </section>

    <div>
        @if ($product->exists)
            <section class="vp-shop-panel">
                <h2 class="vp-shop-title">عکس‌ها</h2>

                @if ($product->media->isEmpty())
                    <p class="vp-shop-empty">هنوز عکسی ندارد.</p>
                @else
                    <div class="vp-media">
                        @foreach ($product->media as $shot)
                            <figure @class(['vp-media-one', 'is-primary' => $shot->is_primary])>
                                <img src="{{ asset($shot->path) }}" alt="">
                                <figcaption>
                                    @unless ($shot->is_primary)
                                        <form method="post" action="{{ route('admin.product.media.primary', [$product, $shot]) }}">
                                            @csrf
                                            <button type="submit">اصلی</button>
                                        </form>
                                    @else
                                        <span>اصلی</span>
                                    @endunless
                                    <form method="post" action="{{ route('admin.product.media.delete', [$product, $shot]) }}">
                                        @csrf
                                        <button type="submit" class="is-off">حذف</button>
                                    </form>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif

                <form class="vp-admin-inline vp-media-add" method="post" action="{{ route('admin.product.media.store', $product) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="photo" accept="image/*" required>
                    <button type="submit" class="vp-admin-save">بارگذاری</button>
                </form>
            </section>

            <section class="vp-shop-panel">
                <h2 class="vp-shop-title">سایزها</h2>
                <p class="vp-shop-count">افزودن سایز، آن را در «{{ $branch->name }}» هم برای فروش باز می‌کند.</p>

                @if ($product->variants->isEmpty())
                    <p class="vp-shop-empty">هنوز سایزی ندارد، پس در فروشگاه دیده نمی‌شود.</p>
                @else
                    <table class="vp-admin-table is-wide">
                        <thead><tr><th>سایز</th><th>رنگ</th><th>کد</th><th>قیمت اینجا</th><th>موجودی</th><th>وضعیت</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($product->variants->sortBy('size_value', SORT_NATURAL) as $variant)
                            <tr>
                                <td>{{ fa_number((int) $variant->size_value) }}</td>
                                <td>{{ $variant->display_color }}</td>
                                <td>{{ $variant->sku }}</td>
                                <td>{{ $variant->offer ? toman($variant->offer->price) : '— فروخته نمی‌شود' }}</td>
                                <td>{{ $variant->stock ? fa_number($variant->stock->stock_on_hand) : '—' }}</td>
                                <td>{{ $variant->status === 'active' ? 'فعال' : 'بازنشسته' }}</td>
                                <td>
                                    <form method="post" action="{{ route('admin.product.variants.retire', [$product, $variant]) }}">
                                        @csrf
                                        <button type="submit" class="vp-admin-save">{{ $variant->status === 'active' ? 'بازنشسته کن' : 'برگردان' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif

                <form class="vp-checkout vp-variant-add" method="post" action="{{ route('admin.product.variants.store', $product) }}">
                    @csrf
                    <div class="vp-field">
                        <label for="v-size">سایز</label>
                        <input id="v-size" name="size_value" value="{{ old('size_value') }}" required maxlength="16" inputmode="numeric">
                    </div>
                    <div class="vp-field">
                        <label for="v-colour">رنگ</label>
                        <input id="v-colour" name="display_color" value="{{ old('display_color', 'نامشخص') }}" required maxlength="60">
                    </div>
                    <div class="vp-field">
                        <label for="v-family">گروه رنگ</label>
                        <input id="v-family" name="color_family" value="{{ old('color_family', 'unspecified') }}" required maxlength="60">
                    </div>
                    <div class="vp-field">
                        <label for="v-price">قیمت (تومان)</label>
                        <input id="v-price" name="price" value="{{ old('price') }}" required inputmode="numeric">
                    </div>
                    <div class="vp-field">
                        <label for="v-compare">قبل از تخفیف</label>
                        <input id="v-compare" name="compare_at_price" value="{{ old('compare_at_price') }}" inputmode="numeric">
                    </div>
                    <div class="vp-field">
                        <label for="v-stock">موجودی</label>
                        <input id="v-stock" type="number" min="0" name="stock_on_hand" value="{{ old('stock_on_hand', 1) }}" required>
                    </div>
                    <button type="submit" class="vp-admin-save">افزودن سایز</button>
                </form>
            </section>
        @else
            <section class="vp-shop-panel">
                <p class="vp-shop-empty">اول محصول را بساز؛ بعد سایز و عکس اضافه می‌شود.</p>
            </section>
        @endif
    </div>
</div>
@endsection
