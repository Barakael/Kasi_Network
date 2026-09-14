<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /*
             * Null for platform administrators, who operate above any single
             * operator. Every other role is confined to one tenant.
             */
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('role', 20)->default('staff')->after('email');
            $table->string('phone', 20)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->timestamp('last_login_at')->nullable();

            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['role', 'phone', 'is_active', 'last_login_at']);
        });
    }
};
