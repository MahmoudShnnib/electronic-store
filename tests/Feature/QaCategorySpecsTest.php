<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\SpecificationAttribute;
use App\Models\SpecificationGroup;

it('links an attribute to multiple categories and back', function (): void {
    $admin = staff('super_admin');
    $group = SpecificationGroup::create(['name' => 'Performance']);
    $pc = Category::factory()->create(['name' => 'PC']);
    $mobile = Category::factory()->create(['name' => 'Mobile']);

    // Create RAM scoped to PC + Mobile — Omar's exact example.
    $this->actingAs($admin)->post(route('admin.specifications.store'), [
        'specification_group_id' => $group->id,
        'name' => 'RAM',
        'type' => 'number',
        'categories' => [$pc->id, $mobile->id],
    ])->assertSessionHasNoErrors();

    $ram = SpecificationAttribute::query()->where('name', 'RAM')->firstOrFail();
    expect($ram->categories()->pluck('categories.id')->all())->toEqualCanonicalizing([$pc->id, $mobile->id]);

    // Narrow it to Mobile only.
    $this->actingAs($admin)->patch(route('admin.specifications.update', $ram), [
        'name' => 'RAM',
        'type' => 'number',
        'categories' => [$mobile->id],
    ])->assertSessionHasNoErrors();

    expect($ram->categories()->pluck('categories.id')->all())->toBe([$mobile->id]);
});

it('exposes each attribute\'s category scope to the product form', function (): void {
    $admin = staff('super_admin');
    $group = SpecificationGroup::create(['name' => 'Display']);
    $mobile = Category::factory()->create(['name' => 'Mobile']);

    $scoped = SpecificationAttribute::create([
        'specification_group_id' => $group->id, 'name' => 'Screen size', 'type' => 'number',
    ]);
    $scoped->categories()->sync([$mobile->id]);

    SpecificationAttribute::create([
        'specification_group_id' => $group->id, 'name' => 'Model', 'type' => 'text',
    ]);

    $product = Product::factory()->create();

    $this->actingAs($admin)->get(route('admin.products.edit', $product))
        ->assertInertia(fn ($page) => $page
            ->component('admin/products/Form')
            ->where('specificationGroups.0.attributes', function ($attributes) use ($mobile) {
                $byName = collect($attributes)->keyBy('name');

                return $byName['Screen size']['category_ids'] === [$mobile->id]
                    && $byName['Model']['category_ids'] === [];
            }));
});
