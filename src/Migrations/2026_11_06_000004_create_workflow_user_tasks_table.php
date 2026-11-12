<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تسک‌های کاربری (bpmn:UserTask) و تنظیمات گردش کار/ظاهر آن‌ها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->create(TableNames::userTasks(), function (Blueprint $table) {
            $table->id();
            $table->string('process_id', 64);
            $table->string('element_id', 64);
            $table->string('name')->nullable();

            // تنظیمات گردش کار
            $table->string('form_id', 64)->nullable();
            $table->string('actor', 64)->nullable();
            $table->string('role_id', 64)->nullable();
            $table->string('assignment_type', 32)->nullable();
            $table->string('case_name')->nullable();
            $table->string('duration', 64)->nullable();
            $table->unsignedInteger('order')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->boolean('show_cancel_button')->default(true);

            // ظاهر
            $table->string('color', 32)->nullable();
            $table->string('background', 32)->nullable();

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
        Schema::connection(TableNames::connection())->dropIfExists(TableNames::userTasks());
    }
};