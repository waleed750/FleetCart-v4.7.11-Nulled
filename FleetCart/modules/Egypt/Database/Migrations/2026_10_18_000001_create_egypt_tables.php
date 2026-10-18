<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egypt_governorates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 8)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('shipping_cost', 18, 4)->default(0);
            $table->decimal('free_shipping_over', 18, 4)->nullable();
            $table->unsignedTinyInteger('eta_days_min')->default(2);
            $table->unsignedTinyInteger('eta_days_max')->default(4);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('egypt_feature_toggles', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('egypt_feature_toggle_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('feature_key', 64)->index();
            $table->boolean('old_value')->nullable();
            $table->boolean('new_value');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('egypt_feature_toggle_audits');
        Schema::dropIfExists('egypt_feature_toggles');
        Schema::dropIfExists('egypt_governorates');
    }
};
