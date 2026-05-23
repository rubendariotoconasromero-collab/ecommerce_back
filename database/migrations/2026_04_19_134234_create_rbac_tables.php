<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Redundante: Las tablas de RBAC (roles, permissions, role_permission)
        // se fusionaron en la migración inicial 0001_01_01_000000_create_users_table.php
        // para garantizar el orden de ejecución correcto con claves UUID.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hace nada
    }
};
