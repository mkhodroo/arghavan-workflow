<?php

namespace ArghavanWorkflow\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * ذخیره‌سازی نمودارها در دیتابیس (بدون تولید هیچ فایلی).
 *
 * با PDO کار می‌کند تا هم در لاراول و هم در تست‌های مستقل (SQLite) قابل
 * استفاده باشد. جدول در صورت نبود به‌صورت خودکار ساخته می‌شود؛ بنابراین
 * migration و انتشار فایل لازم نیست.
 */
class DiagramRepository
{
    /**
     * الگوی مجاز شناسه نمودار.
     */
    public const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_\-]{0,63}$/';

    /**
     * الگوی مجاز نام جدول (ضد تزریق SQL).
     */
    private const TABLE_PATTERN = '/^[A-Za-z0-9_]+$/';

    /**
     * پرچم ساخت جدول؛ تا در هر درخواست فقط یک بار اجرا شود.
     */
    private bool $ensured = false;

    /**
     * @param  PDO  $pdo  اتصال دیتابیس (در لاراول از DB::connection)
     * @param  string  $table  نام جدول (با پیشوند اتصال)
     * @param  string  $legacyPath  پوشه فایل‌های JSON قدیمی برای ورود به دیتابیس
     * @param  bool  $legacyImport  ورود خودکار داده‌های قدیمی
     * @param  bool  $autoCreate  ساخت خودکار جدول
     *
     * @throws RuntimeException وقتی نام جدول نامعتبر باشد
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'workflow_diagrams',
        private readonly string $legacyPath = '',
        private readonly bool $legacyImport = true,
        private readonly bool $autoCreate = true,
    ) {
        if (preg_match(self::TABLE_PATTERN, $this->table) !== 1) {
            throw new RuntimeException('نام جدول نمودار نامعتبر است: '.$this->table);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * فهرست نمودارها (بدون XML) مرتب‌شده بر اساس آخرین ویرایش.
     *
     * @return array<int, array{id: string, name: string, updated_at: string}>
     */
    public function all(): array
    {
        $this->ensure();

        $stmt = $this->pdo->query(
            'SELECT `id`, `name`, `updated_at` FROM `'.$this->table.'` ORDER BY `updated_at` DESC'
        );

        $items = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        return $items;
    }

    /**
     * خواندن یک نمودار؛ در صورت نبود یا نامعتبر بودن شناسه null برمی‌گردد.
     *
     * @return array{id: string, name: string, xml: string, created_at: string, updated_at: string}|null
     */
    public function find(string $id): ?array
    {
        if (! self::isValidId($id)) {
            return null;
        }

        $this->ensure();

        $stmt = $this->pdo->prepare('SELECT * FROM `'.$this->table.'` WHERE `id` = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->normalizeDocument($row);
    }

    /**
     * ذخیره (ایجاد یا به‌روزرسانی) نمودار و برگرداندن سند کامل آن.
     *
     * @return array{id: string, name: string, xml: string, created_at: string, updated_at: string}
     */
    public function save(?string $id, string $name, string $xml): array
    {
        $this->ensure();

        $id = self::isValidId($id ?? '') ? $id : self::generateId();
        $existing = $this->find($id);

        $document = [
            'id' => $id,
            'name' => $this->normalizeName($name),
            'xml' => $xml,
            'created_at' => $existing['created_at'] ?? self::now(),
            'updated_at' => self::now(),
        ];

        if ($existing === null) {
            try {
                $this->insertDocument($document);
            } catch (PDOException) {
                // ثبت هم‌زمان همان شناسه؛ در این صورت به‌روزرسانی می‌شود.
                $this->updateDocument($document);
            }
        } else {
            $this->updateDocument($document);
        }

        return $document;
    }

    /**
     * حذف نمودار؛ false اگر شناسه نامعتبر یا رکورد موجود نباشد.
     */
    public function delete(string $id): bool
    {
        if (! self::isValidId($id)) {
            return false;
        }

        $this->ensure();

        $stmt = $this->pdo->prepare('DELETE FROM `'.$this->table.'` WHERE `id` = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public static function isValidId(?string $id): bool
    {
        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1;
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * ساخت خودکار جدول (در صورت نبود) و ورود داده‌های قدیمی؛ فقط یک بار.
     */
    private function ensure(): void
    {
        if ($this->ensured) {
            return;
        }

        // ابتدا پرچم؛ چون find() داخل import دوباره فراخوانی می‌شود.
        $this->ensured = true;

        if ($this->autoCreate) {
            // LONGTEXT فقط در MySQL لازم است (XML تا ۲ مگابایت).
            $xmlType = $this->driver() === 'mysql' ? 'LONGTEXT' : 'TEXT';

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS `'.$this->table.'` ('
                .'`id` VARCHAR(64) NOT NULL, '
                .'`name` VARCHAR(255) NOT NULL, '
                .'`xml` '.$xmlType.' NOT NULL, '
                .'`created_at` VARCHAR(19) NOT NULL, '
                .'`updated_at` VARCHAR(19) NOT NULL, '
                .'PRIMARY KEY (`id`)'
                .')'
            );
        }

        $this->importLegacyDocuments();
    }

    /**
     * ورود فایل‌های JSON قدیمی (ذخیره‌سازی نسخه‌های قبلی) به دیتابیس؛
     * پس از ورود موفق، فایل حذف می‌شود تا هیچ فایلی باقی نماند.
     */
    private function importLegacyDocuments(): void
    {
        if (! $this->legacyImport || $this->legacyPath === '' || ! is_dir($this->legacyPath)) {
            return;
        }

        $files = glob(rtrim($this->legacyPath, '/\\').DIRECTORY_SEPARATOR.'*.json');

        foreach (($files === false ? [] : $files) as $file) {
            $contents = @file_get_contents($file);
            $document = $contents === false ? null : $this->decodeLegacy($contents);

            if ($document === null) {
                continue;
            }

            if ($this->find($document['id']) === null) {
                try {
                    $this->insertDocument($document);
                } catch (PDOException) {
                    // ورود انجام نشد؛ فایل برای تلاش بعدی باقی می‌ماند.
                    continue;
                }
            }

            if (is_file($file)) {
                @unlink($file);
            }
        }

        // حذف پوشه در صورت خالی شدن (در غیر این صورت نادیده گرفته می‌شود).
        @rmdir(rtrim($this->legacyPath, '/\\'));
    }

    /**
     * @param  array{id: string, name: string, xml: string, created_at: string, updated_at: string}  $document
     */
    private function insertDocument(array $document): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `'.$this->table.'` (`id`, `name`, `xml`, `created_at`, `updated_at`) '
            .'VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $document['id'],
            $document['name'],
            $document['xml'],
            $document['created_at'],
            $document['updated_at'],
        ]);
    }

    /**
     * @param  array{id: string, name: string, xml: string, created_at: string, updated_at: string}  $document
     */
    private function updateDocument(array $document): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `'.$this->table.'` SET `name` = ?, `xml` = ?, `updated_at` = ? WHERE `id` = ?'
        );

        $stmt->execute([
            $document['name'],
            $document['xml'],
            $document['updated_at'],
            $document['id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: string, name: string, xml: string, created_at: string, updated_at: string}
     */
    private function normalizeDocument(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'xml' => (string) $row['xml'],
            'created_at' => (string) ($row['created_at'] ?? self::now()),
            'updated_at' => (string) ($row['updated_at'] ?? self::now()),
        ];
    }

    /**
     * سند JSON قدیمی؛ در صورت نامعتبر بودن null.
     *
     * @return array{id: string, name: string, xml: string, created_at: string, updated_at: string}|null
     */
    private function decodeLegacy(string $contents): ?array
    {
        $document = json_decode($contents, true);

        if (
            ! is_array($document)
            || ! isset($document['id'], $document['xml'])
            || ! self::isValidId((string) $document['id'])
        ) {
            return null;
        }

        return [
            'id' => (string) $document['id'],
            'name' => $this->normalizeName((string) ($document['name'] ?? '')),
            'xml' => (string) $document['xml'],
            'created_at' => (string) ($document['created_at'] ?? self::now()),
            'updated_at' => (string) ($document['updated_at'] ?? self::now()),
        ];
    }

    private function normalizeName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return 'بدون عنوان';
        }

        return mb_strlen($name) > 255 ? mb_substr($name, 0, 255) : $name;
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}