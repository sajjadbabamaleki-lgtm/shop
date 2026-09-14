<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many of this line the customer sent back.
 *
 * **The line itself does not change, and that is the whole design.** An order
 * item is a receipt — «Everything on this page comes off the order's own rows,
 * not the catalogue. A product renamed or repriced next month must not change
 * what this says happened today» — so a partial return is recorded *beside*
 * the quantity rather than by editing it. What was bought stays what was
 * bought; what came back is a second number.
 *
 * That also keeps the table's own arithmetic true: `line_total = unit_price *
 * quantity` is a CHECK constraint, and a return that edited `quantity` would
 * have to edit `line_total` and then the order's `subtotal` and `grand_total`
 * with it — four numbers moving so that an invoice already issued could be
 * restated. This moves one.
 *
 * It exists because اسنپ‌پی's `update` service needs the basket as it stands
 * now: a shopper who sends one shoe back has their instalments reduced, and
 * the shop has twenty-four hours to say so — «پذیرنده موظف است مراتب را ظرف
 * حداکثر ۲۴ ساعت پس از مرجوعی کالا به‌صورت سیستمی به اسنپ‌پی اعلام کند». Until
 * now this application had nowhere to write that down: `admin/OrderController`
 * said so in its own docblock, that there is «no refund and no return anywhere
 * in the schema».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
        });

        // Nothing can come back that was never bought. The same belt every
        // other quantity in this schema wears, in the database rather than in
        // PHP, because the one thing worse than a wrong count is a wrong count
        // that the shelf then agrees with.
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_returned_within_quantity CHECK (returned_quantity <= quantity)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_returned_within_quantity');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('returned_quantity');
        });
    }
};
