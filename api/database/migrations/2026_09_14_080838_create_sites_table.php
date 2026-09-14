<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            /*
             * Printed onto voucher cards so a buyer knows which network the
             * code is for.
             */
            $table->string('ssid')->nullable();

            /*
             * Each router at this site is configured with
             * `radius-location-id=<nas_identifier>`, which RouterOS sends as
             * the NAS-Identifier attribute. FreeRADIUS resolves the tenant from
             * it and refuses a voucher issued by a different operator, so it
             * must be unique across the whole platform rather than per tenant.
             */
            $table->string('nas_identifier', 64)->unique();

            $table->string('timezone', 64)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('address')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
