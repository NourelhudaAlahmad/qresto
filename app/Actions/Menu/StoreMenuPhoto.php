<?php

namespace App\Actions\Menu;

use App\Models\MenuItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Keeps the original upload and writes a centre-cropped square thumbnail
 * for list rows (#31). Uses GD only, no image package.
 */
class StoreMenuPhoto
{
    public function handle(MenuItem $item, UploadedFile $photo): void
    {
        $disk = Storage::disk((string) config('qresto.menu.photo_disk', 'public'));
        $directory = "menu/{$item->restaurant_id}/{$item->id}";
        $stamp = now()->format('YmdHis');

        $originalPath = $photo->storeAs(
            $directory,
            "original-{$stamp}.".$photo->extension(),
            ['disk' => (string) config('qresto.menu.photo_disk', 'public')],
        );

        if ($originalPath === false) {
            throw new RuntimeException('Could not store the photo.');
        }

        // Without GD the original doubles as the thumbnail rather than the
        // upload failing; list rows crop it with object-fit.
        $thumbPath = null;

        if (function_exists('imagecreatefromstring')) {
            $thumbPath = "{$directory}/thumb-{$stamp}.jpg";
            $disk->put($thumbPath, $this->squareThumbnail((string) file_get_contents($photo->getRealPath())));
        }

        // Replace, never accumulate: drop the previous pair.
        foreach ([$item->photo_path, $item->photo_thumb_path] as $old) {
            if (is_string($old) && $old !== '' && ! str_contains($old, '://') && $disk->exists($old)) {
                $disk->delete($old);
            }
        }

        $item->forceFill([
            'photo_path' => $originalPath,
            'photo_thumb_path' => $thumbPath,
        ])->save();
    }

    private function squareThumbnail(string $bytes): string
    {
        $source = imagecreatefromstring($bytes);

        if ($source === false) {
            throw new RuntimeException('The photo could not be read.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $size = max(1, (int) config('qresto.menu.thumb_size', 240));

        $thumb = imagecreatetruecolor($size, $size);

        imagecopyresampled(
            $thumb,
            $source,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            $size,
            $size,
            $side,
            $side,
        );

        ob_start();
        imagejpeg($thumb, null, 82);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumb);

        return $jpeg;
    }
}
