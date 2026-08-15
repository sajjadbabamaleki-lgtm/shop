<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody asking to buy in bulk, or to open a branch.
 *
 * One model for both, because they are one thing — a name, a telephone number
 * and a sentence, waiting for somebody to ring back. What differs is the
 * heading on the form, and that is a `kind`.
 *
 * Not branch-scoped: see the migration.
 */
class Enquiry extends Model
{
    use HasFactory;

    public const WHOLESALE = 'wholesale';

    public const FRANCHISE = 'franchise';

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
     * The two kinds, as the panel and the routes name them.
     *
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            self::WHOLESALE => 'فروش عمده',
            self::FRANCHISE => 'اخذ نمایندگی',
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
