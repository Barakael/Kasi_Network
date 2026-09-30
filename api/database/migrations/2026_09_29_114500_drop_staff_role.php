<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'staff')->update(['role' => 'owner']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('owner')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('staff')->change();
        });
    }
};
