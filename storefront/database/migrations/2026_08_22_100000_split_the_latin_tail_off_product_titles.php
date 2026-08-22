<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The English name comes out of the title and goes somewhere the search can
 * still find it.
 *
 * «اسم انگلیسی کفشارو نمیشه ازشون حذف کرد ولی یجایی گذاشت تو بک اند … که وقتی
 * به انگلیسی سرچ میشه اون کفش بیاد و ولی تو اسم کفش بصورت ظاهری نباشه.»
 *
 * A supplier's title carries the name twice — «کتونی نایک جردن وان ساق کوتاه
 * Air Jordan 1 Low رنگ سفید قرمز» — and printed whole it is a heading in two
 * alphabets. The card already cut it for display; this moves the cut into the
 * data, so every place that prints a title gets the short one without any of
 * them having to know why.
 *
 * **A migration and not a seeder change.** `catalogue:seed` only runs on an
 * empty catalogue and production has not been empty for weeks, so a title that
 * has to move moves here or it ships green and changes nothing. See the note
 * in CLAUDE.md and `2026_08_16_070000_put_the_real_marks_on_the_brands.php`.
 *
 * Nothing is lost: `title` and `title_latin` put back together with a space
 * are the string that was there before, which is what `down()` does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('title_latin')->nullable()->after('title');
        });

        // Chunked by id and written one row at a time: the split is a PHP
        // regex over Unicode letter properties, which is not something to
        // rebuild in SQL for a table this size.
        Product::query()
            ->select('id', 'title')
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    [$head, $tail] = Product::splitTitle((string) $product->title);

                    if ($tail === '') {
                        continue;
                    }

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['title' => $head, 'title_latin' => $tail]);
                }
            });
    }

    public function down(): void
    {
        Product::query()
            ->select('id', 'title', 'title_latin')
            ->whereNotNull('title_latin')
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['title' => trim($product->title.' '.$product->title_latin)]);
                }
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('title_latin');
        });
    }
};
