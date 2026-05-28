<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('logo_login_path')->nullable()->after('footer_text');
            $table->string('logo_sidebar_path')->nullable()->after('logo_login_path');
            $table->string('logo_sidebar_compact_path')->nullable()->after('logo_sidebar_path');
            $table->string('logo_landing_path')->nullable()->after('logo_sidebar_compact_path');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_login_path',
                'logo_sidebar_path',
                'logo_sidebar_compact_path',
                'logo_landing_path',
            ]);
        });
    }
};
