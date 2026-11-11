<?php

namespace App\Http\Controllers;

use App\Services\LegacyFileResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files that already exist inside the legacy project's uploads
 * directory. Nothing is ever written or modified there.
 *
 * The legacy system stored paths like "uploads/profiles/profile_27_123.png"
 * and rendered them with relative <img src> tags. In Laravel the files are
 * outside public/, so they are streamed through this route instead.
 */
class LegacyUploadController extends Controller
{
    public function __construct(private readonly LegacyFileResolver $files) {}

    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $absolute = $this->files->resolve($path);

        abort_if($absolute === null, 404);

        // Defence in depth: the resolved path must still be inside the
        // configured uploads directory (blocks ../ traversal).
        $base = realpath($this->files->baseDirectory());
        $real = realpath($absolute);

        abort_if($base === false || $real === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR), 404);

        return response()->file($real, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}