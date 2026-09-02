{{--
    The stepped sale, and the modal explaining it.

    The board, the track under it, the cut on every card and the board inside
    the modal all read the same five steps out of config('storefront.ladder'),
    so moving the sale on is one number and nothing on the page can disagree
    with anything else. A card's own cut comes from its variant's promotion,
    which the seeder set from that same live step.

    The prose in the modal is copy and stays written out.

    Hand-owned: theme/make-blade.js no longer regenerates this file.
--}}
@php
    $stepMarkLabels = ['done' => 'تکمیل شد', 'current' => 'مرحله فعلی', 'upcoming' => 'هنوز نرسیده'];

    // A tick for the step that has run, a loading ring for the one running
    // now, a clock for the ones still to come. Drawn rather than set in an
    // icon font: each takes the colour of the tile it sits in, and three
    // marks from one hand read as a set where three glyphs do not.
    $stepMarks = [
        'done' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.6 8.4 6.6 11.4 12.4 4.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
        'current' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="26 9"></circle></svg>',
        'upcoming' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.9" fill="none" stroke="currentColor" stroke-width="1.6"></circle><path d="M8 4.7 V8.2 L10.3 9.7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
    ];
@endphp
<section class="collection-area vp-ladder-area overflow-hidden">
        <div class="container th-container vp-ladder-wrap">
            <div class="vp-ladder">
                <div class="vp-ladder-head">
                    <div class="vp-ladder-intro">
                        <h2 class="vp-ladder-title">حراج پله‌ای ویکی پلاس</h2>
                        <p class="vp-ladder-strap">خرید هوشمندانه، قیمت منصفانه</p>
                        <a href="#" class="vp-ladder-how">نحوه کار</a>
                    </div>
                    <ol class="vp-ladder-steps">
                    @foreach ($ladder['steps'] as $step)
                    <li class="vp-step{{ $step['state'] === 'upcoming' ? '' : ' is-'.$step['state'] }}">
                        <span class="vp-step-rate"><b>٪</b>@foreach (mb_str_split(fa_number($step['cut'])) as $digit)<b>{{ $digit }}</b>@endforeach</span>
                        <span class="vp-step-tags"><span class="vp-step-name">{{ $step['name'] }}</span><span class="vp-step-when">{{ $step['when'] }}</span><span class="vp-step-flag is-{{ $step['state'] }}" role="img" aria-label="{{ $stepMarkLabels[$step['state']] }}">{!! $stepMarks[$step['state']] !!}</span></span>
                    </li>
                    @endforeach
                    </ol>
                </div>
                <div class="vp-ladder-track">
                    @foreach ($ladder['steps'] as $step)
                    <span class="vp-track-leg{{ $step['state'] === 'upcoming' ? '' : ' is-filled' }}"></span>
                    @endforeach
                </div>
                <div class="vp-ladder-notes">
                    <a href="{{ page_url('shop.html') }}" class="vp-ladder-all">مشاهده همه محصولات موجود در حراج</a>
                    <span>انتقال پله فقط در صورت باقی‌ماندن موجودی</span>
                    <span>پله بعدی در ۲۲ روز و ۱۴ ساعت</span>
                </div>
                @php
                    // «یک محصول تکراری در حراج پله ای بزار که ۶ تایی بشه». The
                    // phone shows six cards and the catalogue holds fewer — the
                    // band is every product with a live promotion, which is what
                    // the sale is — so the rest are the first ones again.
                    //
                    // **It pads up to six rather than by one.** It added a single
                    // card, which was right while the pool held five and wrong the
                    // first time it held four: «حراج پله ای چرا ناقص شده باید ۶
                    // محصول توش باشه», five cards on the phone. The pool shrinks on
                    // its own — a promoted shoe selling its last pair leaves
                    // `purchasable()` and the band with it — and unlike the hero
                    // and the story rings this one may not have it back:
                    // these cards print a struck-through price, so they have to be
                    // built from what is really discounted and really sellable.
                    // Padding is the answer the client already chose; it just has
                    // to count.
                    //
                    // The pads cycle through the pool rather than repeating the
                    // first one twice, so two missing products show as two
                    // different shoes rather than one shoe three times.
                    //
                    // `d-lg-none` on every pad, so the desktop row is still the
                    // five it was drawn for: `row-cols-xl-5` puts five on one line
                    // and a sixth would wrap it onto two. With six promoted
                    // products this pads nothing and the repeats disappear on
                    // their own.
                    $deals = $ladderDeals->values()
                        ->map(fn ($deal) => ['deal' => $deal, 'phoneOnly' => false]);

                    $real = $deals->count();

                    for ($i = 0; $real > 0 && $deals->count() < 6; $i++) {
                        $deals = $deals->push(['deal' => $deals[$i % $real]['deal'], 'phoneOnly' => true]);
                    }
                @endphp
                <div class="row gy-4 row-cols-2 row-cols-md-3 row-cols-xl-5 vp-ladder-deals">
                @foreach ($deals as $card)
                @php($deal = $card['deal'])
                <div class="col{{ $card['phoneOnly'] ? ' d-lg-none' : '' }}">
                    <div class="vp-deal">
                        <a class="vp-deal-shot" href="{{ storefront_route('product', $deal) }}">
                            <img src="{{ asset($deal->imagePath()) }}"{!! photo_srcset($deal->imagePath()) !!} alt="" loading="lazy">
                            @include('partials.deal-burst', ['key' => $loop->index, 'percent' => $deal->offerHere()->discountPercent()])
                            <span class="vp-deal-label">
                                <span class="vp-deal-lines">
                                    <span class="vp-deal-name">{{ $deal->title }}</span>
                                    <span class="vp-deal-price"><del>{{ toman($deal->offerHere()->compare_at_price) }}</del><strong>{{ toman($deal->offerHere()->price) }} <span>تومان</span></strong></span>
                                </span>
                            </span>
                        </a>
                        <button type="button" class="vp-deal-cart" aria-label="افزودن به سبد خرید"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></button>
                    </div>
                </div>
                @endforeach
                </div>
            </div>
        </div>
    </section>    
