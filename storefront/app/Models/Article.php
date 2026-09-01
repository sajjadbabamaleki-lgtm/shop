<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Something the shop wrote.
 *
 * It began as «متن ساده با عکس و تیتر» and grew to the client's fuller
 * reference: a pull-quote, a row of photographs inside the piece, tags, and
 * comments underneath.
 *
 * **Still plain text, though.** The body is stored and printed as text, with
 * the writer's own line breaks kept; what was added is *fields*, each with one
 * job. Nothing here stores markup, because nothing here sanitises it.
 *
 * **No author, at the client's word** — «فقط تاریخ، بدون نویسنده». An article
 * is the shop's, and a byline nobody typed would be a name on words the person
 * did not write.
 *
 * **Not branch-scoped.** An article is the shop's, and the shop is one shop
 * with several counters.
 */
class Article extends Model
{
    use HasFactory;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const LABELS = [
        self::DRAFT => 'پیش‌نویس',
        self::PUBLISHED => 'منتشر شده',
    ];

    protected $fillable = [
        'slug', 'title', 'excerpt', 'image', 'body',
        'quote', 'quote_by', 'gallery', 'tags',
        'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            // Both are lists the panel edits as text and the page walks.
            // Cast rather than decoded at every call site: a `json_decode`
            // in a Blade is how one view ends up handling null differently
            // from the next.
            'gallery' => 'array',
            'tags' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * What the shop prints: published, and not dated into the future.
     *
     * The date is checked as well as the status so that «منتشر شده» with
     * tomorrow's date is a scheduled article rather than a live one — which is
     * the only thing a shop wants from a date field it can edit.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    /**
     * The line under the title on the list.
     *
     * The excerpt when somebody wrote one, and otherwise the opening of the
     * article — which is what an excerpt would have said. Never the raw body:
     * a card with four hundred words in it is not a card.
     */
    public function summary(int $characters = 160): string
    {
        $excerpt = trim((string) $this->excerpt);

        return $excerpt !== ''
            ? $excerpt
            : Str::limit(trim(preg_replace('/\s+/u', ' ', $this->body) ?? ''), $characters, '…');
    }

    /** Every comment on it, whatever its state — the panel's queue. */
    public function comments(): HasMany
    {
        return $this->hasMany(ArticleComment::class);
    }

    /** The ones the page prints: approved, oldest first, the way a thread reads. */
    public function publishedComments(): HasMany
    {
        return $this->comments()->published()->oldest('approved_at');
    }

    /**
     * The tags, cleaned.
     *
     * The panel takes them as one line of comma-separated words, so the list
     * that comes back can carry empty entries and repeats. Both are cleaned
     * here rather than on the way in, so an article saved before this existed
     * still reads correctly.
     *
     * @return list<string>
     */
    public function tagList(): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($tag): string => trim((string) $tag), $this->tags ?? []),
            static fn (string $tag): bool => $tag !== '',
        )));
    }

    /**
     * The photographs inside the article, in the order they were added.
     *
     * @return list<string>
     */
    public function galleryList(): array
    {
        return array_values(array_filter(
            array_map(static fn ($path): string => trim((string) $path), $this->gallery ?? []),
            static fn (string $path): bool => $path !== '',
        ));
    }

    /**
     * Articles carrying a tag.
     *
     * `whereJsonContains` and not a `like`: «کفش» must not match «کفش چرم», and
     * a substring search on a json blob would also match a tag that happened to
     * appear inside a title if the column ever grew one.
     */
    public function scopeTagged(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * A slug the address bar can carry, from a Persian title.
     *
     * `Str::slug()` transliterates, and on Persian there is nothing to
     * transliterate *to* — it returns an empty string and every article would
     * collide on ''. So the letters are kept as they are and only the
     * separators are normalised; a browser percent-encodes them and the URL
     * still reads correctly when it is pasted back into a message.
     *
     * Uniqueness is the caller's to enforce — the column says so — but the
     * suffix loop is here because that is where the rule about what a slug may
     * contain lives.
     */
    public static function slugFor(string $title, ?int $ignore = null): string
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $title) ?? '', '-');

        if ($base === '') {
            $base = 'مقاله';
        }

        $slug = $base;
        $n = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn (Builder $q) => $q->whereKeyNot($ignore))
            ->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
