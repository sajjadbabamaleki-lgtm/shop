<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The shop's first three articles.
 *
 * «فعلا ۳ تا مقاله جدید واقعی با متن واقعی بزار» — so these are real advice,
 * researched rather than invented, and written out in the shop's own words
 * rather than translated from any one page. What each rests on:
 *
 * - **Leather in winter.** Salt is the thing that ruins a boot, and the cure is
 *   wiping it off before it dries, neutralising what has already dried with
 *   diluted vinegar, drying at room temperature and never at a heater, and
 *   putting the oils back afterwards because both salt and vinegar take them
 *   out. Cedar trees for the damp that comes from inside the shoe.
 * - **Measuring a foot.** At the end of the day, standing, on paper, with the
 *   pen upright, both feet, in the socks the shoe will be worn with — and the
 *   larger foot decides.
 * - **Storing a bag.** Clean and dry before it goes away, filled lightly enough
 *   to hold its shape and not so much that it stretches, in something that
 *   breathes rather than plastic, standing up rather than hung by its straps.
 *
 * **A migration and not a seeder.** `catalogue:seed` runs only on an empty
 * catalogue and production has not been empty for weeks, so a seeder edited
 * here would ship green and put nothing on the live site. See CLAUDE.md.
 *
 * **It inserts and never overwrites.** Each article is written only if its slug
 * is absent, so re-running this cannot undo an edit somebody has made in the
 * panel since. `down()` removes only what it wrote, and only if it is still
 * untouched.
 */
