<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول پرونده‌ها (معادل wf_cases پکیج behin-simple-workflow).
 *
 * هر بار که یک فرایند استارت زده می‌شود یک رکورد در این جدول ساخته می‌شود و
 * روتینگ از المان شروعِ همان فرایند جلو می‌رود. شناسه‌ی فرایند به جدول
 * workflow_processes همین پکیج ارجاع دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->create(TableNames::cases(), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('process_id', 64);
            $table->bigInteger('number')->nullable();
            $table->string('name')->nullable();
            $table->foreignId('creator')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('new');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('process_id')
                ->references('id')
                ->on(TableNames::processes())
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->dropIfExists(TableNames::cases());
    }
};
