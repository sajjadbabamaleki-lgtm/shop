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
        ]);
    }
}
