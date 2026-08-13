<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Specifications;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SpecificationGroup;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the specifications page: every group with its attributes.
 * Group and attribute writes live in their own controllers in this namespace.
 */
final class SpecificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:specifications.manage'];
    }

    public function __invoke(): Response
    {
        return Inertia::render('admin/specifications/Index', [
            'groups' => SpecificationGroup::query()
                ->with(['attributes' => fn ($q) => $q->withCount('values')->with('categories:id')])
                ->orderBy('position')
                ->get()
                ->map(fn (SpecificationGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'name_ar' => $g->name_ar,
                    'position' => $g->position,
                    'attributes' => $g->attributes->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'name_ar' => $a->name_ar,
                        'unit' => $a->unit,
                        'type' => $a->type,
                        'is_filterable' => $a->is_filterable,
                        'position' => $a->position,
                        'values_count' => $a->values_count,
                        // Empty = shows for every category.
                        'category_ids' => $a->categories->pluck('id'),
                    ]),
                ]),
            'categories' => Category::query()
                ->orderBy('position')
                ->get(['id', 'name', 'name_ar', 'parent_id']),
        ]);
    }
}
