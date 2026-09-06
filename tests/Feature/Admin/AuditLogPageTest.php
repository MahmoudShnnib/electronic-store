<?php

use App\Models\Category;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

it('renders update, delete and custom-event rows as readable diffs', function (): void {
    $admin = staff('admin');

    $product = Product::factory()->create(['name' => 'Old Name', 'category_id' => Category::factory()->create()->id]);
    $product->update(['name' => 'New Name']);

    $deleted = Product::factory()->create(['name' => 'Doomed', 'category_id' => $product->category_id]);
    $deleted->delete();

    activity()
        ->performedOn($product)
        ->event('order_status_changed')
        ->withProperties(['from' => 'pending', 'to' => 'confirmed'])
        ->log('status change');

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-logs/Index')
            ->where('logs.data', function ($rows) {
                $rows = collect($rows);

                $update = $rows->firstWhere('event', 'updated');
                expect($update['changes'])->toContain(['field' => 'name', 'old' => 'Old Name', 'new' => 'New Name'])
                    ->and($update['subject_label'])->toBe('New Name');

                // A deleted row keeps its before-values and its subject name
                // (loaded withTrashed) instead of collapsing to raw ip JSON.
                $delete = $rows->firstWhere('event', 'deleted');
                expect(collect($delete['changes'])->firstWhere('field', 'name'))
                    ->toBe(['field' => 'name', 'old' => 'Doomed', 'new' => null])
                    ->and($delete['subject_label'])->toBe('Doomed');

                $custom = $rows->firstWhere('event', 'order_status_changed');
                expect(collect($custom['details'])->pluck('field')->all())->toBe(['from', 'to'])
                    ->and($custom['details'])->not->toContain(fn ($d) => $d['field'] === 'ip');

                return true;
            })
        );
});
