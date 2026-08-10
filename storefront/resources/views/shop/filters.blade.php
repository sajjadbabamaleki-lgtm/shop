{{--
    The filter rail.

    One GET form, submitted by a button. Nothing here is applied by JavaScript:
    the URL is the state, which is what makes a filtered listing something you
    can send to somebody.

    Every control offers only values that would actually return something at
    this branch — the facets come from the branch's own sellable catalogue, so
    a franchise never shows a size it does not stock.

    A search term already typed rides along hidden, because narrowing a search
    by brand should narrow the search and not replace it.
--}}
<aside class="vp-shop-rail">
    <form method="get" action="{{ storefront_route('shop') }}">
        @if ($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">

        <div class="vp-filter">
            <h2 class="vp-filter-title">دسته‌بندی</h2>
            <ul class="vp-filter-list">
                <li>
                    <label>
                        <input type="radio" name="category" value="" @checked(! $filters['category'])>
                        <span>همه</span>
                    </label>
                </li>
                @foreach ($facets['categories'] as $category)
                    <li>
                        <label>
                            <input type="radio" name="category" value="{{ $category->slug }}" @checked($filters['category']?->id === $category->id)>
                            <span>{{ $category->name }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="vp-filter">
            <h2 class="vp-filter-title">برند</h2>
            <ul class="vp-filter-list">
                <li>
                    <label>
                        <input type="radio" name="brand" value="" @checked(! $filters['brand'])>
                        <span>همه</span>
                    </label>
                </li>
                @foreach ($facets['brands'] as $brand)
                    <li>
                        <label>
                            <input type="radio" name="brand" value="{{ $brand->slug }}" @checked($filters['brand'] === $brand->slug)>
                            <span>{{ $brand->name }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($facets['sizes']->isNotEmpty())
            <div class="vp-filter">
                <h2 class="vp-filter-title">سایز</h2>
                <div class="vp-filter-sizes">
                    @foreach ($facets['sizes'] as $size)
                        <label class="vp-size{{ $filters['size'] === $size ? ' is-on' : '' }}">
                            {{-- Clicking the size already chosen clears it, which is what
                                 a pressed button that stays pressed should do. --}}
                            <input type="radio" name="size" value="{{ $filters['size'] === $size ? '' : $size }}" @checked($filters['size'] === $size)>
                            <span>{{ fa_number((int) $size) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($facets['colors']->isNotEmpty())
            <div class="vp-filter">
                <h2 class="vp-filter-title">رنگ</h2>
                <ul class="vp-filter-list">
                    @foreach ($facets['colors'] as $color)
                        <li>
                            <label>
                                <input type="radio" name="color" value="{{ $color }}" @checked($filters['color'] === $color)>
                                <span>{{ $color }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($facets['price']['min'] !== null)
            <div class="vp-filter">
                <h2 class="vp-filter-title">قیمت (تومان)</h2>
                <div class="vp-filter-price">
                    {{-- inputmode numeric rather than type=number: the placeholder is
                         written in Persian digits and a number field would refuse
                         them. What is typed is folded to ASCII on the way in. --}}
                    <input type="text" inputmode="numeric" name="min" value="{{ $filters['min'] ? fa_number(intdiv($filters['min'], 10)) : '' }}" placeholder="از {{ toman($facets['price']['min']) }}" aria-label="کمترین قیمت">
                    <span aria-hidden="true">تا</span>
                    <input type="text" inputmode="numeric" name="max" value="{{ $filters['max'] ? fa_number(intdiv($filters['max'], 10)) : '' }}" placeholder="تا {{ toman($facets['price']['max']) }}" aria-label="بیشترین قیمت">
                </div>
            </div>
        @endif

        <div class="vp-filter-actions">
            <button type="submit" class="vp-filter-apply">اعمال فیلترها</button>
            <a href="{{ storefront_route('shop') }}" class="vp-filter-clear">حذف همه</a>
        </div>
    </form>
</aside>
