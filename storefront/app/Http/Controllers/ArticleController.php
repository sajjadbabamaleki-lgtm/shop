<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleComment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function index(Request $request): View
    {
        /*
         * «برچسب‌ها» — the chips under an article, which have to lead
         * somewhere or they are decoration. A tag that matches nothing is
         * treated as no filter at all rather than as an empty page: the
         * address is typed by hand as often as it is clicked.
         */
        $tag = trim((string) $request->query('tag'));

        $articles = Article::published()
            ->when($tag !== '', fn ($query) => $query->tagged($tag))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('pages.articles', [
            'articles' => $articles,
            'tag' => $tag === '' ? null : $tag,
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
            // Approved only, oldest first — a thread reads the way it was
            // written. The scope is on the relation, so a view cannot print an
            // unread sentence by reaching for `$article->comments`.
            'comments' => $article->publishedComments()->with('customer')->get(),
        ]);
    }

    /**
     * A reader's comment under an article.
     *
     * **Signed in, and nothing more.** A shoe's comment is open to «فقط کسی که
     * خریده» and that purchase is what makes it worth reading; an article has
     * no purchase behind it, so the same rule would close the box to everybody.
     * The account is a verified telephone number, and that is what stands
     * between this and a form anybody on the internet can post into.
     *
     * Stored `PENDING` like everything else this shop takes from the public.
     * Nothing on the storefront publishes on submission.
     */
    public function comment(Request $request, Article $article): RedirectResponse
    {
        if (! $article->isPublished()) {
            throw new NotFoundHttpException('That article is not published.');
        }

        $input = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:1500'],
        ], [], ['body' => 'نظر']);

        ArticleComment::create([
            'article_id' => $article->id,
            'customer_id' => Auth::guard('customer')->id(),
            'body' => $input['body'],
            'status' => ArticleComment::PENDING,
        ]);

        return back()
            ->with('comment_status', 'نظر شما ثبت شد و پس از بررسی منتشر می‌شود.')
            ->withFragment('vp-art-talk');
    }
}
