<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\ReportDateRange;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
        [$from, $to] = ReportDateRange::localBounds($request);

        $logs = Activity::query()
            // Deleted subjects still deserve a name in the trail: every
            // soft-deleting model is loaded withTrashed so "deleted Product X"
            // keeps saying which product it was.
            ->with(['causer', 'subject' => function (MorphTo $morphTo) {
                $morphTo->constrain(collect([
                    Brand::class, Category::class, Coupon::class, Order::class,
                    Product::class, ProductVariant::class, PurchaseOrder::class,
                    Supplier::class, User::class, Warehouse::class,
                ])->mapWithKeys(fn (string $m) => [$m => fn ($q) => $q->withTrashed()])->all());
            }])
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('description ILIKE ?', ['%'.$request->query('q').'%']))
            ->when($request->filled('event'), fn ($q) => $q->where('event', (string) $request->query('event')))
            ->when($request->filled('causer_id'), fn ($q) => $q->where('causer_id', (int) $request->query('causer_id')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', (string) $request->query('subject_type')))
            // The shop's local days, like the reports — not UTC days.
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'subject_type' => $a->subject_type !== null ? class_basename($a->subject_type) : null,
                'subject_id' => $a->subject_id,
                'subject_label' => self::subjectLabel($a),
                'causer' => $a->causer?->name ?? __('admin.system'),
                // Spatie records model updates as old/attributes pairs; shape
                // them into per-field before → after rows the page can render
                // as a readable diff instead of raw JSON.
                'changes' => self::changes($a),
                'details' => self::details($a),
                'ip' => $a->properties['ip'] ?? null,
                'user_agent' => $a->properties['user_agent'] ?? null,
                'created_at' => Dates::dateTime($a->created_at),
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
     * A human handle for the row's subject — order number, product name,
     * coupon code — so a row reads "Order ORD-…" instead of "Order #14".
     * Null when the subject was hard-deleted (morphTo yields nothing); the
     * page falls back to the numeric id.
     */
    private static function subjectLabel(Activity $a): ?string
    {
        $subject = $a->subject;

        if ($subject === null) {
            return null;
        }

        foreach (['number', 'rma_number', 'po_number', 'name', 'company_name', 'code', 'email', 'key'] as $attr) {
            $value = $subject->getAttribute($attr);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Spatie stores model diffs in `attribute_changes`, not `properties` —
     * withChanges() and withProperties() write to different columns. "deleted"
     * rows carry only `old` (there is no after state), so those render as
     * before-only rows rather than being dropped.
     *
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    private static function changes(Activity $a): array
    {
        $changes = $a->attribute_changes ?? collect();

        $new = $changes['attributes'] ?? $a->properties['attributes'] ?? null;
        $old = $changes['old'] ?? $a->properties['old'] ?? [];

        if (! is_array($new)) {
            if (! is_array($old) || $old === []) {
                return [];
            }

            return collect($old)
                ->map(fn ($value, string $field) => [
                    'field' => $field,
                    'old' => self::display($field, $value, $a),
                    'new' => null,
                ])
                ->values()
                ->all();
        }

        return collect($new)
            ->map(fn ($value, string $field) => [
                'field' => $field,
                'old' => self::display($field, $old[$field] ?? null, $a),
                'new' => self::display($field, $value, $a),
            ])
            ->values()
            ->all();
    }

    /**
     * Custom-event payloads (order status from/to, refund amount, receive
     * quantities…) as labeled rows. Request context (ip/user_agent) is
     * surfaced separately, not buried in here.
     *
     * @return list<array{field: string, value: mixed}>
     */
    private static function details(Activity $a): array
    {
        return collect($a->properties)
            ->except(['ip', 'user_agent', 'old', 'attributes'])
            ->map(function ($value, string $field) use ($a) {
                if (is_array($value)) {
                    $value = collect($value)
                        ->map(fn ($v, $k) => is_int($k)
                            ? (string) self::display($field, $v, $a)
                            : $k.': '.self::display($field, $v, $a))
                        ->implode(', ');
                }

                return ['field' => $field, 'value' => self::display($field, $value, $a)];
            })
            ->values()
            ->all();
    }

    /**
     * Raw stored values → what an admin reads: cents become formatted money,
     * enum values become their localized labels, booleans become yes/no.
     * Unknown values pass through untouched.
     */
    private static function display(string $field, mixed $value, Activity $a): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return __($value ? 'admin.yes' : 'admin.no');
        }

        if (str_ends_with($field, '_cents') && is_int($value)) {
            return Money::format($value);
        }

        if (is_string($value)) {
            $enum = self::enumFor($field, $a);

            if ($enum !== null && trans()->has("enums.{$enum}.{$value}")) {
                return __("enums.{$enum}.{$value}");
            }
        }

        if (is_int($value) && ($related = self::relatedName($field, $value)) !== null) {
            return $related;
        }

        return $value;
    }

    /**
     * Foreign keys in a diff ("category_id: 23") mean nothing to an admin;
     * swap them for the related record's name. Memoized per request — a page
     * of 50 rows tends to repeat the same handful of ids.
     */
    private static function relatedName(string $field, int $id): ?string
    {
        static $cache = [];

        $model = match ($field) {
            'category_id', 'parent_id' => Category::class,
            'brand_id' => Brand::class,
            'supplier_id' => Supplier::class,
            'warehouse_id' => Warehouse::class,
            default => null,
        };

        if ($model === null) {
            return null;
        }

        if (! array_key_exists($model.$id, $cache)) {
            $record = $model::withTrashed()->find($id);
            $cache[$model.$id] = $record?->name ?? $record?->company_name;
        }

        return $cache[$model.$id];
    }

    /** Which enum dictionary a field's string values belong to, by subject. */
    private static function enumFor(string $field, Activity $a): ?string
    {
        return match ($field) {
            'type' => $a->subject_type === Coupon::class ? 'coupon_type' : null,
            'method' => 'refund_method',
            'conditions' => 'item_condition',
            'status', 'from', 'to' => match ($a->subject_type) {
                Order::class => 'order_status',
                Product::class, ProductVariant::class => 'product_status',
                PurchaseOrder::class => 'purchase_order_status',
                ProductReturn::class => 'return_status',
                default => null,
            },
            default => null,
        };
    }
}
