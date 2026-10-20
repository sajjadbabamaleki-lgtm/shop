{{--
    The shop's stamp — «یه مهر حرفه‌ای هم برای ویکی پلاس درست کن», then, of
    three drawn, the rectangle: «یه ذره از چپ جمع‌تر بشه و بیشتر شکل مهر بشه».

    The shop's own mark (the same file as the header's logo, inked grey), the
    name, what it sells and the address, in a double rule that is only as wide
    as what is inside it. What makes it read as a rubber stamp rather than a
    box: a few degrees of tilt and `#vp-ink`, a turbulence filter that roughens
    the edges and the type the way ink takes on paper.
--}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <filter id="vp-ink">
        <feTurbulence type="fractalNoise" baseFrequency="1.1" numOctaves="1" seed="7" result="grain"/>
        <feDisplacementMap in="SourceGraphic" in2="grain" scale="0.9" xChannelSelector="R" yChannelSelector="G" result="rough"/>
        <feComponentTransfer in="grain" result="speck"><feFuncA type="discrete" tableValues="1 1 1 1 0.75 1 1 1 1 0.85"/></feComponentTransfer>
        <feComposite in="rough" in2="speck" operator="in"/>
    </filter>
</svg>
<div class="vp-stamp" role="img" aria-label="مهر ویکی پلاس">
    <img class="vp-stamp-mark" src="{{ asset('assets/img/vikyplus-appicon.png') }}" alt="">
    <div class="vp-stamp-words">
        <b>ویکی پلاس</b>
        <small>فروشگاه کیف و کفش زنانه</small>
        <i>vikyplus.ir</i>
    </div>
</div>
