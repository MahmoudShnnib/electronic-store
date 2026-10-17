<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\PageSectionRequest;
use App\Http\Requests\Admin\Content\ReorderSectionsRequest;
use App\Models\Page;
use App\Models\PageSection;
use App\Support\Content\ContentCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Adds, edits, hides, reorders and removes a page's sections. Saving a
 * section bumps the content cache through the model; the reorder writes
 * sort_order in one query per row, so it bumps the cache itself.
 */
final class PageSectionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:settings.manage'];
    }

    public function store(PageSectionRequest $request, Page $page): RedirectResponse
    {
        $page->sections()->create([
            'type' => $request->sectionType(),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => ((int) $page->sections()->max('sort_order')) + 1,
            'data' => $request->sectionData(),
        ]);

        return back()->with('success', __('Section added.'));
    }

    public function update(PageSectionRequest $request, Page $page, PageSection $section): RedirectResponse
    {
        $before = $section->imagePaths();

        $section->update([
            'data' => $request->sectionData(),
            ...($request->has('is_active') ? ['is_active' => $request->boolean('is_active')] : []),
        ]);

        // Artwork the section no longer uses goes, so replacing a banner
        // doesn't leave the old file on disk forever.
        self::deleteImages(array_diff($before, $section->imagePaths()));

        return back()->with('success', __('Section saved.'));
    }

    public function toggle(Page $page, PageSection $section): RedirectResponse
    {
        $section->update(['is_active' => ! $section->is_active]);

        return back()->with('success', $section->is_active ? __('Section shown.') : __('Section hidden.'));
    }

    public function reorder(ReorderSectionsRequest $request, Page $page): RedirectResponse
    {
        $ids = $request->ids();

        DB::transaction(function () use ($ids, $page): void {
            foreach ($ids as $position => $id) {
                PageSection::query()->where('page_id', $page->id)->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        ContentCache::bump();

        activity()->performedOn($page)->event('sections_reordered')->withProperties(['ids' => $ids])->log('Page sections reordered');

        return back()->with('success', __('Order saved.'));
    }

    public function destroy(Page $page, PageSection $section): RedirectResponse
    {
        $images = $section->imagePaths();

        $section->delete();

        self::deleteImages($images);

        return back()->with('success', __('Section removed.'));
    }

    /**
     * Removes uploaded artwork. Only files under the builder's own directory
     * are touched — a seeded or legacy path is never deleted from here.
     *
     * @param  iterable<string>  $paths
     */
    public static function deleteImages(iterable $paths): void
    {
        foreach ($paths as $path) {
            if (str_starts_with($path, SectionImageController::DIRECTORY.'/') && ! str_contains($path, '..')) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
