<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS view_sales_daily');
            DB::statement(<<<'SQL'
                CREATE VIEW view_sales_daily AS
                SELECT
                    DATE(t.created_on) AS day,
                    t.transaction_type,
                    COUNT(*) AS orders,
                    COALESCE(SUM(t.total), 0) AS revenue
                FROM transactions t
                WHERE t.status NOT IN ('pending_payment', 'cancelled')
                GROUP BY DATE(t.created_on), t.transaction_type
            SQL);

            DB::statement('DROP VIEW IF EXISTS view_product_sales');
            DB::statement(<<<'SQL'
                CREATE VIEW view_product_sales AS
                SELECT
                    DATE(t.created_on) AS day,
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
                GROUP BY DATE(t.created_on), t.transaction_type, p.id, p.product_name, p.category_id, c.category_name
            SQL);

            DB::statement('DROP VIEW IF EXISTS view_stock_report');
            DB::statement(<<<'SQL'
                CREATE VIEW view_stock_report AS
                SELECT
                    p.id AS product_id,
                    p.product_name,
                    p.category_id,
                    c.category_name,
                    p.is_active,
                    COALESCE(s.stock, 0) AS stock
                FROM products p
                JOIN categories c ON c.id = p.category_id
                LEFT JOIN view_stock s ON s.product_id = p.id
            SQL);

            DB::statement('DROP VIEW IF EXISTS view_cancellation');
            DB::statement(<<<'SQL'
                CREATE VIEW view_cancellation AS
                SELECT
                    t.id,
                    COALESCE(
                        (SELECT pm.midtrans_order_id FROM payments pm WHERE pm.transaction_id = t.id ORDER BY pm.id DESC LIMIT 1),
                        'POS-' || printf('%06d', t.id)
                    ) AS order_number,
                    t.transaction_type,
                    t.cancel_reason,
                    u.name AS cancelled_by_name,
                    COALESCE(t.cancelled_at, t.created_on) AS cancelled_time,
                    t.total
                FROM transactions t
                LEFT JOIN users u ON u.id = t.cancelled_by
                WHERE t.status = 'cancelled'
            SQL);

            return;
        }

        // Successful sales per day and channel (POS or online).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW view_sales_daily AS
            SELECT
                DATE(t.created_on) AS day,
                t.transaction_type,
                COUNT(*)::int AS orders,
                COALESCE(SUM(t.total), 0)::bigint AS revenue
            FROM transactions t
            WHERE t.status NOT IN ('pending_payment', 'cancelled')
            GROUP BY DATE(t.created_on), t.transaction_type
        SQL);

        // Units and revenue per product, day and channel.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW view_product_sales AS
            SELECT
                DATE(t.created_on) AS day,
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
            GROUP BY DATE(t.created_on), t.transaction_type, p.id, p.product_name, p.category_id, c.category_name
        SQL);

        // Current stock per product with its category.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW view_stock_report AS
            SELECT
                p.id AS product_id,
                p.product_name,
                p.category_id,
                c.category_name,
                p.is_active,
                COALESCE(s.stock, 0) AS stock
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN view_stock s ON s.product_id = p.id
        SQL);

        // Cancelled transactions with a readable order number.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW view_cancellation AS
            SELECT
                t.id,
                COALESCE(
                    (SELECT pm.midtrans_order_id FROM payments pm WHERE pm.transaction_id = t.id ORDER BY pm.id DESC LIMIT 1),
                    CONCAT('POS-', LPAD(t.id::text, 6, '0'))
                ) AS order_number,
                t.transaction_type,
                t.cancel_reason,
                u.name AS cancelled_by_name,
                COALESCE(t.cancelled_at, t.created_on) AS cancelled_time,
                t.total
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cancelled_by
            WHERE t.status = 'cancelled'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS view_cancellation');
        DB::statement('DROP VIEW IF EXISTS view_stock_report');
        DB::statement('DROP VIEW IF EXISTS view_product_sales');
        DB::statement('DROP VIEW IF EXISTS view_sales_daily');
    }
};