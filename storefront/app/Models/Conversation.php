<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thread between a customer and a shop — §11.
 *
 * Branch-scoped, which is the whole of its security: a manager in Shiraz
 * asking for conversation 41 by its id gets a 404 from the model layer rather
 * than a page, in exactly the way an order does. There is no `where('branch_id')`
 * anywhere in the controllers because there is nothing to remember.
 *
 * A conversation may hang off an order or stand alone. Both are ordinary — a
 * question about a size is not about an order — so `order` is nullable and
 * every screen has to be written for both.
 */
class Conversation extends Model
{
    use BelongsToBranch;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    /** The two kinds of party in a thread, used by participants and messages. */
    public const CUSTOMER = 'customer';

    public const STAFF = 'staff';

    /** A line the application wrote itself — «سفارش ارسال شد» — with no author. */
    public const SYSTEM = 'system';

    protected $fillable = [
        'branch_id', 'customer_id', 'order_id', 'subject', 'status', 'last_message_at',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function statusLabel(): string
    {
        return $this->isOpen() ? 'باز' : 'بسته';
    }

    /**
     * The participant row for one person, made if it is not there.
     *
     * A member of staff joins a thread by opening it, so the row cannot be
     * required to exist beforehand — and the row is what carries their read
     * cursor, so «unread» would be meaningless without it.
     */
    public function participantFor(string $party, int $id): ConversationParticipant
    {
        return $this->participants()->firstOrCreate(
            ['party' => $party, 'party_id' => $id],
            ['last_read_at' => null]
        );
    }

    /**
     * How many messages this person has not seen.
     *
     * Their own messages never count: writing something is having read it, and
     * a thread that shows «۱ تازه» the moment you reply to it is a thread
     * nobody trusts the number on.
     *
     * Counted against the cursor's **message id**, never a timestamp — see the
     * migration for why a time cannot answer this.
     */
    public function unreadFor(string $party, int $id): int
    {
        $since = (int) ($this->participants
            ->firstWhere(fn (ConversationParticipant $p): bool => $p->party === $party && $p->party_id === $id)
            ?->last_read_message_id ?? 0);

        return $this->messages()
            ->where('id', '>', $since)
            ->where(function (Builder $q) use ($party, $id): void {
                $q->where('party', '!=', $party)->orWhere('party_id', '!=', $id);
            })
            ->count();
    }

    /** The id of the newest line, which is what «read up to here» means. */
    public function latestMessageId(): int
    {
        return (int) $this->messages()->max('id');
    }

    /**
     * Threads this member of staff has not caught up with.
     *
     * One `EXISTS`: is there a line newer than their cursor that is not their
     * own? That is the whole definition, and writing it as one question rather
     * than as «has been spoken in since» plus «and the last word was not mine»
     * removes the case those two got wrong together — a customer message
     * followed by somebody else's reply is still unread to this person, and
     * the two-part version called it read because the last word was not the
     * customer's.
     *
     * Somebody who has never opened a thread has no participant row; the
     * subquery returns null and `COALESCE(…, 0)` makes that mean «nothing
     * read», which is right — «no row» must mean unread, not excluded.
     */
    public function scopeUnreadByStaff(Builder $query, int $userId): Builder
    {
        return $query->whereExists(function ($sub) use ($userId): void {
            $sub->selectRaw('1')
                ->from('messages as m')
                ->whereColumn('m.conversation_id', 'conversations.id')
                ->whereRaw('m.id > COALESCE((
                    SELECT p.last_read_message_id FROM conversation_participants p
                    WHERE p.conversation_id = conversations.id
                      AND p.party = ? AND p.party_id = ?
                ), 0)', [self::STAFF, $userId])
                ->where(function ($w) use ($userId): void {
                    $w->where('m.party', '!=', self::STAFF)
                        ->orWhere('m.party_id', '!=', $userId);
                });
        });
    }
}
