{{--
    The hero deck: three products, each run twice, so it reads as a loop of
    three rather than six of anything.

    The deck shows 83px of the neighbouring cards at each margin at every
    width. That is the template working as designed and it is wanted — it has
    been cut twice and put back twice. See «همسایه» in CLAUDE.md before
    touching the slider options below.

    The «25% OFF» on the burst is the template's own and is not the product's:
    nothing reads it from the catalogue, which is why it is written here and
    not passed in. The stepped sale further down the page is where a real cut
    is drawn.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
<div class="th-hero-wrapper hero-6 slider-area" id="hero">

        <div class="slider-area">
            <div class="vp-hero-marks" aria-hidden="true"><i class="m-fall"></i><i class="m-near"></i><i class="m-far"></i></div>
            <div dir="rtl" class="swiper th-slider heroSlide6" id="heroSlide6" data-slider-options='{"centeredSlides":true,"centeredSlidesBounds":true,"spaceBetween":24,"breakpoints":{"0":{"slidesPerView":1},"576":{"slidesPerView":"1"},"768":{"slidesPerView":"1"},"992":{"slidesPerView":"2"},"1200":{"slidesPerView":"2"}}}'>
                <div dir="rtl" class="swiper-wrapper">
                    @foreach ($heroSlides as $slide)
                    <div dir="rtl" class="swiper-slide">
                        <div class="hero-inner">
                            <div class="container th-container">
                                <div class="row align-items-center">
                                    <div class="col-lg-6">
                                        <div class="hero-style6">
                                            <span class="sub-title" data-ani="slideinleft" data-ani-delay="0.2s">{{ $slide['eyebrow'] }}</span>
                                            <h1 class="hero-title" data-ani="slideinleft" data-ani-delay="0.4s">
                                                {{ $slide['kind'] }}<br>{{ $slide['model'] }} </h1>
                                            <div class="btn-group" data-ani="slideinup" data-ani-delay="0.7s"><a href="{{ page_url('shop.html') }}" class="th-btn th-icon">خرید محصول</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="hero-img" data-ani="slideinrighthero" data-ani-delay="0.8s">
                                            <img src="{{ asset($slide['product']->imagePath()) }}"{!! photo_srcset($slide['product']->imagePath()) !!} alt="Image">
                                            {{-- The ٪۲۵ badge is gone from every slide.

                                                 «روی عکس‌های اسلایدر همشون ۲۵ درصد تخفیف خورده قسمت
                                                 تخفیفات از روی اسلایدر برداشته بشه». All six slides wore
                                                 the same number, which is not a sale — it is a decoration
                                                 that says one. The whole `.discount-wrapp` went, not just
                                                 the mark inside it: the wrapper is positioned over the
                                                 photograph and an empty one is an invisible box in the
                                                 corner of every slide.

                                                 «فقط می‌تونیم در یک اسلاید حراج پله‌ای رو تبلیغ کنیم» is
                                                 the option and not the instruction, so nothing replaces it
                                                 here. A slide that advertises the stepped sale is a design
                                                 decision to be looked at, not one to be invented.

                                                 The same removal is in theme/make-rtl-page.js for the
                                                 preview page; this file is hand-owned and make-blade.js
                                                 leaves it alone, so it has to be made in both. --}}
                                            <div class="hero6-shape" data-mask-src="{{ asset('assets/img/shape/hot.png') }}"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    @endforeach
                </div>

            </div>
            <button data-slider-prev="#heroSlide6" class="slider-arrow slider-prev"><i class="far fa-arrow-left"></i></button>
            <button data-slider-next="#heroSlide6" class="slider-arrow slider-next"><i class="far fa-arrow-right"></i></button>
        </div>
    </div>

@push('scripts')
    <script>
        (function () {
            var deck = document.querySelector("#heroSlide6");
            var marks = document.querySelector(".vp-hero-marks");
            if (!deck || !marks) return;
            var tones = {"vikyplus-hero-jordan.webp":"#DDC1BB","vikyplus-hero-goldengoose.webp":"#DDCEBB","vikyplus-hero-nb530.webp":"#BBCFDD"};
            function paint() {
                var shot = deck.querySelector(".swiper-slide-active .hero-img img");
                if (!shot) return;
                var tone = tones[shot.getAttribute("src").split("/").pop()];
                if (tone) marks.style.setProperty("--vp-mark", tone);
            }
            paint();
            var wrapper = deck.querySelector(".swiper-wrapper");
            if (wrapper && "MutationObserver" in window) {
                new MutationObserver(paint).observe(wrapper, {
                    subtree: true, attributes: true, attributeFilter: ["class"]
                });
            }
        }());
    </script>
@endpush
