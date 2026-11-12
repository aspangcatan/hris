<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves and loads signature images for the configured storage mode
 * (config/signatures.php, SIGNATURE_STORAGE in .env).
 *
 * A stored value is either a data URL ("data:image/...;base64,...") or a path
 * relative to the signatures disk ("signatures/leave/2026/11/<uuid>.png").
 * The two are told apart by the "data:" prefix, so both kinds can live in the
 * same column and old rows keep working after the mode is switched.
 */
class SignatureStorage
{
    private const MIME_EXTENSIONS = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
    ];

    /**
     * Turns a data URL from the signature pad into the value to put in the
     * database column. Returns null for an empty signature.
     *
     * @param string $folder sub-folder for the file mode, e.g. "leave"
     * @throws \InvalidArgumentException when the value is not a PNG/JPEG image
     */
    public function store(?string $dataUrl, string $folder = 'leave'): ?string
    {
        if ($dataUrl === null || trim($dataUrl) === '') {
            return null;
        }

        if (!$this->usesFiles()) {
            // Current behaviour: keep the data URL as-is. Only make sure it is an image
            // data URL, so a file path can never be smuggled into the column.
            if (strncmp($dataUrl, 'data:image/', 11) !== 0) {
                throw new \InvalidArgumentException('The signature must be an image.');
            }
            return $dataUrl;
        }

        [$mime, $binary] = $this->decode($dataUrl);

        $path = sprintf(
            '%s/%s/%s/%s.%s',
            $this->directory(),
            trim(preg_replace('/[^a-z0-9_-]/i', '', $folder), '/') ?: 'misc',
            now()->format('Y/m'),
            Str::uuid(),
            self::MIME_EXTENSIONS[$mime]
        );
        Storage::disk($this->disk())->put($path, $binary);

        return $path;
    }

    /**
     * Turns a stored value back into a data URL for <img src> (dompdf and the
     * browser both accept it). Returns null when there is nothing to show or
     * the file is missing.
     */
    public function toDataUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        if ($this->isDataUrl($stored)) {
            return $stored;
        }

        $path = $this->safePath($stored);
        if ($path === null || !Storage::disk($this->disk())->exists($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk($this->disk())->get($path));
    }

    /**
     * Removes the file behind a stored value (no-op for data URLs). Call it
     * after a signature has been replaced or cleared.
     */
    public function delete(?string $stored): void
    {
        if ($stored === null || $stored === '' || $this->isDataUrl($stored)) {
            return;
        }

        $path = $this->safePath($stored);
        if ($path !== null) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function usesFiles(): bool
    {
        return strtolower((string) config('signatures.storage', 'base64')) === 'file';
    }

    private function isDataUrl(string $value): bool
    {
        return strncmp($value, 'data:', 5) === 0;
    }

    /** @return array{0:string,1:string} [mime, raw bytes] */
    private function decode(string $dataUrl): array
    {
        if (!preg_match('#^data:(image/(?:png|jpe?g));base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            throw new \InvalidArgumentException('The signature must be a PNG or JPEG image.');
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($binary === false || @getimagesizefromstring($binary) === false) {
            throw new \InvalidArgumentException('The signature image is invalid.');
        }

        return [strtolower($m[1]), $binary];
    }

    /** Only paths inside the signatures folder, no traversal, image extensions only. */
    private function safePath(string $stored): ?string
    {
        $path = ltrim(str_replace('\\', '/', $stored), '/');
        $prefix = $this->directory() . '/';

        if (strncmp($path, $prefix, strlen($prefix)) !== 0
            || strpos($path, '..') !== false
            || !preg_match('/\.(png|jpe?g)$/i', $path)) {
            return null;
        }

        return $path;
    }

    private function disk(): string
    {
        return (string) config('signatures.disk', 'local');
    }

    private function directory(): string
    {
        return trim((string) config('signatures.directory', 'signatures'), '/');
    }
}
