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
            // The pull-quote and whoever said it. Both optional, and separate:
            // a quote with no name is a line the article is emphasising, one
            // with a name is somebody being quoted, and the panel has to be
            // able to make either claim.
            'quote' => ['nullable', 'string', 'max:600'],
            'quote_by' => ['nullable', 'string', 'max:120'],
            // One line of comma-separated words. Cleaned in the model rather
            // than here, so an article saved before tags existed still reads.
            'tags' => ['nullable', 'string', 'max:200'],
            // The photographs that go inside the article. `gallery.*` because
            // the field is `multiple`; the rules are the hero image's.
            'gallery' => ['nullable', 'array', 'max:6'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            // What to keep of what is already there. The form posts the paths
            // it still wants, so removing one is unticking a box rather than a
            // second screen — and a path that was never on this article cannot
            // be smuggled in, because the list is intersected with what is
            // stored below.
            'keep' => ['nullable', 'array'],
            'keep.*' => ['string'],
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
            'quote' => 'نقل‌قول', 'quote_by' => 'گویندهٔ نقل‌قول',
            'tags' => 'برچسب‌ها', 'gallery' => 'عکس‌های داخل مقاله',
        ]);

        $slug = trim((string) ($input['slug'] ?? ''));

        $fields = [
            'title' => $input['title'],
            'slug' => $slug !== ''
                ? Article::slugFor($slug, $article?->id)
                : ($article?->slug ?? Article::slugFor($input['title'])),
            'excerpt' => $input['excerpt'] ?? null,
            'body' => $input['body'],
            'quote' => $input['quote'] ?? null,
            // A name with no quote under it would print nothing and look like
            // a lost field; it is dropped rather than stored.
            'quote_by' => ($input['quote'] ?? null) === null ? null : ($input['quote_by'] ?? null),
            'tags' => $this->tags($input['tags'] ?? null),
            'gallery' => $this->gallery($request, $article, $input['keep'] ?? null),
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

    /**
     * One line of comma-separated words into a list.
     *
     * Persian commas as well as Latin ones: «کفش، چرم» is what a Persian
     * keyboard types, and a shop whose tags all had a «،» stuck to them would
     * be a bug nobody could see the cause of. Null rather than an empty list
     * when there are none, so the column reads as «no tags» and not as «a list
     * with nothing in it».
     *
     * @return list<string>|null
     */
    private function tags(?string $line): ?array
    {
        $tags = array_values(array_unique(array_filter(
            array_map(
                static fn (string $tag): string => trim($tag),
                preg_split('/[,،]+/u', (string) $line) ?: []
            ),
            static fn (string $tag): bool => $tag !== '',
        )));

        return $tags === [] ? null : $tags;
    }

    /**
     * What the article's gallery should hold after this save.
     *
     * The kept paths first, in the order the form posted them, then whatever
     * was uploaded this time. **Intersected with what is actually stored**, so
     * a path typed into the form by hand cannot put an arbitrary file on a
     * public page — the field is a checkbox list, and a checkbox list is a
     * request rather than a fact.
     *
     * @param  list<string>|null  $keep
     * @return list<string>|null
     */
    private function gallery(Request $request, ?Article $article, ?array $keep): ?array
    {
        $had = $article?->galleryList() ?? [];

        // `array_values` because `array_intersect` keeps the original keys and
        // a json column with holes in its keys is an object, not a list.
        $paths = $keep === null
            ? $had
            : array_values(array_intersect($keep, $had));

        foreach ($request->file('gallery') ?? [] as $photo) {
            $paths[] = 'storage/'.$photo->store('articles', 'public');
        }

        return $paths === [] ? null : $paths;
    }
}
