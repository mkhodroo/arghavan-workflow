<?php

namespace ArghavanWorkflow\Http\Controllers\Api;

use ArghavanWorkflow\Models\Runtime\WorkflowInbox;
use ArghavanWorkflow\Runtime\ProcessEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * وب سرویس کارتابل کاربر.
 *
 * نمایش لیست کارهای تخصیص‌یافته به کاربر جاری در جدول workflow_inbox
 * و ادامه‌ی روتینگ پس از انجام هر کار.
 */
class InboxController extends Controller
{
    /**
     * لیست کارهای باز کاربر جاری.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $rows = WorkflowInbox::with(['task', 'case', 'actorUser'])
            ->where('actor', $userId)
            ->whereIn('status', ['new', 'opened', 'inProgress', 'draft'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $rows->map(function ($row) {
            $task = $row->task;
            $case = $row->case;

            return [
                'id' => $row->id,
                'task_id' => $row->task_id,
                'task_name' => $task?->name ?? '-',
                'case_id' => $row->case_id,
                'case_name' => $row->case_name ?? ($case?->name ?? '-'),
                'case_number' => $case?->number ?? '-',
                'actor' => $row->actor,
                'actor_name' => optional($row->actorUser)->name ?? '-',
                'status' => $row->status,
                'status_label' => $this->statusLabel($row->status),
                'created_at' => $row->created_at?->format('Y/m/d H:i') ?? '-',
                'updated_at' => $row->updated_at?->format('Y/m/d H:i') ?? '-',
            ];
        });

        return response()->json([
            'ok' => true,
            'count' => $data->count(),
            'rows' => $data,
        ]);
    }

    /**
     * جزئیات یک رکورد کارتابل.
     */
    public function show(string $inboxId): JsonResponse
    {
        $row = WorkflowInbox::with(['task', 'case', 'actorUser'])->find($inboxId);

        if (! $row) {
            return response()->json([
                'ok' => false,
                'message' => 'رکورد کارتابل یافت نشد.',
            ], 404);
        }

        if ($row->actor != Auth::id()) {
            return response()->json([
                'ok' => false,
                'message' => 'شما دسترسی به این رکورد ندارید.',
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'row' => [
                'id' => $row->id,
                'task_id' => $row->task_id,
                'task_name' => optional($row->task)->name ?? '-',
                'case_id' => $row->case_id,
                'case_name' => $row->case_name ?? optional($row->case)->name ?? '-',
                'case_number' => optional($row->case)->number ?? '-',
                'status' => $row->status,
                'created_at' => $row->created_at?->format('Y/m/d H:i') ?? '-',
                'updated_at' => $row->updated_at?->format('Y/m/d H:i') ?? '-',
            ],
        ]);
    }

    /**
     * انجام کار جاری و رفتن به مرحله‌ی بعدی فرآیند.
     */
    public function next(Request $request, string $inboxId): JsonResponse
    {
        $inbox = WorkflowInbox::with(['task', 'case'])->find($inboxId);

        if (! $inbox) {
            return response()->json(['ok' => false, 'message' => 'رکورد کارتابل یافت نشد.'], 404);
        }

        if ($inbox->actor != Auth::id()) {
            return response()->json(['ok' => false, 'message' => 'شما دسترسی به این کارتابل ندارید.'], 403);
        }

        if ($inbox->status === 'done') {
            return response()->json(['ok' => false, 'message' => 'این کار قبلاً انجام شده است.'], 422);
        }

        $result = ProcessEngine::next($inbox, Auth::id());

        if ($result['ok'] === false) {
            return response()->json([
                'ok' => false,
                'message' => $result['error'] ?? 'ادامه‌ی فرآیند انجام نشد.',
            ], 400);
        }

        $case = $result['case'];

        return response()->json([
            'ok' => true,
            'case_id' => $case?->id,
            'status' => $case?->status,
            'stopped_at' => $result['stopped_at'] ?? null,
            'message' => ($case?->status === 'done')
                ? 'فرآیند با موفقیت تمام شد.'
                : 'کار انجام شد و فرآیند به مرحله بعد رفت.',
        ]);
    }

    /**
     * نام قبلی روت انجام کار؛ همان next است.
     */
    public function complete(Request $request, string $inboxId): JsonResponse
    {
        return $this->next($request, $inboxId);
    }

    /**
     * فقط علامت‌گذاری انجام‌شده، بدون ادامه‌ی روتینگ.
     */
    public function done(Request $request, string $inboxId): JsonResponse
    {
        $row = WorkflowInbox::find($inboxId);

        if (! $row) {
            return response()->json([
                'ok' => false,
                'message' => 'رکورد کارتابل یافت نشد.',
            ], 404);
        }

        if ($row->actor != Auth::id()) {
            return response()->json([
                'ok' => false,
                'message' => 'شما دسترسی به این کارتابل ندارید.',
            ], 403);
        }

        $row->update([
            'status' => 'done',
            'done_by' => Auth::id(),
            'done_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'کار با موفقیت انجام شد.',
        ]);
    }

    /**
     * نمایش صفحه کارتابل کاربر.
     */
    public function inboxPage()
    {
        $layout = (string) config('workflow2.layout', 'behin-layouts.app');
        $layout = $layout !== '' && view()->exists($layout) ? $layout : 'workflow::layout';

        return view('workflow::inbox', [
            'layout' => $layout,
        ]);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'new' => 'جدید',
            'opened' => 'باز شده',
            'inProgress' => 'در حال انجام',
            'draft' => 'پیش‌نویس',
            'done' => 'انجام شده',
            default => (string) $status,
        };
    }
}
