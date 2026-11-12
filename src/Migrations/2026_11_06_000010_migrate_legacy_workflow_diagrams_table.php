<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * انتقال داده‌های جدول قدیمی `workflow_diagrams` (نسخه‌ی PDO) به جدول
 * فرایندها و حذف جدول قدیمی.
 *
 * المان‌های هر نمودار در اولین ذخیره‌سازی از XML ساخته می‌شوند.
 */
return new class extends Migration
{
    private const LEGACY_TABLE = 'workflow_diagrams';

    public function up(): void
    {
        $connection = TableNames::connection();
        $schema = Schema::connection($connection);

        if (! $schema->hasTable(self::LEGACY_TABLE)) {
            return;
        }

        $processes = TableNames::processes();

        DB::connection($connection)
            ->table(self::LEGACY_TABLE)
            ->orderBy('id')
            ->chunk(100, function ($rows) use ($connection, $processes): void {
                foreach ($rows as $row) {
                    $exists = DB::connection($connection)
                        ->table($processes)
                        ->where('id', $row->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::connection($connection)->table($processes)->insert([
                        'id' => (string) $row->id,
                        'name' => (string) (($row->name ?? '') !== '' ? $row->name : 'بدون عنوان'),
                        'xml' => (string) $row->xml,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                }
            });

        $schema->drop(self::LEGACY_TABLE);
    }

    public function down(): void
    {
        // بازسازی جدول قدیمی لازم نیست؛ داده‌ها در جدول فرایندها باقی می‌مانند.
    }
};