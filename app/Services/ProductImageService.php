<?php

namespace App\Services;

use DomainException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Single place that knows where product photos live (object storage).
 * Paths saved before the move (e.g. "products/x.jpg" on the container disk)
 * are "legacy": they no longer exist anywhere, so they resolve to no URL
 * and the UI shows the placeholder.
 */
class ProductImageService
{
    /** Folder of every photo stored on the object-storage disk. */
    public const PREFIX = 'product-images';

    public function disk(): Filesystem
    {
        return \Illuminate\Support\Facades\Storage::disk(config('filesystems.product_image_disk'));
    }

    public function store(UploadedFile $file): string
    {
        try {
            $path = $this->disk()->putFile(self::PREFIX, $file);
        } catch (Throwable $e) {
            report($e);
            $path = false;
        }

        if (! $path) {
            throw new DomainException('Image upload failed. Please try again.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $this->isManaged($path)) {
            return;
        }

        try {
            $this->disk()->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function isManaged(?string $path): bool
    {
        return filled($path) && str_starts_with($path, self::PREFIX.'/');
    }

    public function url(?string $path): ?string
    {
        return $this->isManaged($path) ? $this->disk()->url($path) : null;
    }
}
