<?php

namespace App\Models\Concerns;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an audit row whenever a model that uses this trait is created,
 * changed or deleted.
 *
 * Applied deliberately, not to everything. Spec §29 names the changes worth
 * recording — a commission rate, a branch price, a manual stock adjustment, a
 * settlement, a permission, a suspension — and a trail that also carries every
 * page view of a product is a trail nobody reads.
 *
 * Only the attributes that actually changed are stored, and only the ones a
 * model is willing to have written down: override $auditExcept for anything
 * that must never appear in a log, such as a password hash or a token.
 */
trait RecordsAudits
{
    public static function bootRecordsAudits(): void
    {
        static::created(function (Model $model): void {
            Audit::record(
                $model->auditAction('created'),
                $model,
                null,
                $model->auditableAttributes($model->getAttributes()),
            );
        });

        static::updated(function (Model $model): void {
            $changed = $model->auditableAttributes($model->getChanges());

            // Nothing a reader would care about — a touched timestamp on its
            // own is not a change worth a row.
            if ($changed === []) {
                return;
            }

            Audit::record(
                $model->auditAction('updated'),
                $model,
                $model->auditableAttributes(
                    array_intersect_key($model->getOriginal(), $changed)
                ),
                $changed,
            );
        });

        static::deleted(function (Model $model): void {
            Audit::record(
                $model->auditAction('deleted'),
                $model,
                $model->auditableAttributes($model->getOriginal()),
            );
        });
    }

    /**
     * `branch_offer.updated`, `vendor.suspended` — the table name, then what
     * happened, so actions group naturally when the trail is read.
     */
    public function auditAction(string $event): string
    {
        return str($this->getTable())->singular()->snake()->value().'.'.$event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $except = array_merge(
            ['updated_at', 'created_at', 'remember_token'],
            property_exists($this, 'auditExcept') ? $this->auditExcept : [],
            $this->getHidden(),
        );

        return array_diff_key($attributes, array_flip($except));
    }
}
