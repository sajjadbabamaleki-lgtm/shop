<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line in a thread.
 *
 * Not branch-scoped itself: it reaches its branch through the conversation,
 * which is scoped, and adding a second `branch_id` here would be a second
 * answer to the same question — one that could disagree with the first.
 *
 * A message is never edited and never deleted. What was said was said, and a
 * thread somebody can rewrite is not a record of anything.
 */
class Message extends Model
{
    protected $fillable = ['conversation_id', 'party', 'party_id', 'body'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function isFrom(string $party, ?int $id = null): bool
    {
        return $this->party === $party && ($id === null || $this->party_id === $id);
    }

    /** Who to print above the line. */
    public function author(): string
    {
        return match ($this->party) {
            Conversation::STAFF => $this->staffName(),
            Conversation::SYSTEM => 'سیستم',
            default => 'مشتری',
        };
    }

    private function staffName(): string
    {
        return $this->party_id !== null
            ? (User::find($this->party_id)?->name ?? 'فروشگاه')
            : 'فروشگاه';
    }
}
