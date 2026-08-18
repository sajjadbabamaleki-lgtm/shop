<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person in one thread, and how much of it they have read.
 *
 * `party` is «customer» or «staff» and `party_id` is the id in that table.
 * Two guards, two tables, two words — a polymorphic class string would put a
 * fully-qualified name in a column nothing validates, and the two kinds here
 * are never going to be three.
 */
class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'party', 'party_id', 'last_read_message_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
