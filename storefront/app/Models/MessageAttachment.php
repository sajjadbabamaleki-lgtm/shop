<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file somebody attached.
 *
 * `path` is on the **private** disk. A photograph of a damaged parcel, or of a
 * receipt with a name and an address on it, is the customer's — putting it on
 * the public disk would give it a URL that works for anybody who guesses it
 * and needs no sign-in at all. It is streamed by a controller that checks
 * membership of the thread first.
 */
class MessageAttachment extends Model
{
    protected $fillable = ['message_id', 'path', 'name', 'mime', 'size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** «۲۴۰ کیلوبایت», rather than 245760. */
    public function readableSize(): string
    {
        return $this->size >= 1048576
            ? fa_number((int) round($this->size / 1048576)).' مگابایت'
            : fa_number(max(1, (int) round($this->size / 1024))).' کیلوبایت';
    }
}
