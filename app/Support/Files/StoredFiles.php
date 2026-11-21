<?php

namespace App\Support\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files people upload to a school: assignment worksheets, handed-in papers and photos.
 *
 * They live on the private disk and are only served through controllers that
 * check who is asking. A record keeps a list of them as
 * ['path' => …, 'name' => original name, 'size' => bytes, 'mime' => type].
 */
final class StoredFiles
{
    public const DISK = 'local';

    /** Papers and photos of written work. */
    public const DOCUMENT_TYPES = 'pdf,doc,docx,odt,rtf,txt,ppt,pptx,xls,xlsx,jpg,jpeg,png,webp,heic';

    public const MAX_KILOBYTES = 10240;

    public const MAX_FILES = 5;

    /**
     * Validation rules for a list of document uploads under $field.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $field): array
    {
        return [
            $field => ['nullable', 'array', 'max:'.self::MAX_FILES],
            $field.'.*' => ['file', 'max:'.self::MAX_KILOBYTES, 'mimes:'.self::DOCUMENT_TYPES],
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $uploads
     * @return list<array{path: string, name: string, size: int, mime: string}>
     */
    public static function store(array $uploads, string $directory): array
    {
        return array_values(array_map(fn (UploadedFile $upload): array => [
            'path' => $upload->storeAs($directory, Str::ulid()->toBase32().'.'.Str::lower($upload->getClientOriginalExtension() ?: $upload->extension()), self::DISK),
            'name' => Str::limit($upload->getClientOriginalName(), 120, ''),
            'size' => (int) $upload->getSize(),
            'mime' => (string) ($upload->getMimeType() ?? 'application/octet-stream'),
        ], $uploads));
    }

    /**
     * @param  list<array{path: string}>|null  $files
     */
    public static function delete(?array $files): void
    {
        foreach ($files ?? [] as $file) {
            Storage::disk(self::DISK)->delete($file['path']);
        }
    }

    /**
     * Send a stored file: images and PDFs open in the browser, everything else downloads.
     *
     * @param  array{path: string, name: string, mime: string}  $file
     */
    public static function respond(array $file, bool $download = false): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($file['path']), 404);

        $inline = ! $download && (self::isImage($file) || self::isPdf($file));

        return Storage::disk(self::DISK)->response($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $inline ? 'inline' : 'attachment');
    }

    /**
     * @param  array{mime: string}  $file
     */
    public static function isImage(array $file): bool
    {
        return in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    /**
     * @param  array{mime: string}  $file
     */
    public static function isPdf(array $file): bool
    {
        return $file['mime'] === 'application/pdf';
    }

    /**
     * @param  array{size: int}  $file
     */
    public static function size(array $file): string
    {
        return $file['size'] >= 1048576
            ? round($file['size'] / 1048576, 1).' MB'
            : max(1, (int) round($file['size'] / 1024)).' KB';
    }

    /**
     * A Lucide icon name for the file's type.
     *
     * @param  array{mime: string, name: string}  $file
     */
    public static function icon(array $file): string
    {
        return match (true) {
            self::isImage($file) => 'image',
            self::isPdf($file) => 'file-text',
            str_contains($file['mime'], 'sheet') || str_ends_with(Str::lower($file['name']), '.xlsx') => 'file-spreadsheet',
            default => 'file',
        };
    }
}
