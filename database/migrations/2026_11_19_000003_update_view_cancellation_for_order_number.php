<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->createView('t.order_number,');
    }

    public function down(): void
    {
        $this->createView('');
    }

    private function createView(string $stored): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS view_cancellation');
            DB::statement(<<<SQL
                CREATE VIEW view_cancellation AS
                SELECT
                    t.id,
                    COALESCE(
                        {$stored}
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

        DB::statement(<<<SQL
            CREATE OR REPLACE VIEW view_cancellation AS
            SELECT
                t.id,
                COALESCE(
                    {$stored}
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
};
