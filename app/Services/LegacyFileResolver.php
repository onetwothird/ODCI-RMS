<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Resolves file paths that were stored by the legacy Vanilla PHP application.
 *
 * The legacy users.php carried a getProfileImagePath() helper that probed a
 * list of relative paths with file_exists(). Those paths were relative to the
 * legacy directory depth (e.g. '../../uploads/profile_images/...'), which does
 * not exist under public/ in Laravel.
 *
 * This service keeps the SAME candidate order and the same fallback-to-
 * basename behaviour, but anchors every candidate to the configured legacy
 * uploads directory so the files keep resolving without being moved.
 */
class LegacyFileResolver
{
    /**
     * Directory (relative to legacy_root) that uploaded files live under.
     */
    public function baseDirectory(): string
    {
        return rtrim(
            config('odci.legacy_root').DIRECTORY_SEPARATOR.trim(config('odci.uploads_dir'), '/\\'),
            '/\\',
        );
    }

    /**
     * Candidate relative paths, in the same priority order as the legacy
     * getProfileImagePath(): the stored path first, then the known folders.
     *
     * @return array<int, string>
     */
    protected function candidates(string $storedPath, string $folder): array
    {
        $clean = ltrim($storedPath, '/\\');

        return array_values(array_unique([
            $clean,
            'profile_images'.DIRECTORY_SEPARATOR.basename($clean),
            $folder.DIRECTORY_SEPARATOR.basename($clean),
            basename($clean),
        ]));
    }

    /**
     * Returns the absolute path of the first candidate that exists on disk,
     * or null when none do.
     */
    public function resolve(string $storedPath, string $folder = 'profiles'): ?string
    {
        if ($storedPath === '') {
            return null;
        }

        // Files uploaded through Laravel live under storage/app/private/uploads;
        // older ones sit in the legacy uploads folder. Check both, Laravel first.
        foreach ([$this->privateDirectory(), $this->baseDirectory()] as $base) {
            foreach ($this->candidates(preg_replace('#^uploads/#', '', ltrim(str_replace(chr(92), '/', $storedPath), '/')), $folder) as $candidate) {
                $absolute = $base.DIRECTORY_SEPARATOR.$candidate;

                if (is_file($absolute)) {
                    return $absolute;
                }
            }

            foreach ($this->candidates($storedPath, $folder) as $candidate) {
                $absolute = $base.DIRECTORY_SEPARATOR.$candidate;

                if (is_file($absolute)) {
                    return $absolute;
                }
            }
        }

        return null;
    }

    /**
     * Directory that receives uploads made through the Laravel app.
     */
    public function privateDirectory(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'uploads');
    }

    /**
     * Public URL for the stored path, served through the legacy-uploads route.
     * Returns null when the file is missing so callers can fall back to the
     * initials avatar, exactly like the legacy template did.
     */
    public function url(string $storedPath, string $folder = 'profiles'): ?string
    {
        $absolute = $this->resolve($storedPath, $folder);

        if ($absolute === null) {
            return null;
        }

        return route('legacy.uploads', ['path' => $this->relativePath($absolute)]);
    }

    /**
     * Path relative to the uploads directory, used as the route parameter.
     */
    public function relativePath(string $absolutePath): string
    {
        $base = str_starts_with($absolutePath, $this->privateDirectory())
            ? $this->privateDirectory()
            : $this->baseDirectory();

        // Used to build URLs, so normalise Windows separators to "/".
        return str_replace('\\', '/', ltrim(substr($absolutePath, strlen($base)), '/\\'));
    }
}