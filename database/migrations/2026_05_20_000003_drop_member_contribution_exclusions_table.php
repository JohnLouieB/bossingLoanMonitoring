<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('member_contribution_exclusions');
    }

    public function down(): void
    {
        // Intentionally left empty — this table is no longer used.
    }
};
