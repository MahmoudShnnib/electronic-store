<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportDateRange;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit trail. There are intentionally no mutation endpoints.
 */
final class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:audit.view'];
    }

    public function index(Request $request): Response
    {
        ReportDateRange::validate($request);

        $logs = Activity::query()
            ->with('causer')
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('description ILIKE ?', ['%'.$request->query('q').'%']))
            ->when($request->filled('event'), fn ($q) => $q->where('event', (string) $request->query('event')))
            ->when($request->filled('causer_id'), fn ($q) => $q->where('causer_id', (int) $request->query('causer_id')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', (string) $request->query('subject_type')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'subject_type' => $a->subject_type !== null ? class_basename($a->subject_type) : null,
                'subject_id' => $a->subject_id,
                'causer' => $a->causer?->name ?? __('admin.system'),
                'properties' => $a->properties,
                // Spatie records model updates as old/attributes pairs; shape
                // them into per-field before → after rows the page can render
                // as a readable diff instead of raw JSON.
                'changes' => self::changes($a),
                'created_at' => $a->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/audit-logs/Index', [
            'logs' => $logs,
            'subjectTypes' => Activity::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->map(fn ($t) => ['value' => $t, 'label' => class_basename($t)]),
            'filters' => (object) $request->only(['q', 'event', 'causer_id', 'subject_type', 'from', 'to']),
        ]);
    }

    /**
     * Spatie stores model diffs in `attribute_changes`, not `properties` —
     * withChanges() and withProperties() write to different columns. Reading
     * only `properties` meant every "updated" row rendered as raw JSON with
     * nothing to say about what actually changed.
     *
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    private static function changes(Activity $a): array
    {
        $changes = $a->attribute_changes ?? collect();

        $new = $changes['attributes'] ?? $a->properties['attributes'] ?? null;
        $old = $changes['old'] ?? $a->properties['old'] ?? [];

        if (! is_array($new)) {
            return [];
        }

        return collect($new)
            ->map(fn ($value, string $field) => [
                'field' => $field,
                'old' => $old[$field] ?? null,
                'new' => $value,
            ])
            ->values()
            ->all();
    }
}
