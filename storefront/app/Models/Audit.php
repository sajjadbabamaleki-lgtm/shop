<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * One recorded change. Append-only: see the boot method.
 *
 * @property-read string $action
 */
class Audit extends Model
{
    /** Rows here are written once and never touched again. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type', 'actor_id', 'action',
        'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'context',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The trail is worthless if it can be edited, so the model refuses rather
     * than trusting everyone to remember. Nothing in the application updates
     * or deletes an audit row; if that ever needs to change — a retention
     * policy, say — it should be a deliberate, visible exception here.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit records cannot be changed once written.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit records cannot be deleted.');
        });
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record something worth being able to explain later.
     *
     * The actor defaults to whoever is signed in — staff or customer — and
     * stays null when nothing is, which is the honest answer for a scheduled
     * job or a console command rather than attributing it to nobody.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>  $context
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        array $context = [],
        ?Model $actor = null,
    ): self {
        $actor ??= Auth::guard('web')->user() ?? Auth::guard('customer')->user();

        return self::create([
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'context' => $context ?: null,
        ]);
    }
}
