<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            CREATE VIEW admin_todos_view AS
            SELECT
                t.id,
                t.title,
                t.description,
                t.completed,
                t.created_at,
                t.updated_at,
                u.id AS user_id,
                u.name AS user_name,
                u.email AS user_email
            FROM todos AS t
            JOIN users AS u ON u.id = t.user_id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS admin_todos_view');
    }
};
