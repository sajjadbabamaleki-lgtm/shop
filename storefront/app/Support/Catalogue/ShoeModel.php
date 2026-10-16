<?php

namespace App\Support\Catalogue;

/**
 * Which model a product is, read off its title — for spreading a band of
 * cards across the catalogue, and for nothing else.
 *
 * «باید از ۸ مدل متفاوت باشن» — the home page's «فروشگاه» band took the
 * eight newest shoes, and the eight newest were all one model in eight
 * colourways, because a supplier lists each colour as its own product.
 *
 * The rule: drop the words that say what kind of thing it is («کتونی»,
 * «صندل», «مدل»…), stop at «رنگ» (everything after it is the colourway), and
 * the first two words left are the model. So «گلدن استار» and «گلدن استار
 * ساق بلند» are one model, «کتونی گلدن گوس رنگ صورتی» is «گلدن گوس», and
 * «کتونی نایک وومرو» is not «کتونی نایک وی۲کی ران».
 *
 * **It is deliberately loose, and that is safe only because of what it is
 * for.** Merging two models here costs one card a different shoe; it is not
 * `product_group_id`, where the same mistake merges two shoes in public.
 */
final class ShoeModel
{
    /** Words that name a kind of thing, a wearer or a filler, not a model. */
    private const NOT_THE_MODEL = [
        'کتونی', 'کفش', 'صندل', 'بوت', 'نیم', 'نیمبوت', 'کیف', 'ونس', 'اسنیکر',
        'زنانه', 'مردانه', 'دخترانه', 'پسرانه', 'اسپرت', 'مجلسی', 'رسمی', 'روزمره',
        'مدل', 'طرح', 'جدید',
    ];

    public static function key(string $title): string
    {
        $words = preg_split('~\s+~u', trim(fold_persian(mb_strtolower($title))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $skip = array_map(fn (string $w) => fold_persian($w), self::NOT_THE_MODEL);

        $kept = [];
        foreach ($words as $word) {
            if ($word === fold_persian('رنگ')) {
                break;
            }
            if (! in_array($word, $skip, true)) {
                $kept[] = $word;
            }
            if (count($kept) === 2) {
                break;
            }
        }

        return implode(' ', $kept) ?: fold_persian($title);
    }
}
