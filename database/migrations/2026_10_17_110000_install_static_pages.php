<?php

declare(strict_types=1);

use App\Support\Content\DefaultPages;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the static pages (about, contact, policies) to installs that already
 * ran the pages migration. Pages that exist are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DefaultPages::install();
    }

    public function down(): void
    {
        // Content, not schema: the pages migration's down() drops the tables.
    }
};
