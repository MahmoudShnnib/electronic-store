<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specification_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('specification_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('specification_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('unit')->nullable();
            $table->string('type')->default('text');
            $table->boolean('is_filterable')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['specification_group_id', 'name']);
        });

        Schema::create('category_specification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specification_attribute_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'specification_attribute_id'], 'cat_spec_template_unique');
        });

        Schema::create('product_specification_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specification_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('value_ar')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'specification_attribute_id'], 'product_spec_value_unique');
            $table->index(['specification_attribute_id', 'value'], 'spec_value_filter_index');
        });

        Schema::create('price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('price_type')->default('regular');
            $table->unsignedBigInteger('old_price_cents')->nullable();
            $table->unsignedBigInteger('new_price_cents');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('product_specification_values');
        Schema::dropIfExists('category_specification_templates');
        Schema::dropIfExists('specification_attributes');
        Schema::dropIfExists('specification_groups');
    }
};
