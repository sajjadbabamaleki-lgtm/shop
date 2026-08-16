{{--
    The eight questions, in one place.

    They were written into `pages/faq.blade.php` and are now included from
    there and from the home page's band, because the client asked for them at
    the foot of the shop as well — «بنظرم قسمت انتهای سایت هم بزارش این سوالات
    متداولو خود سوالاتو» — and a second copy of eight answers is a second copy
    that goes stale. Every answer here is read off the application rather than
    written about it: the delivery charge and the free-delivery threshold are
    `storefront.checkout`, the same two numbers `CheckoutController` adds up
    with; the cancellation answer is `Order::isCancellable()` in words; the
    registration answer is what `AccountController` actually asks for.

    `<details>` rather than a scripted accordion: it needs no script, it opens
    with the keyboard, and a browser's find-in-page can see inside a closed
    one. `$openFirst` is the one difference between the two places that show
    it — the page opens its first box, because a whole page of closed boxes
    reads as empty, and the home band does not, because it is one band among
    seven and its job is to be small until asked.

    Each answer is wrapped in `.vp-faq-a` so it can be measured and its height
    animated — «کشو باید با یه انیمیشن نرم بازو بسته بشه». `<details>` gives no
    element for the part that opens, and a height cannot be transitioned from a
    thing that is not there.

    Hand-owned: `theme/make-blade.js` does not write this file and must not be
    pointed at it.
--}}

@php
    $shippingFlat = (int) config('storefront.checkout.shipping_flat');
    $freeAbove = (int) config('storefront.checkout.free_shipping_above');
    $exchangeDays = (int) config('storefront.content.exchange_days');
@endphp

        <details @if ($openFirst ?? false) open @endif>
            <summary>هزینه ارسال چقدر است؟</summary>
            <div class="vp-faq-a">
            <p>
                هزینه ارسال {{ toman($shippingFlat) }} تومان است و برای
                سفارش‌های بالای {{ toman($freeAbove) }} تومان رایگان
                می‌شود. مبلغ دقیق، پیش از ثبت سفارش در صفحه پرداخت
                نوشته می‌شود؛ چیزی بعداً به آن اضافه نمی‌شود.
            </p>
            </div>
        </details>

        <details>
            <summary>چطور پرداخت کنم؟</summary>
            <div class="vp-faq-a">
            <p>
                فعلاً فقط پرداخت در محل، هنگام تحویل. درگاه بانکی هنوز
                وصل نشده و تا وصل نشده، هیچ جای این سایت از شما شماره
                کارت یا رمز نمی‌خواهد. اگر صفحه‌ای به نام ویکی پلاس از
                شما اطلاعات بانکی خواست، مال ما نیست.
            </p>
            </div>
        </details>

        <details>
            <summary>سفارشم را چطور پیگیری کنم؟</summary>
            <div class="vp-faq-a">
            <p>
                از
                <a href="{{ storefront_route('orders.track') }}">صفحه پیگیری سفارش</a>،
                با شماره سفارش (که با VP- شروع می‌شود) و شماره موبایلی
                که سفارش با آن ثبت شده. اگر حساب کاربری دارید، همه
                سفارش‌هایتان در
                <a href="{{ storefront_route('account.enter') }}">حساب کاربری</a>
                فهرست شده است.
            </p>
            </div>
        </details>

        <details>
            <summary>می‌توانم سفارشم را لغو کنم؟</summary>
            <div class="vp-faq-a">
            <p>
                تا وقتی وضعیت سفارش «ثبت شد» است، بله — دکمه لغو در
                همان صفحه سفارش هست و کالاها بلافاصله به فروشگاه
                برمی‌گردند. بعد از این‌که سفارش ارسال شد دیگر از سایت
                قابل لغو نیست و باید تماس بگیرید.
            </p>
            </div>
        </details>

        <details>
            <summary>سایز کفش اشتباه بود. تعویض می‌کنید؟</summary>
            <div class="vp-faq-a">
            <p>
                تا {{ fa_number($exchangeDays) }} روز بعد از تحویل، برای
                تعویض سایز با ما تماس بگیرید. تعویض فعلاً تلفنی و
                دستی انجام می‌شود، نه از داخل سایت، پس لطفاً کالا را
                نپوشیده و با جعبه نگه دارید تا هماهنگ شود.
            </p>
            <p>
                بهتر از تعویض، این است که اول
                <a href="{{ storefront_route('size-guide') }}">راهنمای سایز</a>
                را ببینید یا طول پایتان را برای ما بفرستید.
            </p>
            </div>
        </details>

        <details>
            <summary>کد تخفیف را کجا وارد کنم؟</summary>
            <div class="vp-faq-a">
            <p>
                در صفحه پرداخت، کنار جمع فاکتور. کد روی کالاهای خود
                ویکی پلاس اعمال می‌شود و رقم آن، پیش از ثبت سفارش، در
                همان جمع به شما نشان داده می‌شود.
            </p>
            </div>
        </details>

        <details>
            <summary>حساب کاربری لازم است؟</summary>
            <div class="vp-faq-a">
            <p>
                نه. می‌توانید بدون ثبت‌نام سفارش دهید. اگر حساب بسازید،
                سفارش‌هایتان یک‌جا جمع می‌شود.
            </p>
            <p>
                نکته‌ای که ممکن است به آن بربخورید: اگر قبلاً با همین
                شماره موبایل سفارش داده‌اید، هنگام ثبت‌نام شماره یکی از
                سفارش‌های خودتان را می‌پرسیم. این تنها راهی است که
                مطمئن شویم شماره مال خودتان است — پیامک تأیید هنوز
                نداریم.
            </p>
            </div>
        </details>

        <details>
            <summary>می‌خواهم کالاهایم را در ویکی پلاس بفروشم.</summary>
            <div class="vp-faq-a">
            <p>
                از
                <a href="{{ storefront_route('vendors.apply') }}">فرم فروشنده شوید</a>
                درخواست بدهید. ثبت‌نام رایگان است، کارمزد فقط روی فروش
                گرفته می‌شود، و کالاهایتان پس از تأیید روی سایت
                می‌آید.
            </p>
            </div>
        </details>
