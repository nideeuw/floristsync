<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS view_stock_waste');
            DB::statement(<<<'SQL'
                CREATE VIEW view_stock_waste AS
                SELECT
                    strftime('%Y-%m-01', s.date) AS month,
                    p.id AS product_id,
                    p.product_name,
                    p.category_id,
                    c.category_name,
                    SUM(ABS(s.quantity_change)) AS quantity_lost,
                    SUM(ABS(s.quantity_change) * p.price) AS value_lost
                FROM stocks s
                JOIN products p ON p.id = s.product_id
                JOIN categories c ON c.id = p.category_id
                WHERE s.quantity_change < 0
                  AND s.reason IN ('wilted', 'damaged')
                  AND s.change_type NOT IN ('cancel', 'in')
                GROUP BY strftime('%Y-%m-01', s.date), p.id, p.product_name, p.category_id, c.category_name
            SQL);

            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW view_stock_waste AS
            SELECT
                DATE_TRUNC('month', s.date)::date AS month,
                p.id AS product_id,
                p.product_name,
                p.category_id,
                c.category_name,
                SUM(ABS(s.quantity_change))::int AS quantity_lost,
                SUM(ABS(s.quantity_change) * p.price)::bigint AS value_lost
            FROM stocks s
            JOIN products p ON p.id = s.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE s.quantity_change < 0
              AND s.reason IN ('wilted', 'damaged')
              AND s.change_type NOT IN ('cancel', 'in')
            GROUP BY DATE_TRUNC('month', s.date)::date, p.id, p.product_name, p.category_id, c.category_name
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS view_stock_waste');
    }
};

