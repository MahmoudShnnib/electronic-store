<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which categories a specification attribute applies to (RAM → PC and
     * Mobile, Battery → Mobile only, …). An attribute with no rows here is
     * universal and shows for every category.
     */
    public function up(): void
    {
        Schema::create('category_specification_attribute', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specification_attribute_id')->constrained()->cascadeOnDelete();

            $table->unique(['category_id', 'specification_attribute_id'], 'category_spec_attribute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_specification_attribute');
    }
};
