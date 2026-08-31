<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets an enquiry be a question, not only an offer to do business.
 *
 * «یه قسمتی به عنوان پشتیبانی ۲۴ ساعته تو سایت داریم ولی هیچ جای دیگه راجع به
 * پشتیبانی … کسی موردی داره و سوالی داره کجا باید این سوال رو مطرح بکنه یا
 * مشکلی داره کجا باید این مشکل رو عنوان بکنه نیست». The front page has
 * promised «پشتیبانی ۲۴ ساعته» since the template was dressed, and there was
 * nowhere on the site to say anything to anybody.
 *
 * `enquiries` is already the shop's one inbox — a name, a telephone number and
 * a sentence, waiting for somebody to ring back — and «سؤال» is that shape
 * exactly. So this is a third `kind` rather than a second table, and the
 * routes, the panel screen and the form come with it: they are all generated
 * from `Enquiry::kinds()`.
 *
 * **The column is a CHECK constraint, not a Postgres enum type.** Laravel's
 * `$table->enum()` compiles to `varchar` plus
 * `enquiries_kind_check`, so widening it is dropping that constraint and
 * writing it again — there is no `ALTER TYPE … ADD VALUE` to reach for, and a
 * `->change()` on the column would need doctrine/dbal and would rewrite the
 * column rather than the constraint.
 *
 * `down()` narrows it again, and deletes any support enquiry first: rows that
 * the restored constraint would refuse have to go before it can be added back,
 * or the rollback fails on data written since. That is a real loss of somebody's
 * question, so it is worth saying out loud rather than hiding in a cascade.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'enquiries_kind_check';

    public function up(): void
    {
        DB::statement('ALTER TABLE enquiries DROP CONSTRAINT '.self::CONSTRAINT);

        DB::statement(
            'ALTER TABLE enquiries ADD CONSTRAINT '.self::CONSTRAINT.
            " CHECK (kind::text = ANY (ARRAY['wholesale', 'franchise', 'support']::text[]))"
        );
    }

    public function down(): void
    {
        DB::table('enquiries')->where('kind', 'support')->delete();

        DB::statement('ALTER TABLE enquiries DROP CONSTRAINT '.self::CONSTRAINT);

        DB::statement(
            'ALTER TABLE enquiries ADD CONSTRAINT '.self::CONSTRAINT.
            " CHECK (kind::text = ANY (ARRAY['wholesale', 'franchise']::text[]))"
        );
    }
};
