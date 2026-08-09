<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pushes the marketplace and franchise rules down to the database, on the same
 * principle as the catalogue's stock constraints: the rules that must never be
 * violated do not live in whichever service happened to be written last.
 *
 * The three that matter most:
 *
 *  - An order item must name exactly one seller of the kind it claims. This is
 *    the enforcement half of §2's critical design rule. Without it,
 *    `seller_kind = 'vendor'` with a null vendor_id is a row that no
 *    settlement query will ever find and no report will ever show.
 *
 *  - Commission plus payable equals the line. Any code path that computes one
 *    without the other, or rounds them apart, fails at write time rather than
 *    at the end of a payout period.
 *
 *  - Vendor and branch stock obey the same non-negative and
 *    reserved-within-on-hand rules the central catalogue already obeys.
 *    Overselling is unacceptable in one place exactly as much as the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        // §2 — vendor ownership recorded at the item level, and coherently.
        DB::statement(<<<'SQL'
            ALTER TABLE order_items ADD CONSTRAINT order_items_seller_matches_kind CHECK (
                (seller_kind = 'platform'  AND vendor_id IS NULL     AND franchise_id IS NULL)
             OR (seller_kind = 'vendor'    AND vendor_id IS NOT NULL AND franchise_id IS NULL)
             OR (seller_kind = 'franchise' AND vendor_id IS NULL     AND franchise_id IS NOT NULL)
            )
        SQL);

        // A platform sale takes no commission from anyone; a marketplace sale
        // without one is a mistake, not a favour.
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_platform_takes_no_commission CHECK (seller_kind <> 'platform' OR commission_amount = 0)");

        // Money on a line adds up, and quantities are real.
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_total_matches CHECK (line_total = unit_price * quantity)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_split_adds_up CHECK (commission_amount + seller_payable = line_total)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_refund_within_line CHECK (refunded_amount <= line_total)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_rate_within_range CHECK (commission_rate_bps <= 10000)');

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_settlement_fk FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE SET NULL');

        // §9 — the platform account is singular, tenant accounts are not.
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_account_shape CHECK ((account_type = 'platform' AND account_id IS NULL) OR (account_type <> 'platform' AND account_id IS NOT NULL))");
        // A zero-amount entry records nothing and hides in every sum.
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_nonzero CHECK (amount <> 0)');

        // Rates are basis points and 100% is the ceiling.
        DB::statement('ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_rate_within_range CHECK (rate_bps <= 10000)');
        DB::statement('ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_window_ordered CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)');
        // A rule must carry the target its scope names, and nothing wider.
        DB::statement(<<<'SQL'
            ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_scope_target CHECK (
                (scope = 'platform' AND category_id IS NULL AND product_id IS NULL AND vendor_id IS NULL)
             OR (scope = 'vendor'   AND vendor_id IS NOT NULL AND category_id IS NULL AND product_id IS NULL)
             OR (scope = 'category' AND category_id IS NOT NULL AND product_id IS NULL)
             OR (scope = 'product'  AND product_id IS NOT NULL AND category_id IS NULL)
            )
        SQL);

        DB::statement('ALTER TABLE vendors ADD CONSTRAINT vendors_rate_within_range CHECK (commission_rate_bps IS NULL OR commission_rate_bps <= 10000)');

        // §2 — vendor stock keeps the catalogue's invariants.
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_on_hand_not_negative CHECK (stock_on_hand >= 0)');
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_reserved_not_negative CHECK (stock_reserved >= 0)');
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_reserved_within_on_hand CHECK (stock_reserved <= stock_on_hand)');
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_compare_at_above_price CHECK (compare_at_price IS NULL OR compare_at_price >= price)');
        DB::statement('ALTER TABLE vendor_offers ADD CONSTRAINT vendor_offers_rate_within_range CHECK (commission_rate_bps IS NULL OR commission_rate_bps <= 10000)');

        // §7 — branch stock likewise.
        DB::statement('ALTER TABLE franchise_inventory ADD CONSTRAINT franchise_inventory_on_hand_not_negative CHECK (stock_on_hand >= 0)');
        DB::statement('ALTER TABLE franchise_inventory ADD CONSTRAINT franchise_inventory_reserved_not_negative CHECK (stock_reserved >= 0)');
        DB::statement('ALTER TABLE franchise_inventory ADD CONSTRAINT franchise_inventory_reserved_within_on_hand CHECK (stock_reserved <= stock_on_hand)');

        // §3 — a branch may only move a price as far as its own limits allow,
        // and 'none' means none.
        DB::statement('ALTER TABLE franchises ADD CONSTRAINT franchises_override_limits_within_range CHECK (max_discount_bps <= 10000 AND max_markup_bps <= 10000)');

        // §6 — a tenant-scoped role assignment must name its tenant, and a
        // platform assignment must not.
        DB::statement('ALTER TABLE role_assignments ADD CONSTRAINT role_assignments_scope_shape CHECK ((scope_type IS NULL AND scope_id IS NULL) OR (scope_type IS NOT NULL AND scope_id IS NOT NULL))');

        // §4 — a custom domain is a claim until DNS proves it, so an
        // unverified custom host must never be primary.
        DB::statement("ALTER TABLE store_domains ADD CONSTRAINT store_domains_custom_primary_verified CHECK (NOT (is_primary AND kind = 'custom' AND verified_at IS NULL))");

        // §9 — settlement periods are ordered.
        DB::statement('ALTER TABLE settlements ADD CONSTRAINT settlements_period_ordered CHECK (period_end > period_start)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_settlement_fk');

        $constraints = [
            'order_items' => [
                'order_items_seller_matches_kind',
                'order_items_platform_takes_no_commission',
                'order_items_line_total_matches',
                'order_items_split_adds_up',
                'order_items_quantity_positive',
                'order_items_refund_within_line',
                'order_items_rate_within_range',
            ],
            'ledger_entries' => ['ledger_entries_account_shape', 'ledger_entries_amount_nonzero'],
            'commission_rules' => [
                'commission_rules_rate_within_range',
                'commission_rules_window_ordered',
                'commission_rules_scope_target',
            ],
            'vendors' => ['vendors_rate_within_range'],
            'vendor_offers' => [
                'vendor_offers_on_hand_not_negative',
                'vendor_offers_reserved_not_negative',
                'vendor_offers_reserved_within_on_hand',
                'vendor_offers_compare_at_above_price',
                'vendor_offers_rate_within_range',
            ],
            'franchise_inventory' => [
                'franchise_inventory_on_hand_not_negative',
                'franchise_inventory_reserved_not_negative',
                'franchise_inventory_reserved_within_on_hand',
            ],
            'franchises' => ['franchises_override_limits_within_range'],
            'role_assignments' => ['role_assignments_scope_shape'],
            'store_domains' => ['store_domains_custom_primary_verified'],
            'settlements' => ['settlements_period_ordered'],
        ];

        foreach ($constraints as $table => $names) {
            foreach ($names as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }
    }
};
