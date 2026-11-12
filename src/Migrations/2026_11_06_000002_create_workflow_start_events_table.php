<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المان‌های شروع (bpmn:StartEvent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->create(TableNames::startEvents(), function (Blueprint $table) {
            $table->id();
            $table->string('process_id', 64);
            $table->string('element_id', 64);
            $table->string('name')->nullable();
            $table->string('event_definition', 32)->nullable();
            $table->boolean('is_interrupting')->default(true);
            $table->text('documentation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['process_id', 'element_id']);
            $table->foreign('process_id')
                ->references('id')
                ->on(TableNames::processes())
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->dropIfExists(TableNames::startEvents());
    }
};