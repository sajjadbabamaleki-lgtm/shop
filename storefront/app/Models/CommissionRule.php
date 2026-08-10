<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What the platform charges, and on what.
 *
 * A percentage is stored in basis points — 1250 is 12.5% — so a rate is an
 * integer like every other number in this application and nothing is rounded
 * before the money is computed.
 *
 * Audited: a commission rate is the first thing §29 names.
 */
class CommissionRule extends Model
{
    use HasFactory, RecordsAudits;

    public const GLOBAL = 'global';

    public const VENDOR = 'vendor';

    public const CATEGORY = 'category';

    public const PRODUCT = 'product';

    /**
     * Most specific first. A product rule beats a category rule, which beats
     * a vendor rule, which beats the platform default.
     */
    public const SPECIFICITY = [self::PRODUCT, self::CATEGORY, self::VENDOR, self::GLOBAL];

    protected $fillable = ['scope', 'scope_id', 'type', 'value', 'priority', 'is_active'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'priority' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * What this rule takes on one line, in integer Rial.
     *
     * Percentages use intdiv, so the platform never takes a fraction of a Rial
     * and the vendor is never short one: the remainder stays with the seller.
     *
     * Not called `on()`: Eloquent already has a static `Model::on()` for
     * choosing a connection, and PHP will not let a class have both.
     */
    public function amountOn(int $lineTotal, int $quantity): int
    {
        return $this->type === 'percent'
            ? intdiv($lineTotal * $this->value, 10_000)
            : $this->value * $quantity;
    }

    public function describe(): string
    {
        return $this->type === 'percent'
            ? fa_number($this->value / 100).'٪'
            : fa_number(intdiv($this->value, 10)).' تومان به ازای هر عدد';
    }
}
