<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function categoryPayload(array $overrides = []): array
{
    return [
        'name' => 'Monitors',
        'position' => 0,
        'is_active' => true,
        ...$overrides,
    ];
}

it('stores an uploaded category image on the public disk', function (): void {
    Storage::fake('public');

    $this->actingAs(staff('admin'))
        ->post(route('admin.categories.store'), categoryPayload([
            'image' => UploadedFile::fake()->image('monitors.jpg', 600, 600),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $category = Category::query()->sole();

    expect($category->image_path)->not->toBeNull()
        ->and($category->image_path)->toStartWith('categories/');

    Storage::disk('public')->assertExists($category->image_path);
});

it('deletes the previous file when the image is replaced', function (): void {
    Storage::fake('public');

    $admin = staff('admin');

    $this->actingAs($admin)->post(route('admin.categories.store'), categoryPayload([
        'image' => UploadedFile::fake()->image('first.jpg'),
    ]));

    $category = Category::query()->sole();
    $original = $category->image_path;

    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $category), categoryPayload([
            'image' => UploadedFile::fake()->image('second.jpg'),
        ]))
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->image_path)->not->toBe($original);

    Storage::disk('public')->assertMissing($original);
    Storage::disk('public')->assertExists($category->image_path);
});

it('clears the image and removes the file when remove_image is ticked', function (): void {
    Storage::fake('public');

    $admin = staff('admin');

    $this->actingAs($admin)->post(route('admin.categories.store'), categoryPayload([
        'image' => UploadedFile::fake()->image('gone.jpg'),
    ]));

    $category = Category::query()->sole();
    $original = $category->image_path;

    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $category), categoryPayload([
            'remove_image' => true,
        ]))
        ->assertSessionHasNoErrors();

    expect($category->refresh()->image_path)->toBeNull();

    Storage::disk('public')->assertMissing($original);
});

it('keeps the existing image when the form is saved without touching it', function (): void {
    Storage::fake('public');

    $admin = staff('admin');

    $this->actingAs($admin)->post(route('admin.categories.store'), categoryPayload([
        'image' => UploadedFile::fake()->image('keep.jpg'),
    ]));

    $category = Category::query()->sole();
    $original = $category->image_path;

    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $category), categoryPayload(['name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    expect($category->refresh()->image_path)->toBe($original);

    Storage::disk('public')->assertExists($original);
});

it('rejects a non-image upload', function (): void {
    Storage::fake('public');

    $this->actingAs(staff('admin'))
        ->post(route('admin.categories.store'), categoryPayload([
            'image' => UploadedFile::fake()->create('payload.php', 16, 'application/x-php'),
        ]))
        ->assertSessionHasErrors('image');

    expect(Category::query()->count())->toBe(0);
});
