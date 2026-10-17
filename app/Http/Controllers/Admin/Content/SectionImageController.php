<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;

/**
 * Stores artwork for a hero slide, promo tile or banner and hands back its
 * path, which the section form then saves. Uploading first keeps the section
 * endpoints plain JSON and lets the drawer preview the image at once.
 *
 * An upload the admin never saves stays on disk; files a saved section stops
 * using are removed by PageSectionController.
 */
final class SectionImageController extends Controller implements HasMiddleware
{
    public const DIRECTORY = 'pages';

    public static function middleware(): array
    {
        return ['can:settings.manage'];
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('image')->store(self::DIRECTORY, 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }
}
