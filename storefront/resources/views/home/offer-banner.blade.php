{{--
    The two banners in "Cta Area", now the shop's own coming-soon collages.

    Used to carry the stepped-sale copy translated from the template's Black
    Friday banner. «اینام بزار تو بنرای سایت» replaced the photographs
    themselves with two collages the client sent, each already carrying its
    own «COMING SOON» mark baked into the picture, so the title, sub-title and
    button that make-rtl-page.js had translated came off rather than sit on
    top of a second, conflicting message — «متن و دکمه پاک بشه، فقط خود عکس»
    in those words. The first banner's `.discount` shape went with them: a
    Black Friday «%» badge has nothing to do with a coming-soon collage.

    Ported from download-version/shoe-shop-rtl.html by theme/make-blade.js
    originally; hand-owned since (see CLAUDE.md), which is why this note is
    prose rather than a codegen header.
--}}
<section class="overflow-hidden">
        <div class="container-fluid p-0">
            <div class="row gy-4">
                <div class="col-xxl-8">
                    <div class="cta-area4 mega-hover" data-bg-src="{{ asset('assets/img/normal/cta_11_1.jpg') }}" role="img" aria-label="به‌زودی">
                    </div>
                </div>
                <div class="col-xxl-4">
                    <div class="cta-area4 style2 mega-hover" data-bg-src="{{ asset('assets/img/normal/cta_11_2.jpg') }}" role="img" aria-label="به‌زودی">
                    </div>
                </div>
            </div>
        </div>
    </section>
