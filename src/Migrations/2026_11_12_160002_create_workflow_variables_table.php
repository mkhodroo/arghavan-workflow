<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول متغیرهای پرونده (معادل wf_variables پکیج behin-simple-workflow).
 *
 * اسکریپت‌ها و شرط‌های گیت‌وی از روی متغیرهای پرونده خوانده/نوشته می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->create(TableNames::variables(), function (Blueprint $table) {
            $table->id();
            $table->string('process_id', 64);
            $table->uuid('case_id', 64);
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('process_id')
                ->references('id')
                ->on(TableNames::processes())
                ->cascadeOnDelete();
            $table->foreign('case_id')
                ->references('id')
                ->on(TableNames::cases())
                ->cascadeOnDelete();

            $table->unique(['process_id', 'case_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->dropIfExists(TableNames::variables());
    }
};
