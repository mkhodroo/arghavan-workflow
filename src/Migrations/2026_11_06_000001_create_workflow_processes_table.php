<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول فرایندها؛ هر نمودار BPMN یک رکورد در این جدول دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->create(TableNames::processes(), function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name');
            $table->string('process_key', 191)->nullable();
            $table->text('description')->nullable();
            $table->longText('xml');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->dropIfExists(TableNames::processes());
    }
};