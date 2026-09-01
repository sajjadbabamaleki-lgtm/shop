<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Something the shop wrote — «متن ساده با عکس و تیتر».
 *
 * A title, a photograph and plain text. No categories, no tags, no author
 * table: every one of those is a screen somebody has to fill in before an
 * article can be published, and what was asked for is somewhere to write.
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

    protected $fillable = ['slug', 'title', 'excerpt', 'image', 'body', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
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
