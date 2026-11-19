<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Day a transaction counts on in the report views: online orders use the
     * latest payment's payment_date (falling back to created_on), POS uses
     * created_on. Must stay in sync with ReportService::reportDaySql().
     *
     * Timezone: created_on and payment_date are `timestamp without time zone`
     * columns that Laravel writes in the app timezone (Asia/Jakarta), so the
     * stored literal is already Jakarta wall time and DATE() groups on the
     * Jakarta day. The database session timezone is irrelevant for these
     * columns, so no AT TIME ZONE conversion is needed.
     */
    private function reportDaySql(string $alias): string
    {
        return "CASE WHEN {$alias}.transaction_type = 'online'
            THEN COALESCE((
                SELECT pm.payment_date FROM payments pm
                WHERE pm.transaction_id = {$alias}.id
                ORDER BY pm.id DESC LIMIT 1
            ), {$alias}.created_on)
            ELSE {$alias}.created_on END";
    }

    public function up(): void
    {
        $day = $this->reportDaySql('t');

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS view_sales_daily');
            DB::statement(<<<SQL
                CREATE VIEW view_sales_daily AS
                SELECT
                    DATE({$day}) AS day,
                    t.transaction_type,
                    COUNT(*) AS orders,
                    COALESCE(SUM(t.total - COALESCE(t.shipping_cost, 0)), 0) AS revenue,
                    COALESCE(SUM(t.shipping_cost), 0) AS shipping
                FROM transactions t
                WHERE t.status NOT IN ('pending_payment', 'cancelled')
                GROUP BY DATE({$day}), t.transaction_type
            SQL);

            DB::statement('DROP VIEW IF EXISTS view_product_sales');
            DB::statement(<<<SQL
                CREATE VIEW view_product_sales AS
                SELECT
                    DATE({$day}) AS day,
                    t.transaction_type,
                    p.id AS product_id,
                    p.product_name,
                    p.category_id,
                    c.category_name,
                    SUM(d.qty) AS total_qty,
                    SUM(d.subtotal) AS total_revenue
                FROM transaction_details d
                JOIN transactions t ON t.id = d.transaction_id
                JOIN products p ON p.id = d.product_id
                JOIN categories c ON c.id = p.category_id
                WHERE t.status NOT IN ('pending_payment', 'cancelled')
                GROUP BY DATE({$day}), t.transaction_type, p.id, p.product_name, p.category_id, c.category_name
            SQL);

            return;
        }

        // Net revenue per day and channel: shipping is money passed on to the
        // courier, not store revenue. The shipping column is appended last so
        // CREATE OR REPLACE VIEW keeps the existing column order.
        DB::statement(<<<SQL
            CREATE OR REPLACE VIEW view_sales_daily AS
            SELECT
                DATE({$day}) AS day,
                t.transaction_type,
                COUNT(*)::int AS orders,
                COALESCE(SUM(t.total - COALESCE(t.shipping_cost, 0)), 0)::bigint AS revenue,
                COALESCE(SUM(t.shipping_cost), 0)::bigint AS shipping
            FROM transactions t
            WHERE t.status NOT IN ('pending_payment', 'cancelled')
            GROUP BY DATE({$day}), t.transaction_type
        SQL);

        // Units and revenue per product, day and channel, on the same report
        // day as view_sales_daily so both views always agree.
        DB::statement(<<<SQL
            CREATE OR REPLACE VIEW view_product_sales AS
            SELECT
                DATE({$day}) AS day,
                t.transaction_type,
                p.id AS product_id,
                p.product_name,
                p.category_id,
                c.category_name,
                SUM(d.qty)::int AS total_qty,
                SUM(d.subtotal)::bigint AS total_revenue
            FROM transaction_details d
            JOIN transactions t ON t.id = d.transaction_id
            JOIN products p ON p.id = d.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE t.status NOT IN ('pending_payment', 'cancelled')
            GROUP BY DATE({$day}), t.transaction_type, p.id, p.product_name, p.category_id, c.category_name
        SQL);
    }

    public function down(): void
    {
        $day = 't.created_on';

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS view_sales_daily');
            DB::statement(<<<SQL
                CREATE VIEW view_sales_daily AS
                SELECT
                    DATE({$day}) AS day,
                    t.transaction_type,
                    COUNT(*) AS orders,
                    COALESCE(SUM(t.total), 0) AS revenue
                FROM transactions t
                WHERE t.status NOT IN ('pending_payment', 'cancelled')
                GROUP BY DATE({$day}), t.transaction_type
            SQL);

            DB::statement('DROP VIEW IF EXISTS view_product_sales');
            DB::statement(<<<SQL
                CREATE VIEW view_product_sales AS
                SELECT
                    DATE({$day}) AS day,
                    t.transaction_type,
                    p.id AS product_id,
                    p.product_name,
                    p.category_id,
                    c.category_name,
                    SUM(d.qty) AS total_qty,
                    SUM(d.subtotal) AS total_revenue
                FROM transaction_details d
                JOIN transactions t ON t.id = d.transaction_id
                JOIN products p ON p.id = d.product_id
                JOIN categories c ON c.id = p.category_id
                WHERE t.status NOT IN ('pending_payment', 'cancelled')
                GROUP BY DATE({$day}), t.transaction_type, p.id, p.product_name, p.category_id, c.category_name
            SQL);

            return;
        }

        // CREATE OR REPLACE cannot drop the shipping column added in up(),
        // so the old definitions need a plain drop first.
        DB::statement('DROP VIEW IF EXISTS view_sales_daily');
        DB::statement(<<<SQL
            CREATE VIEW view_sales_daily AS
            SELECT
                DATE({$day}) AS day,
                t.transaction_type,
                COUNT(*)::int AS orders,
                COALESCE(SUM(t.total), 0)::bigint AS revenue
            FROM transactions t
            WHERE t.status NOT IN ('pending_payment', 'cancelled')
            GROUP BY DATE({$day}), t.transaction_type
        SQL);

        DB::statement('DROP VIEW IF EXISTS view_product_sales');
        DB::statement(<<<SQL
            CREATE VIEW view_product_sales AS
            SELECT
                DATE({$day}) AS day,
                t.transaction_type,
                p.id AS product_id,
                p.product_name,
                p.category_id,
                c.category_name,
                SUM(d.qty)::int AS total_qty,
                SUM(d.subtotal)::bigint AS total_revenue
            FROM transaction_details d
            JOIN transactions t ON t.id = d.transaction_id
            JOIN products p ON p.id = d.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE t.status NOT IN ('pending_payment', 'cancelled')
            GROUP BY DATE({$day}), t.transaction_type, p.id, p.product_name, p.category_id, c.category_name
        SQL);
    }
};
