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
                                            <span class="sub-title" data-ani="slideinleft" data-ani-delay="0.2s">{{ $slide['product']->title }}</span>
                                            <h1 class="hero-title" data-ani="slideinleft" data-ani-delay="0.4s">
                                                {{ $slide['kind'] }}<br>{{ $slide['model'] }} </h1>
                                            <div class="btn-group" data-ani="slideinup" data-ani-delay="0.7s"><a href="{{ page_url('shop.html') }}" class="th-btn th-icon">خرید محصول</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="hero-img" data-ani="slideinrighthero" data-ani-delay="0.8s">
                                            <img src="{{ asset($slide['product']->primaryMedia()->path) }}" alt="Image">
                                            <div class="discount-wrapp style2">
                                                <div class="discount-tag">
                                                    <svg class="vp-burst" viewBox="0 0 150 150" aria-hidden="true"><defs><linearGradient id="vp-burst-gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#C0972F"></stop><stop offset="100%" stop-color="#E3B54A"></stop></linearGradient></defs><g class="vp-burst-star"><path fill="url(#vp-burst-gold)" d="M 75,3 C 80.73,3 85.7,14.57 92.19,16.47 C 98.67,18.38 109.11,11.33 113.93,14.43 C 118.75,17.53 116.67,29.94 121.1,35.05 C 125.53,40.16 138.11,39.88 140.49,45.09 C 142.87,50.3 134.42,59.63 135.38,66.32 C 136.34,73.01 147.08,79.58 146.27,85.25 C 145.45,90.92 133.3,94.19 130.49,100.34 C 127.68,106.49 133.17,117.82 129.41,122.15 C 125.66,126.48 113.67,122.66 107.98,126.32 C 102.29,129.97 100.78,142.47 95.28,144.08 C 89.79,145.7 81.76,136 75,136 C 68.24,136 60.21,145.7 54.72,144.08 C 49.22,142.47 47.71,129.97 42.02,126.32 C 36.33,122.66 24.34,126.48 20.59,122.15 C 16.83,117.82 22.32,106.49 19.51,100.34 C 16.7,94.19 4.55,90.92 3.73,85.25 C 2.92,79.58 13.66,73.01 14.62,66.32 C 15.58,59.63 7.13,50.3 9.51,45.09 C 11.89,39.88 24.47,40.16 28.9,35.05 C 33.33,29.94 31.25,17.53 36.07,14.43 C 40.89,11.33 51.33,18.38 57.81,16.47 C 64.3,14.57 69.27,3 75,3 Z"></path><circle class="vp-burst-stud" cx="75.00" cy="19.50" r="2.4"></circle><circle class="vp-burst-stud" cx="105.01" cy="28.31" r="2.4"></circle><circle class="vp-burst-stud" cx="125.48" cy="51.94" r="2.4"></circle><circle class="vp-burst-stud" cx="129.94" cy="82.90" r="2.4"></circle><circle class="vp-burst-stud" cx="116.94" cy="111.34" r="2.4"></circle><circle class="vp-burst-stud" cx="90.64" cy="128.25" r="2.4"></circle><circle class="vp-burst-stud" cx="59.36" cy="128.25" r="2.4"></circle><circle class="vp-burst-stud" cx="33.06" cy="111.34" r="2.4"></circle><circle class="vp-burst-stud" cx="20.06" cy="82.90" r="2.4"></circle><circle class="vp-burst-stud" cx="24.52" cy="51.94" r="2.4"></circle><circle class="vp-burst-stud" cx="44.99" cy="28.31" r="2.4"></circle></g><text class="vp-burst-num" x="75" y="72">25%</text><text class="vp-burst-off" x="75" y="98">OFF</text></svg>
                                                </div>
                                            </div>
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
