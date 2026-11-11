<?php

namespace App\Http\Controllers\SocialFeed;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves Social Feed media files referenced by the database as
 * "social_feed/uploads/{images,files}/<file>".
 *
 * Resolution order (mirrors the lived behaviour of the legacy web root):
 *
 *   1. public/social_feed/uploads/<path>   - files uploaded by the migrated app
 *   2. <legacy_root>/social_feed/uploads/<path> - read-only files uploaded by the
 *      legacy Vanilla PHP app (never written to)
 *
 * The legacy JS hardcoded media URLs under /ODCI/...; here post_renderer.js
 * maps them to /social-feed/uploads/... which lands in this controller.
 */
class SocialFeedUploadController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        // No path traversal out of the intended tree.
        abort_if(
            str_contains($path, '..') || str_contains($path, "\0"),
            404
        );

        // 1) Files written by this Laravel app.
        $publicBase = public_path('social_feed'.DIRECTORY_SEPARATOR.'uploads');
        $publicFile = $publicBase.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (is_file($publicFile)) {
            return $this->fileResponse($publicFile, $publicBase);
        }

        // 2) Legacy files still sitting inside the read-only legacy project.
        $legacyBase = rtrim((string) config('odci.legacy_root'), '\\/')
            .DIRECTORY_SEPARATOR.'social_feed'
            .DIRECTORY_SEPARATOR.'uploads';
        $legacyFile = $legacyBase.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (is_file($legacyFile)) {
            return $this->fileResponse($legacyFile, $legacyBase);
        }

        abort(404);
    }

    private function fileResponse(string $absolute, string $base): BinaryFileResponse
    {
        // Defence in depth: resolved file must still be inside its base tree.
        $realBase = realpath($base);
        $realFile = realpath($absolute);

        abort_if(
            $realBase === false || $realFile === false || ! str_starts_with($realFile, $realBase.DIRECTORY_SEPARATOR),
            404
        );

        return response()->file($realFile, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}