<div class="vp-how-modal" id="vp-how" hidden>
        <div class="vp-how-veil" data-vp-how-close></div>
        <div class="vp-how-panel" role="dialog" aria-modal="true" aria-labelledby="vp-how-title">
            <button type="button" class="vp-how-close" data-vp-how-close aria-label="بستن">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4 12 12 M12 4 4 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
            </button>
            <div class="vp-how-scroll">
                <header class="vp-how-head">
                    <p class="vp-how-aside">اگر در هر مرحله فروش نرود، به پله بعدی منتقل شده و با تخفیف بیشتر عرضه می‌شود.</p>
                    <div class="vp-how-titles">
                        <h2 class="vp-how-title" id="vp-how-title">حراج پله‌ای ویکی پلاس</h2>
                        <p class="vp-how-strap"><span>خرید هوشمندانه، قیمت منصفانه</span></p>
                    </div>
                    <p class="vp-how-aside is-warn"><b>فرصت را از دست ندهید!</b> ممکن است قبل از رسیدن به تخفیف بیشتر فروخته شود.</p>
                </header>
                <div class="vp-how-board">
                    <ol class="vp-how-steps">
                        @foreach ($ladder['steps'] as $step)
                        <li class="vp-how-step">
                            <span class="vp-how-step-no">{{ $step['name'] }}</span>
                            <div class="vp-how-card">
                                <span class="vp-how-shot"><svg aria-hidden="true" viewBox="0 0 25 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M23.1043 19.9996H5.0564C3.35602 19.9996 0.0894938 19.3903 0 17.082C0 16.9765 0.0596625 16.8828 0.149156 16.8242C0.253566 16.7773 0.402722 16.7773 0.507132 16.8242C0.566794 16.8594 3.92281 18.4881 10.784 18.2068C14.0804 18.0897 15.9001 17.9139 17.496 17.7616C19.3157 17.5858 20.7924 17.4335 23.7009 17.4335C23.8054 17.4335 23.8948 17.4569 23.9545 17.5272C23.9993 17.5624 24.76 18.3123 24.4766 19.0388C24.3125 19.4723 23.865 19.7652 23.1341 19.941C23.164 19.9996 23.1341 19.9996 23.1043 19.9996ZM0.760697 17.5858C1.37224 19.4371 4.98182 19.4723 5.01165 19.4723H23.0297C23.4772 19.3434 23.7755 19.1676 23.865 18.945C23.9993 18.6169 23.7009 18.2068 23.5518 18.0311C20.7626 18.0311 19.3605 18.1834 17.6004 18.3592C15.9896 18.5115 14.1699 18.6872 10.8437 18.8161C5.36963 19.0153 2.04344 18.0545 0.760697 17.5858Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M23.7313 18.0074C23.5524 18.0074 23.3883 17.8785 23.3883 17.7379C23.3883 12.7346 22.553 12.5823 22.4635 12.5823C22.1354 12.5823 21.852 12.9104 21.4642 13.3088C20.5394 14.3165 20.0323 14.6914 19.4804 14.7383C18.9434 14.7969 18.5258 14.5156 17.9441 14.1407C17.4966 13.836 16.9149 13.4611 16.0945 13.1096C14.5284 12.4534 12.828 11.9965 11.2619 13.2033C8.38315 15.4179 5.34036 15.6756 5.20612 15.6991C1.49213 15.9451 0.731429 16.7536 0.731429 17.0348C0.76126 17.1754 0.627019 17.3043 0.448032 17.3278C0.254128 17.3512 0.0900563 17.234 0.060225 17.0817C0.060225 17.0348 0.0303938 16.7536 0.373453 16.4021C1.04466 15.7225 2.68538 15.3241 5.17629 15.1484C5.20612 15.1484 8.11467 14.8906 10.8144 12.8049C12.6938 11.352 14.7074 11.9027 16.4376 12.6057C17.3325 12.9807 17.9739 13.3791 18.4214 13.6837C18.8688 13.9884 19.197 14.1876 19.4506 14.1641C19.8682 14.1173 20.4797 13.4845 20.9869 12.9573C21.4045 12.5003 21.8818 11.9965 22.553 11.9965C23.3584 11.9965 24.1788 12.676 24.1788 17.7027C24.0893 17.8785 23.9252 18.0074 23.7313 18.0074Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.44378 16.2505C6.35494 16.2505 5.43017 16.0747 4.86338 15.6177C4.72914 15.524 4.72914 15.3482 4.86338 15.2428C4.9827 15.149 5.20644 15.149 5.34068 15.2428C6.20578 15.9224 8.86077 15.8521 11.4859 15.0436C13.6934 14.3874 15.1552 13.4383 15.2297 12.6298C15.2297 12.4775 15.3789 12.3838 15.6175 12.3838C15.7965 12.3838 15.9606 12.5361 15.9308 12.6767C15.8413 13.7313 14.2602 14.821 11.7395 15.5709C10.4269 15.9692 8.83093 16.2505 7.44378 16.2505Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M15.0352 18.5585C14.901 18.5585 14.8115 18.5116 14.7518 18.4413L12.3504 15.1488C12.246 15.0199 12.3206 14.8442 12.4698 14.7621C12.6338 14.6918 12.8576 14.7387 12.9471 14.8676L15.3485 18.1601C15.4529 18.289 15.3783 18.4648 15.2291 18.5351C15.1546 18.5351 15.0949 18.5585 15.0352 18.5585Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M12.276 18.6867C12.1567 18.6867 12.0225 18.6399 11.9628 18.5344L10.2922 15.7223C10.2027 15.5934 10.2922 15.4176 10.4563 15.3708C10.6204 15.2888 10.8441 15.3708 10.9038 15.4997L12.5743 18.3118C12.6638 18.4407 12.5743 18.6164 12.4103 18.6633C12.3804 18.6633 12.3506 18.6867 12.276 18.6867Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M20.4339 18.0894C20.2549 18.0894 20.0908 17.9605 20.0908 17.8082V17.7379C20.0908 16.1795 21.6271 14.9141 23.6109 14.8672C23.8048 14.8672 23.954 14.9961 23.954 15.1484C23.954 15.289 23.8048 15.4179 23.6109 15.4179C22.0149 15.4413 20.762 16.4724 20.762 17.7379V17.8082C20.7919 17.9605 20.6278 18.0894 20.4339 18.0894Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M8.75521 15.5706C8.63589 15.5706 8.53148 15.5237 8.47182 15.4417L7.96469 14.7621C7.86028 14.6449 7.93486 14.4692 8.08401 14.3871C8.24808 14.3168 8.47182 14.3637 8.57623 14.4926L9.08336 15.1722C9.17285 15.2894 9.11319 15.4768 8.94912 15.5471C8.85962 15.5706 8.82979 15.5706 8.75521 15.5706Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M10.1427 14.9729C10.0085 14.9729 9.919 14.9143 9.84442 14.844L9.30746 14.0941C9.20305 13.9652 9.27763 13.7895 9.42679 13.7075C9.59086 13.6372 9.81459 13.684 9.919 13.8129L10.456 14.5628C10.5604 14.6917 10.4858 14.8675 10.3366 14.9378C10.2621 14.9729 10.2025 14.9729 10.1427 14.9729Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M11.41 14.1412C11.2907 14.1412 11.1863 14.0944 11.1266 14.0123L10.5897 13.2624C10.4853 13.1335 10.5599 12.9578 10.709 12.8875C10.8731 12.8055 11.0968 12.8523 11.1863 12.9812L11.7382 13.7311C11.8277 13.86 11.768 14.0358 11.6039 14.1178C11.5145 14.1412 11.4846 14.1412 11.41 14.1412Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M4.78751 14.4462C3.99698 14.4462 3.19153 14.2118 2.55016 13.6377C2.46067 13.5557 2.46067 13.4619 2.4905 13.3565C2.52033 13.2627 2.65457 13.2041 2.77389 13.1807C2.80373 13.1807 6.29398 12.8058 10.7836 9.59525C12.9464 8.02515 14.0352 7.12293 15.0345 6.29101C16.1532 5.36535 17.0482 4.61546 19.0618 3.30313C19.1662 3.25626 19.2557 3.23283 19.3601 3.25626C19.3899 3.25626 20.4788 3.43202 20.8218 4.08818C21.0157 4.46313 20.9262 4.86152 20.5682 5.31849C20.5384 5.34192 20.5384 5.36535 20.5086 5.38879L8.15844 13.4151C7.41266 13.8603 6.10008 14.4462 4.78751 14.4462ZM3.54951 13.6142C5.27972 14.5165 7.57673 13.0284 7.71097 12.9581L20.0313 4.96697C20.255 4.68576 20.3147 4.46313 20.2103 4.26394C20.0909 4.00616 19.6733 3.85384 19.3899 3.80697C17.4956 5.03727 16.6305 5.7403 15.5417 6.64253C14.5572 7.47445 13.4386 8.41182 11.2609 9.9702C7.7408 12.5011 4.83225 13.333 3.54951 13.6142Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M2.84875 13.7313C2.74434 13.7313 2.65485 13.7079 2.58027 13.661C2.55044 13.6376 2.29688 13.4384 2.29688 13.0283C2.29688 12.2549 3.10232 11.2473 4.66846 10.017C4.66846 10.017 6.48817 8.52889 6.84614 5.93939C7.09971 4.08808 8.88959 3.56081 10.59 3.27959C11.4849 3.12727 12.1859 3.12727 12.7229 3.12727C13.2151 3.12727 13.588 3.12727 13.7223 2.99838C13.946 2.79919 13.946 2.09616 13.8863 1.52202C13.8565 1.01818 13.7819 0.467473 14.304 0.139392C14.8708 -0.212123 15.9298 -0.14182 19.5841 3.32646C19.7034 3.45535 19.6736 3.60768 19.5393 3.70141C19.3902 3.80687 19.1963 3.78343 19.062 3.67798C15.4226 0.209695 14.7813 0.56121 14.7813 0.56121C14.5575 0.690099 14.5874 1.12363 14.6172 1.49858C14.7067 2.6 14.6172 3.10384 14.2592 3.38505C13.9162 3.65454 13.3941 3.65454 12.7676 3.65454C12.2456 3.65454 11.5744 3.65454 10.7391 3.78343C8.94925 4.06465 7.74108 4.53333 7.54718 5.97454C7.15937 8.78667 5.28 10.3216 5.17559 10.3685C2.87858 12.1495 2.87858 13.0283 3.07249 13.2392C3.19181 13.3329 3.19181 13.5087 3.07249 13.6141C2.99791 13.7079 2.90842 13.7313 2.84875 13.7313Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M5.05569 10.4967C4.98111 10.4967 4.95128 10.4967 4.89162 10.4967C4.69772 10.4733 4.56348 10.3444 4.56348 10.192C4.60822 10.0397 4.75738 9.94598 4.95128 9.94598C6.01029 10.0163 7.77034 8.76255 8.9785 7.09871C10.0077 5.69265 10.3358 4.38032 9.81378 3.82962C9.69445 3.70073 9.75412 3.52497 9.87344 3.45467C10.0375 3.34921 10.2612 3.4078 10.3657 3.50154C11.0667 4.28659 10.7833 5.71608 9.59004 7.37992C8.47137 8.87972 6.62183 10.4967 5.05569 10.4967Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M13.752 8.02525C13.7221 8.02525 13.6625 8.02525 13.6177 8.00182L9.62035 6.8301C9.42644 6.77152 9.36678 6.61919 9.42644 6.46687C9.50102 6.32626 9.69492 6.26768 9.87391 6.32626L13.8862 7.49798C14.0801 7.55657 14.1398 7.70889 14.0801 7.8495C14.0055 7.95495 13.8862 8.02525 13.752 8.02525Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M11.9319 9.33715C11.8722 9.33715 11.8275 9.33715 11.7678 9.31372L8.60572 8.13028C8.44165 8.05998 8.35216 7.90766 8.44165 7.77877C8.53115 7.64988 8.72505 7.57958 8.88912 7.64988L12.0512 8.83332C12.2153 8.91534 12.3197 9.05594 12.2153 9.18483C12.1855 9.26685 12.0512 9.33715 11.9319 9.33715Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M17.1086 5.31907C17.0191 5.31907 16.9147 5.29564 16.855 5.21362C16.8252 5.19018 16.8252 5.16675 16.7953 5.16675C15.6767 4.08877 15.8407 2.53038 17.1384 1.59301C17.3025 1.49927 17.4964 1.49927 17.6306 1.61644C17.7499 1.74533 17.7499 1.89766 17.6008 2.00311C16.5418 2.72957 16.4075 3.95988 17.3025 4.81523L17.3621 4.8621C17.4964 4.96755 17.4964 5.14331 17.3323 5.23705C17.2726 5.29564 17.1831 5.31907 17.1086 5.31907Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.29299 8.78604C7.26316 8.78604 7.18858 8.78604 7.15875 8.76261L6.32347 8.50483C6.12957 8.45796 6.06991 8.30564 6.12957 8.15332C6.20415 8.00099 6.39805 7.93069 6.59195 8.00099L7.41231 8.25877C7.60622 8.30564 7.66588 8.45796 7.60622 8.61028C7.57638 8.70402 7.44214 8.78604 7.29299 8.78604Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.80035 7.75557C7.77052 7.75557 7.71086 7.75557 7.66611 7.73214L6.77117 7.45092C6.59219 7.40405 6.51761 7.25173 6.59219 7.09941C6.65185 6.94708 6.84575 6.90022 7.03965 6.94708L7.93459 7.2283C8.11358 7.27517 8.18816 7.42749 8.11358 7.57981C8.08375 7.67355 7.96442 7.75557 7.80035 7.75557Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M8.0848 6.61921C8.05497 6.61921 7.99531 6.61921 7.96548 6.59578L7.07054 6.32628C6.87664 6.2677 6.81698 6.11537 6.87664 5.97477C6.9363 5.82245 7.1302 5.74043 7.32411 5.82245L8.21905 6.09194C8.41295 6.15053 8.47261 6.29113 8.41295 6.44346C8.35329 6.54891 8.21905 6.61921 8.0848 6.61921Z" fill="currentColor"/></svg></span>
                                <span class="vp-how-off"><b>٪{{ fa_number($step['cut']) }}</b><small>تخفیف</small></span>
                            </div>
                            <span class="vp-how-podium"></span>
                            <span class="vp-how-when"><svg viewBox="0 0 16 16" aria-hidden="true"><rect x="2" y="3.4" width="12" height="10.6" rx="2.4" fill="none" stroke="currentColor" stroke-width="1.4"></rect><path d="M2 6.8h12M5.4 2v2.6M10.6 2v2.6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"></path><circle cx="5.6" cy="9.6" r="0.9" fill="currentColor"></circle><circle cx="8" cy="9.6" r="0.9" fill="currentColor"></circle><circle cx="10.4" cy="9.6" r="0.9" fill="currentColor"></circle></svg>{{ $step['when'] }}</span>
                        </li>
                        @endforeach
                    </ol>
                </div>
                <ul class="vp-how-rules">
                        <li class="vp-how-rule"><span class="vp-how-rule-icon"><svg viewBox="0 0 18 18" aria-hidden="true"><path d="M3 8.4V3.4a.9.9 0 0 1 .9-.9h5l7.6 7.6a1.2 1.2 0 0 1 0 1.7l-4.3 4.3a1.2 1.2 0 0 1-1.7 0Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><circle cx="6.4" cy="6" r="1.2" fill="currentColor"></circle></svg></span><span class="vp-how-rule-text">تخفیف‌ها خودکار و<br>زمان‌بندی‌شده هستند</span></li>
                        <li class="vp-how-rule"><span class="vp-how-rule-icon"><svg viewBox="0 0 18 18" aria-hidden="true"><rect x="2.6" y="4" width="12.8" height="11.4" rx="2.6" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M2.6 7.6h12.8M6.4 2.4v3M11.6 2.4v3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg></span><span class="vp-how-rule-text">انتقال به پله بعد فقط<br>با باقی‌ماندن موجودی</span></li>
                        <li class="vp-how-rule"><span class="vp-how-rule-icon"><svg viewBox="0 0 18 18" aria-hidden="true"><path d="M9 2.4 15.6 6v6L9 15.6 2.4 12V6Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><path d="M2.4 6 9 9.6 15.6 6M9 9.6v6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path></svg></span><span class="vp-how-rule-text">کالاهای حراج پله‌ای<br>موجودی محدود دارند</span></li>
                        <li class="vp-how-rule"><span class="vp-how-rule-icon"><svg viewBox="0 0 18 18" aria-hidden="true"><circle cx="9" cy="9" r="6.6" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M6.6 11.4 11.4 6.6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path><circle cx="6.9" cy="6.9" r="1.1" fill="currentColor"></circle><circle cx="11.1" cy="11.1" r="1.1" fill="currentColor"></circle></svg></span><span class="vp-how-rule-text">امکان اتمام موجودی<br>در هر مرحله هست</span></li>
                        <li class="vp-how-rule"><span class="vp-how-rule-icon"><svg viewBox="0 0 18 18" aria-hidden="true"><rect x="3.6" y="7.8" width="10.8" height="7.6" rx="2.4" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M6 7.8V6a3 3 0 0 1 6 0v1.8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg></span><span class="vp-how-rule-text">پس از اتمام موجودی<br>رزرو قیمت ممکن نیست</span></li>
                        <li class="vp-how-rule"><span class="vp-how-rule-icon"><svg viewBox="0 0 18 18" aria-hidden="true"><circle cx="9" cy="6.4" r="2.8" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M3.6 15.2a5.4 5.4 0 0 1 10.8 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg></span><span class="vp-how-rule-text">اولویت با کسانی است<br>که زودتر ثبت می‌کنند</span></li>
                </ul>
                <div class="vp-how-cols">
                    <section>
                        <h3>حراج پله‌ای چیست؟</h3>
                        <p>در ویکی پلاس، برای اولین بار مدل فروش حراج پله‌ای را طراحی کرده‌ایم؛ روشی شفاف، زمان‌بندی‌شده و منصفانه که به شما امکان می‌دهد کالاهای منتخب را با تخفیف‌های مرحله‌ای و واقعی بخرید.</p>
                        <p>این طرح مخصوص محصولاتی است که موجودی محدودی دارند یا پس از فروش اولیه، تنها برخی سایزها یا رنگ‌های آن‌ها باقی مانده است.</p>
                    </section>
                    <section>
                        <h3>چگونه کار می‌کند؟</h3>
                        <ol class="vp-how-list">
                            <li>هر محصول با یک تخفیف اولیه وارد پله اول می‌شود.</li>
                            <li>اگر تا پایان آن مرحله فروش نرود، به پله بعدی منتقل می‌شود.</li>
                            <li>در هر پله، درصد تخفیف بیشتر از مرحله قبل خواهد بود.</li>
                            <li>تنها موجودی باقی‌مانده وارد مرحله بعد می‌شود.</li>
                            <li>این روند تا فروش کامل کالا یا پایان آخرین پله ادامه دارد.</li>
                        </ol>
                    </section>
                    <section>
                        <h3>یک مثال</h3>
                        <p>فرض کنید از یک مدل کفش فقط سایزهای ۳۷ و ۴۰ باقی مانده باشد.</p>
                        <p>اگر این سایزها در پله اول به فروش نرسند، همان موجودی وارد پله دوم می‌شود و با تخفیف بیشتری عرضه خواهد شد. بنابراین ممکن است پیش از رسیدن به تخفیف بالاتر، سایز موردنظر شما را شخص دیگری بخرد.</p>
                    </section>
                    <section>
                        <h3>چرا حراج پله‌ای؟</h3>
                        <ul class="vp-how-list is-ticked">
                            <li>قیمت‌گذاری شفاف و بدون تخفیف ساختگی</li>
                            <li>خرید هوشمندانه متناسب با بودجه شما</li>
                            <li>مدیریت عادلانه کالاهای با موجودی محدود</li>
                            <li>تجربه‌ای نو در خرید آنلاین کفش و کیف</li>
                        </ul>
                    </section>
                </div>
                <p class="vp-how-note">انتخاب با شماست؛ خرید زودتر با اطمینان بیشتر، یا صبر برای تخفیف بالاتر با ریسک از دست رفتن کالا.</p>
            </div>
        </div>
    </div>

