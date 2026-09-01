<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * «مقالات» — where the shop's own writing is read.
 *
 * «هیچ جایی برای مقالات در سایت نداریم». Two pages: the list, and one article.
 *
 * Nothing here is branch-scoped. An article is the shop's, and it reads the
 * same at every counter — so this sits inside the storefront group like the
 * content pages, and a franchise's visitor gets the same list.
 */
class ArticleController extends Controller
{
    public function index(): View
    {
        return view('pages.articles', [
            'articles' => Article::published()->latest('published_at')->paginate(9),
        ]);
    }

    public function show(Article $article): View
    {
        /*
         * A draft is a 404, not a page with a badge on it.
         *
         * Route-model binding resolves on the slug alone, so without this an
         * article somebody is still writing is readable by anybody who guesses
         * or is sent its address — and the panel's own «پیش‌نویس» link is the
         * likeliest way that address travels.
         */
        if (! $article->isPublished()) {
            throw new NotFoundHttpException('That article is not published.');
        }

        return view('pages.article', [
            'article' => $article,
            // Three more to read, so the page does not end in a wall. Newest
            // first and never this one.
            'more' => Article::published()
                ->whereKeyNot($article->id)
                ->latest('published_at')
                ->limit(3)
                ->get(),
            /*
             * «قبلی» and «بعدی», in the order the list is read.
             *
             * The list is newest first, so «بعدی» — the next one along — is the
             * *older* article and «قبلی» is the newer. Written out because the
             * two are easy to swap and the mistake reads as working links that
             * walk the wrong way.
             *
             * Compared on `published_at` and not on the id: an article can be
             * given the date it was written rather than the date somebody
             * remembered to publish it, and the pager has to follow what the
             * reader sees.
             */
            'newer' => Article::published()
                ->where('published_at', '>', $article->published_at)
                ->oldest('published_at')
                ->first(),
            'older' => Article::published()
                ->where('published_at', '<', $article->published_at)
                ->latest('published_at')
                ->first(),
        ]);
    }
}
