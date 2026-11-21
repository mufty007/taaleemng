<?php

namespace App\Support\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Passport photos for student and staff records and ID cards.
 *
 * Phone photos are large and often sideways, so when GD is available the photo
 * is turned upright, cropped to the 4:5 passport shape and saved as a small JPEG.
 */
final class PassportPhoto
{
    public const WIDTH = 480;

    public const HEIGHT = 600;

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']];
    }

    public static function store(UploadedFile $upload, string $directory): string
    {
        $path = $directory.'/'.Str::ulid()->toBase32().'.jpg';
        $jpeg = self::passport($upload);

        if ($jpeg === null) {
            return $upload->storeAs($directory, Str::ulid()->toBase32().'.'.$upload->extension(), StoredFiles::DISK);
        }

        Storage::disk(StoredFiles::DISK)->put($path, $jpeg);

        return $path;
    }

    private static function passport(UploadedFile $upload): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($upload->getRealPath()));

        if ($source === false) {
            return null;
        }

        $source = self::upright($source, $upload);
        $width = imagesx($source);
        $height = imagesy($source);

        // Largest 4:5 window, centred across and a little above centre (faces sit high).
        $cropWidth = (int) min($width, $height * self::WIDTH / self::HEIGHT);
        $cropHeight = (int) min($height, $width * self::HEIGHT / self::WIDTH);
        $x = (int) (($width - $cropWidth) / 2);
        $y = (int) max(0, ($height - $cropHeight) / 3);

        $photo = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($photo, 0, 0, (int) imagecolorallocate($photo, 255, 255, 255));
        imagecopyresampled($photo, $source, 0, 0, $x, $y, self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);

        ob_start();
        imagejpeg($photo, null, 85);

        return (string) ob_get_clean();
    }

    private static function upright(\GdImage $image, UploadedFile $upload): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($upload->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($upload->getRealPath())['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
