{{--
    «برندهای موجود» — four brands, each a photo mosaic with a glass plate
    floating in it.

    The name and the mark are the brand's own, and so are the photographs of
    the three tiles the client has supplied a set for — Nike, Jordan and New
    Balance, each reading shoe, kit, athlete down the tile. The fourth still
    borrows the category photographs from the top of the page, because we hold
    one product photograph for that brand and a tile wants three. No count is
    real; they are invented outright.

    Photographs and counts alike come out of
    config('storefront.placeholders.brand_strip') rather than out of the
    catalogue, so an invented number never sits in the tables looking like a
    counted one.

    Only the Nike mark is real. The other three are the template's own
    abstract marks.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
{{-- `id` for the section bar in the header: «برندها» is the one link in
     it with no page of its own, because this band is the only place the
     shop lists its brands. An id paints nothing, so `check-parity.js`
     cannot see whether both copies of this page carry it — which is why it
     is written on both rather than only on the one somebody remembered. --}}
<section class="vp-brands-section space" id="brands">
        <div class="vp-brands-panel">
            <div class="vp-brands-head">
                <h2 class="vp-brands-title">برندهای موجود</h2>
                <a href="{{ page_url('shop.html') }}" class="vp-brands-all">مشاهده همه برندها</a>
            </div>
            <div class="vp-brands-row">
                @foreach ($brands as $tile)
                <a class="vp-brand" href="{{ storefront_route('shop', ['brand' => $tile['brand']->slug]) }}">
                    <span class="vp-brand-mosaic" aria-hidden="true">
                        @foreach ($tile['mosaic'] as $photo)
                        <span class="vp-brand-cell{{ $loop->first ? ' is-lead' : '' }}"><img src="{{ asset($photo) }}"{!! photo_srcset($photo) !!} alt="" loading="lazy"></span>
                        @endforeach
                    </span>
                    <span class="vp-brand-plate">
                        <img class="vp-brand-logo" src="{{ asset($tile['brand']->logo_path) }}" alt="" loading="lazy">
                        <span class="vp-brand-lines">
                            <span class="vp-brand-name">{{ $tile['brand']->name }}</span>
                            <span class="vp-brand-stock">{{ fa_number($tile['stock']) }} کالا موجود</span>
                        </span>
                    </span>
                </a>
                @endforeach
            </div>
        </div>
    </section>
