<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اضافه کردن condition_id به جریان‌های ترتیبی.
 *
 * هر sequence flow می‌تواند به یک شرط تعریف‌شده در جدول wf_conditions پکیج
 * behin-simple-workflow ارجاع داشته باشد؛ روتینگ هنگام رسیدن به گیت‌وی
 * شرطی، شرطِ جریان‌های خروجی را ارزیابی می‌کند و مسیر درست را طی می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->table(TableNames::sequenceFlows(), function (Blueprint $table) {
            $table->uuid('condition_id')->nullable()->after('is_default');

            // شرط‌ها در جدول wf_conditions پکیج behin-simple-workflow تعریف می‌شوند
            $table->foreign('condition_id')
                ->references('id')
                ->on('wf_conditions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->table(TableNames::sequenceFlows(), function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
            $table->dropColumn('condition_id');
        });
    }
};
