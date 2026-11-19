<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS view_stock');
            DB::statement(<<<'SQL'
                CREATE VIEW view_stock AS
                SELECT
                    p.id AS product_id,
                    CAST(COALESCE(SUM(s.quantity_change), 0) AS INTEGER) AS stock
                FROM products p
                LEFT JOIN stocks s ON s.product_id = p.id
                GROUP BY p.id
            SQL);
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW view_stock AS
            SELECT
                p.id AS product_id,
                COALESCE(SUM(s.quantity_change), 0)::int AS stock
            FROM products p
            LEFT JOIN stocks s ON s.product_id = p.id
            GROUP BY p.id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS view_stock');
    }
};