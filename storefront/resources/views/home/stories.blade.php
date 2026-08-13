{{--
    Five story circles between the header and the hero.

    Phone only — the CSS hides the strip above 991.98, so the desktop page is
    unchanged. Asked for as a look to try: «۵ تا حالت استوری دایره ای بزار
    بالای هیرو و زیر هدر ببینیم چطور میشه».

    The five are the catalogue's own first five sections, with the photographs
    and names the tiles under the hero already use — the same `$categories` the
    tiles read, so the strip and the tiles cannot describe two different shops.

    Hand-owned: theme/make-blade.js does not generate this file.
--}}
<section class="vp-stories" aria-label="دسته‌بندی‌ها">
        <div class="vp-stories-row">
            @foreach ($categories->take(5) as $category)
            {{-- No caption under the circle. The name becomes the link's
                 accessible name rather than being dropped — a link whose whole
                 content is a decorative photograph announces nothing. --}}
            <a class="vp-story" href="{{ storefront_route('category', $category) }}" aria-label="{{ $category->name }}">
                <span class="vp-story-ring">
                    <img src="{{ asset($category->image_path) }}" alt="" loading="lazy">
                </span>
            </a>
            @endforeach
        </div>
    </section>
