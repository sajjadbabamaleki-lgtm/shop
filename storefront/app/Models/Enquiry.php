<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody asking to buy in bulk, to open a branch, or simply asking.
 *
 * One model for all three, because they are one thing — a name, a telephone
 * number and a sentence, waiting for somebody to ring back. What differs is
 * the heading on the form, and that is a `kind`.
 *
 * `SUPPORT` is the third and the odd one: the other two are offers to do
 * business and this one is a question or a complaint. It is here rather than
 * in a table of its own because the shop answers all three the same way — a
 * person reads it in `/admin/enquiries` and telephones back — and because
 * everything downstream is generated from `kinds()`: the two routes, the
 * panel's filter, the form. A second table would have been a second inbox for
 * somebody to forget to look in.
 *
 * Not branch-scoped: see the migration.
 */
class Enquiry extends Model
{
    use HasFactory;

    public const WHOLESALE = 'wholesale';

    public const FRANCHISE = 'franchise';

    public const SUPPORT = 'support';

    public const NEW = 'new';

    public const CONTACTED = 'contacted';

    public const CLOSED = 'closed';

    protected $fillable = [
        'kind', 'name', 'phone', 'city', 'organisation', 'message', 'status',
        'handled_by', 'handled_at',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /**
     * The three kinds, as the panel and the routes name them.
     *
     * **This list is the source of both routes and both screens.** Adding to
     * it gives the new kind its page, its form, its throttle and its place in
     * the panel; what it does not give it is room in the `kind` column, which
     * is a CHECK constraint and needs a migration.
     *
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            self::WHOLESALE => 'فروش عمده',
            self::FRANCHISE => 'اخذ نمایندگی',
            self::SUPPORT => 'پشتیبانی و سؤال',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::NEW => 'جدید',
            self::CONTACTED => 'تماس گرفته شد',
            self::CLOSED => 'بسته شد',
        ];
    }

    public function kindLabel(): string
    {
        return self::kinds()[$this->kind] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Unhandled first, then newest — the order somebody working through them
     * wants, rather than the order they arrived in.
     */
    public function scopeWorkList(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when status = ? then 0 else 1 end', [self::NEW])
            ->orderByDesc('created_at');
    }
}