@push('scripts')
    <script>
        (function () {
            var opener = ".vp-ladder-how";
            function modal() { return document.getElementById("vp-how"); }
            var lastFocus = null;
            var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            var lightTimer = null;
            // 2s a step, starting from the first — client asked for the board to
            // walk itself through instead of opening on one fixed step. Runs the
            // full five twice: the first pass ends at the last step and wraps back
            // to the first rather than stopping, the second pass ends at the last
            // step and stays there. Skipped entirely under reduced motion, where
            // the CSS fallback (see tweaks.css) lights the live step as a still
            // instead.
            function stepEls(m) { return m.querySelectorAll(".vp-how-step"); }
            function stopLights() {
                if (lightTimer) { clearInterval(lightTimer); lightTimer = null; }
            }
            function startLights(m) {
                var els = stepEls(m);
                if (!els.length) return;
                var i = 0;
                var pass = 1;
                els[i].classList.add("is-lit");
                lightTimer = setInterval(function () {
                    if (i === els.length - 1) {
                        if (pass >= 2) { stopLights(); return; }
                        pass += 1;
                        for (var j = 0; j < els.length; j++) els[j].classList.remove("is-lit", "is-done");
                        i = 0;
                        els[i].classList.add("is-lit");
                        return;
                    }
                    els[i].classList.remove("is-lit");
                    els[i].classList.add("is-done");
                    i += 1;
                    els[i].classList.add("is-lit");
                }, 2000);
            }
            function open(e) {
                var m = modal();
                if (!m) return;
                if (e) e.preventDefault();
                lastFocus = document.activeElement;
                m.hidden = false;
                document.documentElement.style.overflow = "hidden";
                var close = m.querySelector(".vp-how-close");
                if (close) close.focus();
                if (!reduceMotion) {
                    var els = stepEls(m);
                    for (var j = 0; j < els.length; j++) els[j].classList.remove("is-lit", "is-done");
                    startLights(m);
                }
            }
            function close() {
                var m = modal();
                if (!m || m.hidden) return;
                m.hidden = true;
                document.documentElement.style.overflow = "";
                if (lastFocus && lastFocus.focus) lastFocus.focus();
                stopLights();
            }
            document.addEventListener("click", function (e) {
                if (e.target.closest && e.target.closest(opener)) { open(e); return; }
                if (e.target.closest && e.target.closest("[data-vp-how-close]")) { close(); }
            });
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") close();
            });
        }());
    </script>
@endpush
