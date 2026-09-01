<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where the shop's articles are written.
 *
 * «هیچ جایی برای مقالات در سایت نداریم», and «متن ساده با عکس و تیتر». One
 * form with five fields on it, and that is the whole panel side — no editor,
 * no media library, no revisions. A screen that asks for more than was asked
 * for is a screen nobody finishes filling in.
 *
 * Platform-scoped: an article is the shop's, and the shop is one shop with
 * several counters. `platform.article.manage`, its own permission, because
 * what the shop sells and what the shop says are two jobs.
 */
class ArticleController extends Controller
{
    public function index(): View
    {
        return view('admin.articles', [
            'articles' => Article::query()->latest('created_at')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.article', ['article' => new Article]);
    }

    public function edit(Article $article): View
    {
        return view('admin.article', ['article' => $article]);
    }

    public function store(Request $request): RedirectResponse
    {
        $article = Article::create($this->fields($request));

        return redirect()->route('admin.article.edit', $article)->with('status', 'مقاله ثبت شد.');
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $article->update($this->fields($request, $article));

        return redirect()->route('admin.article.edit', $article)->with('status', 'مقاله به‌روز شد.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return redirect()->route('admin.articles')->with('status', 'مقاله حذف شد.');
    }

    /**
     * The five fields, validated, plus the two the form does not ask for.
     *
     * The slug is written from the title when the field is left empty and kept
     * when it is not: an article's address is what other sites link to, so it
     * must not move every time a headline is reworded. `Article::slugFor()`
     * knows why `Str::slug()` cannot be used on a Persian title.
     *
     * `published_at` is filled in the moment something is first published and
     * left alone afterwards, so re-saving a live article does not restamp it
     * to today and jump it back to the top of the list. It is cleared when an
     * article goes back to «پیش‌نویس», because a date on a draft is a claim
     * that it went out.
     *
     * @return array<string, mixed>
     */
    private function fields(Request $request, ?Article $article = null): array
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'max:40000'],
            'status' => ['required', Rule::in(array_keys(Article::LABELS))],
            // Stored on the `public` disk, the same as a product photograph.
            // **On a container that is not permanent** — a redeploy starts
            // from a fresh filesystem — so a persistent disk has to be mounted
            // at storage/app/public before this is used in anger. The same
            // note is on `CatalogueController::storeMedia`, and it is written
            // twice on purpose: it is the kind of thing that is discovered
            // when a month of photographs disappears.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [], [
            'title' => 'عنوان', 'slug' => 'نشانی', 'excerpt' => 'خلاصه',
            'body' => 'متن', 'status' => 'وضعیت', 'image' => 'عکس',
        ]);

        $slug = trim((string) ($input['slug'] ?? ''));

        $fields = [
            'title' => $input['title'],
            'slug' => $slug !== ''
                ? Article::slugFor($slug, $article?->id)
                : ($article?->slug ?? Article::slugFor($input['title'])),
            'excerpt' => $input['excerpt'] ?? null,
            'body' => $input['body'],
            'status' => $input['status'],
        ];

        if ($input['status'] === Article::PUBLISHED) {
            $fields['published_at'] = $article?->published_at ?? now();
        } else {
            $fields['published_at'] = null;
        }

        if ($request->hasFile('image')) {
            $fields['image'] = 'storage/'.$request->file('image')->store('articles', 'public');
        }

        return $fields;
    }
}
