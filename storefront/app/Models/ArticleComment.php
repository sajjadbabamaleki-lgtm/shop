<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a reader said under an article.
 *
 * **A different gate from `ProductComment`, and it has to be.** A shoe's
 * comment is open to «فقط کسی که خریده», and that purchase is what makes it
 * worth reading. An article has no purchase behind it, so the rule here is a
 * signed-in customer and the shop reading it first.
 *
 * Not branch-scoped, like the article it hangs off: the shop is one shop with
 * several counters.
 *
 * The three states and their words are `ProductComment`'s, quoted rather than
 * redefined — the panel shows both queues on one screen, and two vocabularies
 * for one decision is how the two drift apart.
 */
class ArticleComment extends Model
{
    use HasFactory;

    public const PENDING = ProductComment::PENDING;

    public const PUBLISHED = ProductComment::PUBLISHED;

    public const REJECTED = ProductComment::REJECTED;

    public const LABELS = ProductComment::LABELS;

    protected $fillable = ['article_id', 'customer_id', 'body', 'status', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The only scope the page itself may read. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    /**
     * The name over the comment, and the initial in the round mark.
     *
     * Both are `ProductComment`'s, for the same reasons written out there: the
     * account often has no name, and the telephone number it does have is the
     * credential this shop signs people in with and never goes on a public
     * page whole.
     */
    public function authorName(): string
    {
        return $this->asProductComment()->authorName();
    }

    public function authorIsNumber(): bool
    {
        return $this->asProductComment()->authorIsNumber();
    }

    public function authorInitial(): string
    {
        return $this->asProductComment()->authorInitial();
    }

    /**
     * A throwaway carrying this comment's customer, so the three above can be
     * one implementation rather than two copies.
     *
     * Copied rather than inherited because the two tables are genuinely
     * different — different subject, different gate, different rule about how
     * many one person may leave — and a shared parent would tie them together
     * in every way except the one they actually share.
     */
    private function asProductComment(): ProductComment
    {
        $stand = new ProductComment;
        $stand->setRelation('customer', $this->customer);

        return $stand;
    }
}
