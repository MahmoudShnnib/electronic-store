<?php

declare(strict_types=1);

use App\Support\Content\DefaultPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            // System pages (home, …) are addressed by key and cannot be deleted;
            // custom pages carry a slug instead.
            $table->string('key')->nullable()->unique();
            $table->string('slug')->nullable()->unique();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->json('seo')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('page_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
        });

        // Existing installs get a home page that reproduces today's layout.
        DefaultPages::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');
    }
};
