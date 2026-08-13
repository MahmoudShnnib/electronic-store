<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Specifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Specifications\SpecificationGroupRequest;
use App\Models\SpecificationGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** CRUD for specification groups (the headings attributes are organized under). */
final class SpecificationGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:specifications.manage'];
    }

    public function store(SpecificationGroupRequest $request): RedirectResponse
    {
        SpecificationGroup::create($request->validated());

        return back()->with('success', 'Specification group created.');
    }

    public function update(SpecificationGroupRequest $request, SpecificationGroup $group): RedirectResponse
    {
        $group->update($request->validated());

        return back()->with('success', 'Specification group updated.');
    }

    public function destroy(SpecificationGroup $group): RedirectResponse
    {
        $group->delete();

        return back()->with('success', 'Specification group deleted.');
    }
}