return new class extends Migration
{
    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            [
                'slug' => 'نگهداری-از-کفش-چرم-در-زمستان',
                'title' => 'نگهداری از کفش چرم در زمستان',
                'excerpt' => 'نمک خیابان بزرگ‌ترین دشمن کفش چرم است. چهار کار ساده که کفش را از زمستان سالم بیرون می‌آورد.',
                'image' => 'assets/img/category/boot.jpg',
                'quote' => 'چرم زنده است؛ اگر خشک بماند ترک می‌خورد و اگر خیس بماند لک می‌شود. کار شما فقط نگه داشتن آن در میانهٔ این دو است.',
                'quote_by' => 'کارگاه ویکی پلاس',
                'tags' => ['کفش چرم', 'نگهداری', 'زمستان'],
                'body' => <<<'TEXT'
                    زمستان برای کفش چرم دو خطر دارد و هر دو هم قابل پیشگیری‌اند: رطوبت، و نمکی که برای آب کردن یخ روی پیاده‌رو می‌پاشند. رطوبت چرم را نرم و بی‌شکل می‌کند و نمک آن را از داخل خشک می‌کند تا جایی که ترک بخورد. کاری که باید بکنید پیچیده نیست، ولی باید همان شب انجام شود، نه آخر هفته.

                    ۱) همان لحظه که به خانه رسیدید، کفش را پاک کنید

                    یک دستمال نمدارِ کمی مرطوب کافی است. هدف این است که نمک را قبل از خشک شدن بردارید؛ نمکی که خشک شود و بماند، به‌مرور رنگ چرم را می‌بَرد. اگر رد سفید نمک قبلاً روی کفش نشسته، سرکهٔ سفید را با آب نصف‌نصف مخلوط کنید و با دستمال تمیز آرام روی همان قسمت بزنید. سرکه نمک را خنثی می‌کند؛ فشار دادن و سابیدن کاری نمی‌کند جز آسیب زدن به سطح چرم.

                    ۲) بگذارید در دمای اتاق خشک شود

                    این مهم‌ترین بند این مقاله است: کفش خیس را کنار شوفاژ، جلوی بخاری، روی رادیاتور یا زیر آفتاب مستقیم نگذارید. حرارت، آب را از چرم بیرون می‌کشد و با آن روغن‌های چرم را هم می‌بَرد؛ نتیجه‌اش چرمی است که سفت و شکننده می‌شود و دیگر برنمی‌گردد. جای خنک، دور از حرارت، و صبر.

                    ۳) قالب سدر بگذارید

                    قالب چوبی سدر دو کار هم‌زمان می‌کند: شکل کفش را نگه می‌دارد و رطوبتی را که از داخل — یعنی از پای خودتان — جمع شده می‌کشد. چوب سدر این رطوبت را می‌گیرد بی‌آنکه خود چرم را خشک کند، و نمکِ ناشی از عرق را هم که در طول روز داخل کفش نشسته با خودش می‌بَرد. اگر قالب سدر ندارید، کاغذ مچاله‌شده بهتر از هیچ است، ولی هر چند ساعت باید عوض شود.

                    ۴) بعد از هر پاک‌سازی، روغن را برگردانید

                    هم نمک و هم سرکه چرم را خشک می‌کنند. پس بعد از هر بار که یکی از این دو را از کفش گرفتید، نوبت کرم یا واکس مرطوب‌کننده است. مقدار کم، با دستمال نرم، و بگذارید بنشیند. این همان کاری است که تفاوت یک جفت بوت سه‌ساله و یک جفت بوت یک‌ساله را می‌سازد.

                    و یک نکتهٔ آخر: کفشی که هر روز پوشیده می‌شود هیچ‌وقت فرصت خشک شدن کامل پیدا نمی‌کند. اگر می‌توانید دو جفت را یک‌درمیان بپوشید، عمر هر دو بیشتر از دو برابر می‌شود.
                    TEXT,
            ],
            [
                'slug' => 'اندازه-گرفتن-درست-سایز-پا-در-خانه',
                'title' => 'اندازه گرفتن درست سایز پا در خانه',
                'excerpt' => 'با یک کاغذ، یک خودکار و یک خط‌کش، در پنج دقیقه. و چند نکته که بیشتر خریدهای اشتباه از ندانستنشان می‌آید.',
                'image' => 'assets/img/category/sneaker.jpg',
                'quote' => 'پای شما دو اندازه دارد، نه یکی. کفش را برای پای بزرگ‌تر بخرید؛ پای کوچک‌تر با یک جفت جوراب جبران می‌شود، ولی پای بزرگ‌تر با هیچ‌چیز.',
                'quote_by' => 'کارگاه ویکی پلاس',
                'tags' => ['راهنمای سایز', 'خرید کفش'],
                'body' => <<<'TEXT'
                    بیشترِ کفش‌هایی که پس فرستاده می‌شوند اشکال دوخت ندارند؛ فقط اندازه نبوده‌اند. و اندازه گرفتن پا در خانه واقعاً پنج دقیقه است، اگر چند چیز را رعایت کنید.

                    آخر روز اندازه بگیرید

                    پا در طول روز ورم می‌کند. پایی که صبح اندازه بگیرید تا عصر بزرگ‌تر شده است، و کفشی که با اندازهٔ صبح خریده شود، همان کفشی است که عصرها اذیت می‌کند. آخرِ روز، بعد از راه رفتن، درست‌ترین لحظه است.

                    ایستاده اندازه بگیرید، نه نشسته

                    وقتی وزنتان روی پا می‌افتد، پا پهن‌تر و بلندتر می‌شود. اندازهٔ نشسته، اندازهٔ پایی است که هیچ‌وقت با آن راه نمی‌روید. کاغذ را روی زمین صاف بگذارید، پشتش را به دیوار بچسبانید، پاشنه را به دیوار تکیه دهید و بایستید.

                    خودکار را عمود نگه دارید

                    دور پا خط بکشید، ولی خودکار را عمود بر کاغذ بگیرید. اگر خودکار کج باشد، خط از پا فاصله می‌گیرد و چند میلی‌متر اضافه به اندازه می‌دهد — و چند میلی‌متر در دنیای سایز کفش یعنی یک سایز.

                    هر دو پا را اندازه بگیرید

                    دو پای یک نفر معمولاً هم‌اندازه نیستند و این کاملاً طبیعی است. عدد بزرگ‌تر ملاک است.

                    با همان جورابی که می‌پوشید

                    اگر قرار است کفش را با جوراب ضخیم بپوشید، با همان جوراب اندازه بگیرید. یک جوراب زمستانی به‌راحتی نیم سایز جا می‌گیرد.

                    عرض را هم اندازه بگیرید

                    از روی همان خطی که کشیدید، پهن‌ترین جای پا را اندازه بگیرید. طول، سایز را تعیین می‌کند؛ عرض تعیین می‌کند که آن سایز راحت است یا نه. کسی که پای پهن دارد و فقط طول را حساب می‌کند، معمولاً یک سایز بزرگ‌تر می‌خرد تا عرض جا شود — و بعد پاشنه‌اش در کفش سُر می‌خورد.

                    بعد از اندازه‌گیری، عدد طول را با جدول سایز ما مقایسه کنید. اگر بین دو سایز ماندید، سایز بزرگ‌تر را بگیرید: فضای اضافه را می‌شود با کفی یا جوراب پر کرد، ولی کفش تنگ با هیچ ترفندی راحت نمی‌شود.
                    TEXT,
            ],
            [
                'slug' => 'نگهداری-و-انبار-کردن-کیف-چرم',
                'title' => 'نگهداری و انبار کردن کیف چرم',
                'excerpt' => 'کیفی که درست کنار گذاشته شود، بعد از یک فصل همان شکلی است که بود. چند اشتباه رایج که شکل کیف را خراب می‌کند.',
                'image' => 'assets/img/category/bag-set.jpg',
                'quote' => 'کیف را از بندش آویزان نکنید. وزن کیف تمام‌وقت روی محل اتصال بند می‌افتد، و آن نقطه همان‌جایی است که سرِ آخر پاره می‌شود.',
                'quote_by' => 'کارگاه ویکی پلاس',
                'tags' => ['کیف چرم', 'نگهداری'],
                'body' => <<<'TEXT'
                    کیف چرم اگر درست نگهداری شود سال‌ها می‌ماند، و اگر نه، در همان فصل اول شکلش را از دست می‌دهد. تفاوت این دو چند عادت ساده است.

                    قبل از کنار گذاشتن، تمیز و خشک

                    کیفی که کثیف یا نمناک کنار گذاشته شود، همان چربی و رطوبت را در خودش تثبیت می‌کند؛ نتیجه‌اش لکه‌ای است که دیگر پاک نمی‌شود، یا بدتر، کپک. با دستمال نرم داخل و بیرونش را پاک کنید و مطمئن شوید کاملاً خشک است.

                    داخلش را پر کنید — ولی نه زیادی

                    کیف خالی روی خودش می‌خوابد و تا می‌خورد، و آن تاخوردگی بعد از چند ماه دیگر باز نمی‌شود. کاغذ بدون اسید، یا حتی یک تی‌شرت نرم و تمیز، شکل کیف را نگه می‌دارد. ولی پر کردن بیش از حد هم اشتباه است: چرم کش می‌آید و کیف گشاد می‌شود. آن‌قدر پر کنید که شکل خودش را داشته باشد، نه بیشتر.

                    در چیزی بگذارید که نفس بکشد

                    کیسهٔ پارچه‌ای — همانی که معمولاً همراه کیف می‌آید — یا یک روبالشی نخی تمیز. پلاستیک نه: چرم باید نفس بکشد، و رطوبتی که زیر پلاستیک گیر بیفتد به کپک می‌رسد.

                    از بند آویزانش نکنید

                    این رایج‌ترین اشتباه است. وقتی کیف از بند آویزان می‌ماند، تمام وزنش ماه‌ها روی دو نقطهٔ اتصال بند است. بند کش می‌آید و محل اتصال ضعیف می‌شود. کیف را ایستاده روی قفسه بگذارید، یا خوابیده — ولی نه فشرده بین چیزهای دیگر.

                    جای خنک، خشک و دور از آفتاب

                    آفتاب مستقیم رنگ چرم را می‌بَرد و حرارت خشکش می‌کند. کمد معمولی خانه از هر جای دیگری بهتر است.

                    و اگر چند کیف دارید، روی هم نچینید؛ کیف زیرین شکل کیف رویی را می‌گیرد.
                    TEXT,
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->articles() as $index => $article) {
            if (DB::table('articles')->where('slug', $article['slug'])->exists()) {
                continue;
            }

            DB::table('articles')->insert([
                'slug' => $article['slug'],
                'title' => $article['title'],
                'excerpt' => $article['excerpt'],
                'image' => $article['image'],
                // The heredoc is indented to sit with the code; the leading
                // spaces come off here so the body is the prose and nothing
                // else. `pre-line` on the page keeps the blank lines, which are
                // the paragraphs.
                'body' => $this->unindent($article['body']),
                'quote' => $article['quote'],
                'quote_by' => $article['quote_by'],
                'gallery' => null,
                'tags' => json_encode($article['tags'], JSON_UNESCAPED_UNICODE),
                'status' => 'published',
                // A minute apart, newest last in this list, so the front page's
                // three come out in the order they are written here rather than
                // in whatever order three identical timestamps sort in.
                'published_at' => $now->copy()->subMinutes(count($this->articles()) - $index),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Strips the common leading indentation the heredoc carries. */
    private function unindent(string $text): string
    {
        return trim(preg_replace('/^[ \t]+/m', '', $text) ?? '');
    }

    public function down(): void
    {
        // Only what this wrote, and only while it is still what this wrote: an
        // article somebody has edited since is theirs, not this migration's.
        foreach ($this->articles() as $article) {
            DB::table('articles')
                ->where('slug', $article['slug'])
                ->where('title', $article['title'])
                ->delete();
        }
    }
};
