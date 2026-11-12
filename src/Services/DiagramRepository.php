<?php

namespace ArghavanWorkflow\Services;

use ArghavanWorkflow\Models\WorkflowProcess;
use ArghavanWorkflow\Support\BpmnXmlParser;
use ArghavanWorkflow\Support\TableNames;
use Illuminate\Support\Facades\DB;

/**
 * ذخیره‌سازی نمودارها در دیتابیس با Eloquent (بدون PDO و بدون فایل).
 *
 * هر نمودار یک «فرایند» در جدول فرایندها است و المان‌های آن (المان شروع،
 * پایان، تسک کاربری، اسکریپت تسک، گیت‌وی، استخر، لین و جریان ترتیبی) در
 * جدول‌های مخصوص خودشان ذخیره می‌شوند.
 *
 * جدول‌ها با migrationهای پکیج ساخته می‌شوند (php artisan migrate).
 */
class DiagramRepository
{
    /**
     * الگوی مجاز شناسه نمودار.
     */
    public const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_\-]{0,63}$/';

    public function __construct(private readonly DiagramSynchronizer $synchronizer)
    {
    }

    /**
     * فهرست فرایندها (بدون XML) مرتب‌شده بر اساس آخرین ویرایش.
     *
     * @return array<int, array{id: string, name: string, updated_at: string}>
     */
    public function all(): array
    {
        $items = [];

        $processes = WorkflowProcess::query()
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'updated_at']);

        foreach ($processes as $process) {
            $items[] = [
                'id' => (string) $process->id,
                'name' => (string) $process->name,
                'updated_at' => $this->formatDate($process->updated_at),
            ];
        }

        return $items;
    }

    /**
     * خواندن یک فرایند؛ در صورت نبود یا نامعتبر بودن شناسه null برمی‌گردد.
     *
     * @return array{id: string, name: string, xml: string, created_at: string, updated_at: string}|null
     */
    public function find(string $id): ?array
    {
        if (! self::isValidId($id)) {
            return null;
        }

        $process = WorkflowProcess::query()->find($id);

        return $process === null ? null : $this->toDocument($process);
    }

    /**
     * ذخیره (ایجاد یا به‌روزرسانی) فرایند، همگام‌سازی المان‌ها و
     * برگرداندن سند کامل آن.
     *
     * @return array{id: string, name: string, xml: string, created_at: string, updated_at: string}
     */
    public function save(?string $id, string $name, string $xml): array
    {
        $id = self::isValidId($id ?? '') ? (string) $id : self::generateId();
        $parsed = BpmnXmlParser::parse($xml);
        $name = self::normalizeName($name);

        /** @var WorkflowProcess $process */
        $process = DB::connection(TableNames::connection())->transaction(function () use ($id, $name, $xml, $parsed) {
            /** @var WorkflowProcess $process */
            $process = WorkflowProcess::query()->find($id) ?? new WorkflowProcess();

            $process->id = $id;
            $process->name = $name;
            $process->xml = $xml;

            if (! $process->process_key && isset($parsed['processes'][0]['element_id'])) {
                $process->process_key = (string) $parsed['processes'][0]['element_id'];
            }

            $process->save();

            $this->synchronizer->sync($process, $parsed);

            return $process;
        });

        return $this->toDocument($process->refresh());
    }

    /**
     * حذف فرایند و همه‌ی المان‌های آن؛ false اگر رکورد موجود نباشد.
     */
    public function delete(string $id): bool
    {
        if (! self::isValidId($id)) {
            return false;
        }

        $process = WorkflowProcess::query()->find($id);

        if ($process === null) {
            return false;
        }

        DB::connection(TableNames::connection())->transaction(function () use ($process): void {
            $this->synchronizer->purge($process);
            $process->delete();
        });

        return true;
    }

    public static function isValidId(?string $id): bool
    {
        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1;
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * @return array{id: string, name: string, xml: string, created_at: string, updated_at: string}
     */
    private function toDocument(WorkflowProcess $process): array
    {
        return [
            'id' => (string) $process->id,
            'name' => (string) $process->name,
            'xml' => (string) $process->xml,
            'created_at' => $this->formatDate($process->created_at),
            'updated_at' => $this->formatDate($process->updated_at),
        ];
    }

    private function formatDate(?\DateTimeInterface $date): string
    {
        return $date === null ? '' : $date->format('Y-m-d H:i:s');
    }

    private static function normalizeName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return 'بدون عنوان';
        }

        return mb_strlen($name) > 255 ? mb_substr($name, 0, 255) : $name;
    }
}