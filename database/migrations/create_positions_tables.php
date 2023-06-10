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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('model_has_positions', function (Blueprint $table) {
            $table->unsignedBigInteger('position_id');
            $table->morphs('model');

            $table->foreign('position_id')
                ->references('id') // position_id
                ->on('positions')
                ->onDelete('cascade');
        });

        Schema::create('position_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('position_id');

            $table->foreign('position_id')
                ->references('id') // position_id
                ->on('positions')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id') // role id
                ->on('roles')
                ->onDelete('cascade');

            $table->primary(['position_id', 'role_id'], 'position_has_roles_role_id_position_id_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_has_roles');
        Schema::dropIfExists('model_has_positions');
        Schema::dropIfExists('positions');
    }
};
