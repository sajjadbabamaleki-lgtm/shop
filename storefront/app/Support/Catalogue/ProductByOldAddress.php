<?php

namespace App\Support\Catalogue;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The shoe an address from the previous website was naming.
 *
 * **ترب hold ~38 addresses this site has never served**, and the one they sent
 * on 2026-09-21 shows why. Measured against the live site from a runner:
 *
 *   they hold  /product/کفش/کتونی-نایک-مدل-وومرو-۵-رنگ-قهوه-ای-nike-vomero-5
 *   the shop   /products/کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای
 *
 * All three shapes of the first answered **404**, and the shoe itself is on
 * sale — that exact colour. Two different things are wrong with that address
 * and only one of them is the path: the *slug* is a different string as well.
 * The old site wrote «مدل», spelled the five in Persian digits, and put the
 * Latin name last where this shop puts it in the middle. So nothing that
 * compares slugs — not exactly, not folded, not case-insensitively — can match
 * them, and the four reasons a product normally leaves the feed are all
 * innocent here.
 *
 * **What survives is the words.** Both strings name the same shoe in the same
 * language, so this scores a candidate by how many of the old address's words
 * it carries, and takes the winner.
 *
 * **It scores the shop's own slug as well as its title, and that is not a
 * refinement — it is the whole of why the first version of this file shipped
 * green and answered 404 on the live site.** The catalogue in this repository
 * names a shoe «کتونی نایک وومرو Nike Vomero 5 رنگ قهوه ای» and the live shop
 * does not. Read off the live sitemap and the live product pages on
 * 2026-09-21, all 148 of them:
 *
 *   title  کتونی نایک وومرو                              ← five of these, identical
 *   slug   کتونی-نایک-وومرو-Nike-Vomero-5-رنگ-قهوه-ای    ← the colour, the make, the number
 *
 * `basalam:import` writes a terse title and a slug carrying everything, so on
 * this shop the *slug* is where a product's identity actually lives — and a
 * title is not merely thinner, it is identical across a whole colourway
 * family. Scored on titles alone the live vocabulary holds no colour word, no
 * Latin name and no «۵», so most of the address's words count for nothing,
 * `wanted` falls under SHORTEST and every one of the addresses ترب sent was
 * refused before it was ever scored. Simulated against those 148 slugs: nine
 * of nine refused on titles, nine of nine correct with the slug in.
 *
 * **It is not the fuzzy matching `product_group_id` refuses**, and the
 * difference is worth being precise about. That one has to decide that two
 * *different live products* are one shoe — a judgement with no right answer
 * available here. This one is given a string the shop itself published as the
 * address of one particular shoe, and only has to find that shoe again. The
 * shop wrote both names.
 *
 * Three fences, because a wrong answer here points an aggregator at the wrong
 * product, which is worse than the 404 it replaces:
 *
 *  - **The winner must be alone.** A tie returns nothing, the same rule
 *    `ReplacePhotos::theOneProductNamed()` follows. Measured on the address
 *    above against the live catalogue: the brown Vomero scores 8 of 8, the
 *    navy one 7, the mocha and the white 6 — the colour word is what
 *    separates them, which is exactly what should.
 *  - **It must carry most of the address.** Below `ENOUGH` of the words, the
 *    match is a coincidence between two shoes of the same make.
 *  - **A short address is refused outright.** Two or three words name a
 *    category, not a shoe.
 *
 * Only ever `listable()` products: an old address may lead to something the
 * shop is selling today or to nothing at all.
 */
final class ProductByOldAddress
{
    /** How much of the address's *usable* words the winner has to carry. */
    private const ENOUGH = 0.6;

    /** Fewer usable words than this and the address does not name one shoe. */
    private const SHORTEST = 4;

