<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product's place in one band of the front page.
 *
 * Not branch-scoped, deliberately: the front page being edited is the shop's
 * own, and a franchise's page is the same page. If a branch ever needs its own
 * cast this gains a nullable branch_id and the reads gain a fallback — which is
 * a different feature from the one that was asked for.
 */
class FrontPagePlacement extends Model
{
    protected $fillable = ['band', 'product_id', 'position'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
