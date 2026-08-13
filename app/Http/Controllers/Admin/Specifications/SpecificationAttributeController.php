<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Specifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Specifications\SpecificationAttributeRequest;
use App\Models\SpecificationAttribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** CRUD for the individual specification attributes inside a group. */
final class SpecificationAttributeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:specifications.manage'];
    }

    public function store(SpecificationAttributeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $attribute = SpecificationAttribute::create(collect($validated)->except('categories')->all());
        $attribute->categories()->sync($validated['categories'] ?? []);

        return back()->with('success', __('Attribute created.'));
    }

    public function update(SpecificationAttributeRequest $request, SpecificationAttribute $attribute): RedirectResponse
    {
        $validated = $request->validated();

        $attribute->update(collect($validated)->except('categories')->all());
        $attribute->categories()->sync($validated['categories'] ?? []);

        return back()->with('success', __('Attribute updated.'));
    }

    public function destroy(SpecificationAttribute $attribute): RedirectResponse
    {
        $attribute->delete();

        return back()->with('success', __('Attribute deleted.'));
    }
}
