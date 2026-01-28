<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['member_id']);

            // Make member_id nullable
            $table->unsignedBigInteger('member_id')->nullable()->change();

            // Add non_member_name field
            $table->string('non_member_name')->nullable()->after('member_id');

            // Re-add the foreign key constraint (nullable)
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['member_id']);

            // Remove non_member_name
            $table->dropColumn('non_member_name');

            // Make member_id required again
            $table->unsignedBigInteger('member_id')->nullable(false)->change();

            // Re-add the foreign key constraint
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }
};
