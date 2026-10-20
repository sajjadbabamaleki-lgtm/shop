{{--
    The shop's stamp — «یه مهر حرفه‌ای هم برای ویکی پلاس درست کن».

    A round seal in one ink, the way a rubber stamp prints: a double ring, the
    shop's name and what it sells set round the band, the mark at the centre
    with the name in Latin and the address under it. Drawn, not a picture, so
    it takes the sheet's own Persian face and prints sharp at any size.
    `currentColor`, so the invoice decides the ink.
--}}
<svg class="vp-stamp" viewBox="0 0 160 160" role="img" aria-label="مهر ویکی پلاس">
    <defs>
        {{-- The band's text runs on these two arcs. `text-anchor: middle`
             does not centre a right-to-left run on a path in Chromium — it
             landed a third of the way round — so each line is placed by hand:
             its measured length at this size is fixed with `textLength`, and
             it starts at (arc − length) / 2. The top arc is π·56 = 175.9 and
             «ویکی پلاس» is 58.8; the bottom is π·49 = 153.9 and its line 107.1. --}}
        <path id="vp-stamp-top" d="M 24 80 A 56 56 0 0 1 136 80"/>
        <path id="vp-stamp-bottom" d="M 31 80 A 49 49 0 0 0 129 80"/>
    </defs>
    <g fill="none" stroke="currentColor">
        <circle cx="80" cy="80" r="76" stroke-width="3"/>
        <circle cx="80" cy="80" r="71" stroke-width="1"/>
        <circle cx="80" cy="80" r="42" stroke-width="1.2"/>
    </g>
    <g fill="currentColor">
        <text class="vp-stamp-band" font-size="13" font-weight="800" direction="ltr">
            <textPath href="#vp-stamp-top" startOffset="58.6" textLength="58.8" lengthAdjust="spacingAndGlyphs">ویکی پلاس</textPath>
        </text>
        <text class="vp-stamp-band" font-size="9.5" font-weight="700" direction="ltr" dominant-baseline="hanging">
            <textPath href="#vp-stamp-bottom" startOffset="23.4" textLength="107.1" lengthAdjust="spacingAndGlyphs">فروشگاه کیف و کفش زنانه</textPath>
        </text>
        {{-- Two stars where the band's two lines meet. --}}
        <text x="16" y="84" font-size="9" text-anchor="middle">★</text>
        <text x="144" y="84" font-size="9" text-anchor="middle">★</text>
        {{-- The mark, redrawn in outline from the gem in the logo. --}}
        <g transform="translate(80 66)" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
            <path d="M -15 -7 L -8 -14 L 8 -14 L 15 -7 L 0 12 Z"/>
            <path d="M -15 -7 L 15 -7 M -8 -14 L -3 -7 L 0 12 L 3 -7 L 8 -14"/>
        </g>
        <text x="80" y="95" font-size="10.5" font-weight="800" text-anchor="middle" letter-spacing="1.6" direction="ltr">VIKYPLUS</text>
        <text x="80" y="107" font-size="7.5" text-anchor="middle" letter-spacing="0.5" direction="ltr">vikyplus.ir</text>
    </g>
</svg>
