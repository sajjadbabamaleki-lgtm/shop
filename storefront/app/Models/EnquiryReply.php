<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer the shop sent to an enquiry, by text message.
 */
class EnquiryReply extends Model
{
    protected $fillable = ['enquiry_id', 'user_id', 'body', 'sent_at', 'failure'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
