<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The shoes the panel made and never put on the shop.
 *
 * `2026_09_19_…_let_the_panel_edit_a_price` stopped this happening again: the
 * product form opens with today's date in it now. **That fixes the next
 * product and not the last one** — every shoe added through the panel before
 * today was saved with `published_at` null, so it sits in the catalogue marked
 * «فعال» and appears on no page of the shop. The client photographed two of
 * them. «اره بکن» is the instruction to put them up.
 *
 * **The fingerprint is `status = 'active'` with no `published_at`, and it is
 * precise rather than convenient.** Nothing else in this application produces
 * that pair:
 *
 * - `basalam:import` creates an unpublished product as **`draft`**, so a
 *   staged import is not swept up here — the importer's own comment says a
 *   re-run must not put back a product a shopkeeper archived, and this must
 *   not either.
 * - The five setup shoes and the payment-test product were retired to
 *   **`archived`** with their dates cleared, so they stay off.
 * - A shoe somebody deliberately took off the shop by emptying that date would
 *   match — and until today the box was empty on every new product, so there
 *   was no reason for anybody to have found it, let alone used it. It is also
 *   the one case that is undone in one click, from the same field, which now
 *   says what it does.
 *
 * `published_at` is set to **now** and not backdated: the shoe goes on the
 * shop today, which is true, and a date invented into the past is a claim
 * about when it was for sale.
 *
 * **It never throws.** `liara_pre_start.sh` runs `migrate --force` under
 * `set -eu`, so a migration that throws does not fail a chore — it stops the
 * shop from starting. It says what it did in the log instead, with the prefix
 * that script uses, because a repair nobody can see the result of is a repair
 * somebody has to do again by hand.
 *
 * `down()` does not take them off again. Unpublishing a shoe the shop has been
 * selling is a decision about the shop, and it is one click in the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $stranded = DB::table('products')
                ->where('status', 'active')
                ->whereNull('published_at')
                ->get(['id', 'title']);

            if ($stranded->isEmpty()) {
                return;
            }

            DB::table('products')
                ->whereIn('id', $stranded->pluck('id'))
                ->update(['published_at' => now(), 'updated_at' => now()]);

            $this->say($stranded->count().' product(s) were active and unpublished, and are now on the shop: '
                .$stranded->pluck('title')->implode('، '));
        } catch (Throwable $e) {
            $this->say('could not publish the stranded products: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Deliberately empty. See the note above.
    }

    private function say(string $message): void
    {
        Log::warning('put_the_unpublished_panel_products_on_the_shop: '.$message);

        // The same prefix `liara_pre_start.sh` uses, so it reads as one log.
        echo 'storefront: '.$message.PHP_EOL;
    }
};
