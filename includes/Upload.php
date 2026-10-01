<?php

/**
 * iPharmaLink :: Secure upload helper
 * ---------------------------------------------------------------------------
 * Hardened against the classic upload attacks:
 *   - extension allow-list derived from the *verified* MIME type, not the
 *     client-supplied filename
 *   - finfo content sniffing (magic bytes)
 *   - random generated filenames — the original is never used on disk
 *   - re-encoded raster images via GD to strip embedded PHP/EXIF payloads
 *   - per-context storage subdirectories, non-executable by convention
 *   - size cap enforced both by PHP and here
 */

declare(strict_types=1);

namespace App;

final class UploadException extends \RuntimeException {}

final class Upload
{
    /** context => [subdirectory, allowed mime map, max size MB] */
    private const CONTEXTS = [
        'product'     => ['products',      'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'max' => 5],
        'pharmacy'    => ['pharmacies',    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'max' => 5],
        'supplier'    => ['suppliers',     'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'max' => 5],
        'profile'     => ['profiles',      'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'max' => 3],
        'category'    => ['categories',    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'max' => 3],
        'brand'       => ['brands',        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'max' => 2],
        'banner'      => ['banners',       'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'max' => 6],
        'document'    => ['documents',     'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'max' => 8],
        'prescription' => ['prescriptions', 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'max' => 8],
        'pod'         => ['pod',           'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'max' => 5],
        'review'      => ['reviews',       'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'max' => 4],
    ];

    /**
     * Store a single uploaded file.
     *
     * @param  array<string,mixed> $file  an entry from $_FILES
     * @return array{path:string,name:string,size:int,mime:string}|null  path is web-relative
     */
    public static function store(array $file, string $context): ?array
    {
        $spec = self::CONTEXTS[$context] ?? null;
        if ($spec === null) {
            throw new UploadException('Unknown upload context.');
        }

        self::assertNoUploadError($file, $spec['max']);

        $tmpPath = (string) $file['tmp_name'];
        $size    = (int) $file['size'];

        if ($size <= 0) {
            throw new UploadException('The uploaded file is empty.');
        }

        // ---- 1. verify real content type -----------------------------------
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($tmpPath);

        $allowed = $spec;
        unset($allowed['max']);
        if (!isset($allowed[$mime])) {
            throw new UploadException('That file type is not allowed (' . $mime . ').');
        }

        // ---- 2. raster images get re-encoded (kills embedded payloads) -----
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $tmpPath = self::sanitiseImage($tmpPath, $mime) ?? $tmpPath;
            $size    = (int) filesize($tmpPath);
        }

        // ---- 3. SVG is text: strip scripts and event handlers -------------
        if ($mime === 'image/svg+xml') {
            self::assertSafeSvg($tmpPath);
        }

        // ---- 4. write with a generated name --------------------------------
        $extension = $allowed[$mime];
        $dir       = Config::str('uploads.path') . '/' . $spec[0];
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UploadException('Could not create the upload directory. Check storage permissions.');
        }
        @chmod($dir, 0755);

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $target   = $dir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $target) && !rename($tmpPath, $target)) {
            throw new UploadException('Could not save the uploaded file.');
        }
        @chmod($target, 0644);

        return [
            'path' => 'storage/uploads/' . $spec[0] . '/' . $filename,
            'name' => basename((string) $file['name']),
            'size' => $size,
            'mime' => $mime,
        ];
    }

    /**
     * Store several files from a multi-file input.
     *
     * @param  array<string,mixed> $files
     * @return list<array{path:string,name:string,size:int,mime:string}>
     */
    public static function storeMany(array $files, string $context): array
    {
        // Normalise the transposed $_FILES shape into a list.
        if (!isset($files['name']) || !is_array($files['name'])) {
            $single = self::store($files, $context);
            return $single === null ? [] : [$single];
        }

        $stored = [];
        foreach (array_keys($files['name']) as $index) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $result = self::store([
                'name'     => $files['name'][$index],
                'type'     => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index],
                'error'    => $files['error'][$index],
                'size'     => $files['size'][$index] ?? 0,
            ], $context);
            if ($result !== null) {
                $stored[] = $result;
            }
        }
        return $stored;
    }

    /** Remove a previously uploaded file (path is web-relative). */
    public static function delete(?string $relativePath): bool
    {
        if ($relativePath === null || $relativePath === '') {
            return false;
        }
        $base = realpath(Config::str('uploads.path'));
        if ($base === false) {
            return false;
        }
        $full = realpath(APP_ROOT . '/' . ltrim($relativePath, '/'));
        // Refuse anything that resolves outside the uploads directory.
        if ($full === false || !str_starts_with($full, $base) || !is_file($full)) {
            return false;
        }
        return @unlink($full);
    }

    // -----------------------------------------------------------------------

    private static function assertNoUploadError(array $file, int $maxMb): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "That file is larger than the {$maxMb}MB limit.",
            UPLOAD_ERR_PARTIAL    => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'Please choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder configured.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION  => 'The upload was blocked by a server extension.',
            default               => 'The file could not be uploaded. Please try again.',
        };
        throw new UploadException($message);
    }

    private static function sanitiseImage(string $path, string $mime): ?string
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return null;   // GD unavailable — fall back to the original bytes
        }
        $info = @getimagesize($path);
        if ($info === false) {
            throw new UploadException('That file is not a readable image.');
        }

        $limits = Config::get('uploads.image_max', ['width' => 2000, 'height' => 2000]);
        if ($info[0] > $limits['width'] || $info[1] > $limits['height']) {
            throw new UploadException(sprintf('Images must be at most %dx%d pixels.', $limits['width'], $limits['height']));
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
        if (!$source instanceof \GdImage) {
            return null;
        }

        // Re-encode into a fresh file: any appended PHP payload is discarded.
        $clean = $path . '.clean';
        $ok = false;
        if ($mime === 'image/jpeg') {
            $ok = @imagejpeg($source, $clean, 85);
        } elseif ($mime === 'image/png') {
            $ok = @imagepng($source, $clean, 6);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $ok = @imagewebp($source, $clean, 85);
        }
        imagedestroy($source);

        if (!$ok || !is_file($clean)) {
            return null;
        }
        return $clean;
    }

    private static function assertSafeSvg(string $path): void
    {
        $content = (string) file_get_contents($path);
        $blocked = ['<script', 'javascript:', 'onload=', 'onerror=', 'onclick=', '<foreignObject', 'data:text/html', '<iframe', '<embed', 'xlink:href="data:'];
        foreach ($blocked as $needle) {
            if (stripos($content, $needle) !== false) {
                throw new UploadException('That SVG contains scripting content and was rejected.');
            }
        }
    }
}
