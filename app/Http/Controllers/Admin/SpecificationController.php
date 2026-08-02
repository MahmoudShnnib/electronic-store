<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Specifications\SpecificationAttributeRequest;
use App\Http\Requests\Admin\Specifications\SpecificationGroupRequest;
use App\Models\SpecificationAttribute;
use App\Models\SpecificationGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

final class SpecificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:specifications.manage'];
    }

    public function index(): Response
    {
        return Inertia::render('admin/specifications/Index', [
            'groups' => SpecificationGroup::query()
                ->with(['attributes' => fn ($q) => $q->withCount('values')])
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
                    ]),
                ]),
        ]);
    }

    public function storeGroup(SpecificationGroupRequest $request): RedirectResponse
    {
        SpecificationGroup::create($request->validated());

        return back()->with('success', 'Specification group created.');
    }

    public function updateGroup(SpecificationGroupRequest $request, SpecificationGroup $group): RedirectResponse
    {
        $group->update($request->validated());

        return back()->with('success', 'Specification group updated.');
    }

    public function destroyGroup(SpecificationGroup $group): RedirectResponse
    {
        $group->delete();

        return back()->with('success', 'Specification group deleted.');
    }

    public function store(SpecificationAttributeRequest $request): RedirectResponse
    {
        SpecificationAttribute::create($request->validated());

        return back()->with('success', 'Attribute created.');
    }

    public function update(SpecificationAttributeRequest $request, SpecificationAttribute $attribute): RedirectResponse
    {
        $attribute->update($request->validated());

        return back()->with('success', 'Attribute updated.');
    }

    public function destroy(SpecificationAttribute $attribute): RedirectResponse
    {
        $attribute->delete();

        return back()->with('success', 'Attribute deleted.');
    }
}
