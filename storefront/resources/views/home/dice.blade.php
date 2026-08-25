{{--
    «تاس شانس» — the game band.

    Hand-owned, and kept in step with theme/make-rtl-page.js's DICE_BAND by
    hand, the way the other bands are. `check-parity.js` is what notices if the
    two come apart.

    **The opening state is all that is here.** The result card is built by the
    script from what the server answers, so this markup is identical for every
    visitor — which is the only reason the static preview and this page can be
    compared pixel for pixel at all.

    The band is drawn only when the game is switched on: `config('storefront.game.enabled')`.
    A button for a game nobody is running is worse than no button.
--}}
@if (config('storefront.game.enabled'))
    {{-- The three things the script needs and cannot guess: where to throw,
         where «استفاده از تخفیف» goes, and the token that lets the POST through.
         The static preview carries none of them and falls back to saying the
         throw did not work, which is the truth there — it has no server. --}}
    <section class="vp-dice-area" id="vp-dice"
             data-dice-url="{{ storefront_route('game.dice') }}"
             data-shop-url="{{ storefront_route('shop') }}"
             data-dice-token="{{ csrf_token() }}">
        <div class="container th-container">
            <div class="vp-dice-card">
                <h2 class="vp-dice-title">تاس شانس، بنداز و ببر!</h2>
                <p class="vp-dice-say">روی دکمه شروع بزن تا تاس‌ها بچرخن؛<br>جفت شیش بیاد، جایزه‌ات فعال می‌شه</p>
                <div class="vp-dice-pair" data-dice-pair>
                        <span class="vp-die" data-face="3" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        <span class="vp-die" data-face="5" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                </div>
                <button type="button" class="vp-dice-go" data-dice-go>شروع بازی</button>
                {{-- The promise and the rule come from the same place, so the band
                     cannot advertise a number of throws that config does not give. --}}
                <p class="vp-dice-foot">هر کاربر {{ fa_number(config('storefront.game.tries')) }} بار شانس داره</p>
            </div>
        </div>
    </section>
@endif