    /**
     * @param  string  $address  an old slug, or the title of a retired product
     * @param  Collection<int, Product>|null  $catalogue  loaded once by a caller resolving several
     */
    public static function find(string $address, ?Collection $catalogue = null): ?Product
    {
        $catalogue ??= self::catalogue();

        $identities = $catalogue->map(
            fn (Product $product) => self::words($product->title.' '.$product->slug)
        );

        /*
         * **Only the words this shop actually uses are allowed to count**, and
         * this one line is what makes the rule survive the addresses ترب sent
         * on 2026-09-21. Three kinds of noise are in them and all three are
         * silent here:
         *
         *   …-رنگ-سفید-مشکی-ai        the old site cut the slug mid-word
         *   …-رنگ-سفید-air-jor        and again
         *   …-مدل-…  /  محصول-…       filler this shop's titles never carry
         *
         * Scored against the raw word list, `ai`, `jor` and «مدل» can never
         * match anything, so they only drag the fraction down — a shoe that is
         * plainly the right one lands under the threshold and the address 404s
         * exactly as before. A word that appears in no title on the shop is not
         * evidence about which shoe is meant, so it is not counted either way.
         * A word this shop *does* use — in a title or in a slug — counts.
         *
         * It also tightens the other direction, which matters more: an address
         * for a shoe this shop simply does not sell keeps almost none of its
         * words, falls under SHORTEST, and is refused rather than matched to
         * whatever scored highest.
         */
        $vocabulary = $identities->flatten()->unique()->flip();

        $wanted = array_values(array_filter(
            self::words($address),
            fn (string $word) => $vocabulary->has($word),
        ));

        if (count($wanted) < self::SHORTEST) {
            return null;
        }

        $scored = $catalogue->values()
            ->map(fn (Product $product, int $i) => [
                'product' => $product,
                'score' => count(array_intersect($wanted, $identities->values()[$i])),
                /*
                 * **How much the title says that the address did not**, and it
                 * is the tie-break rather than a second score.
                 *
                 * Two of the addresses ترب sent are the same shoe in white:
                 * one ends «…رنگ-سفید-مشکی-ai», the other «…رنگ-سفید-air-jor».
                 * The live shop sells a white Jordan One Low, a white-and-black
                 * one, a white-and-pink one and a white-and-red one, and *every*
                 * word of the shorter address is in all four — measured, all
                 * four score 9 of 9. On intersection alone they tie and a rule
                 * that refuses ties answers 404 for a shoe that is on sale.
                 *
                 * Among shoes that satisfy the address equally, the one that
                 * adds least beyond it is the one the address names: the plain
                 * white adds 2 words, the other three add 3. The black-and-white
                 * one is reached by the address that actually says «مشکی», which
                 * outscores it outright.
                 */
                'extras' => count(array_diff($identities->values()[$i], $wanted)),
            ])
            ->sortBy([['score', 'desc'], ['extras', 'asc']])
            ->values();

        $best = $scored->first();

        if ($best === null || $best['score'] < ceil(count($wanted) * self::ENOUGH)) {
            return null;
        }

        // Alone, or nothing. Two shoes that match the same words *and* say the
        // same amount besides is the shop unable to tell them apart, not a
        // reason to pick one.
        $runnerUp = $scored->get(1);

        if ($runnerUp !== null
            && $runnerUp['score'] === $best['score']
            && $runnerUp['extras'] === $best['extras']) {
            return null;
        }

        return $best['product'];
    }

    /**
     * The shoe the shop sells in place of a retired row, by these words.
     *
     * **A retired product is asked about by its title *and* its slug**, and
     * on this shop the slug is the half that carries the colour: the live
     * titles are terse, so «ونس آدیداس سامبا» is three words — under SHORTEST
     * — while its slug adds the make, the Latin name and «رنگ نسکافه ای».
     * Asked on the title alone every retired row on this catalogue is refused
     * for being too short, which is a wrong answer dressed as a careful one.
     *
     * It is a named constructor rather than the same two lines in
     * `ProductController` and `TorobFeedController`, because those two
     * disagreeing about one address is the fault this repository has already
     * paid for three times.
     *
     * **It does not become a guess.** Measured against the live catalogue: a
     * retired row naming no colour still gets nothing — `jordan-one-air`
     * carries «کتونی جردن وان» plus `jordan` and `air`, several Jordans score
     * it identically, and the tie is refused, which is the answer the client
     * decided on for that address. Only a row that names a colour resolves.
     *
     * @param  Collection<int, Product>|null  $catalogue  loaded once by a caller resolving several
     */
    public static function forRetired(Product $retired, ?Collection $catalogue = null): ?Product
    {
        return self::find($retired->title.' '.$retired->slug, $catalogue);
    }

    /** Everything this branch is selling, for a caller resolving one address. */
    public static function catalogue(): Collection
    {
        return Product::query()
            ->listable()
            ->with(['variants.offer', 'variants.stock'])
            ->get();
    }

    /**
     * The words of a title or a slug, folded so two keyboards agree.
     *
     * `fold_persian()` settles ی/ي, ک/ك, the zero-width joiners and — the one
     * that matters here — «۵» against «5». Lowercasing settles `Nike` against
     * `nike`. Single characters go: they are what is left of a size or a
     * stray letter, and they match everything.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $parts = preg_split(
            '~[^\p{L}\p{N}]+~u',
            mb_strtolower(fold_persian($text)),
            -1,
            PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        return array_values(array_unique(
            array_filter($parts, fn (string $word) => mb_strlen($word) > 1)
        ));
    }
}
