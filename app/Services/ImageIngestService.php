<?php


namespace App\Services;


use App\Models\Image;
use App\Models\ImageVariant;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as Img; // requires intervention/image ^3


class ImageIngestService
{
    /** Create image+variants from an already completed Upload */
    public function ensureImageFromUpload(Upload $upload): Image
    {
        $disk = 'public';
        $path = 'uploads/originals/' . $upload->public_id . '.' . pathinfo($upload->filename, PATHINFO_EXTENSION);
        if (!Storage::disk($disk)->exists($path)) {
            // In case complete stored a different extension, fallback to wildcard find
            $guessed = collect(Storage::disk($disk)->files('uploads/originals'))
                ->first(fn($p) => str_contains($p, $upload->public_id));
            if (!$guessed) throw new \RuntimeException('Original not found for upload');
            $path = $guessed;
        }
        $sha256 = $upload->sha256;
        $mime = $upload->mime ?: 'image/*';
        return $this->ensureImageFromStored($disk, $path, $sha256, $mime);
    }


    /** Create image+variants from a stored original (idempotent by sha256) */
    public function ensureImageFromStored(string $disk, string $path, string $sha256, string $mime): Image
    {
        $existing = Image::where('sha256', $sha256)->first();
        if ($existing) {
            // ensure variants exist (might be missing if crash earlier)
            $this->ensureVariants($existing);
            return $existing;
        }


        $bin = Storage::disk($disk)->get($path);
        $img = Img::read($bin);


        $image = Image::create([
            'disk' => $disk,
            'path' => $path,
            'mime' => $mime,
            'width' => $img->width(),
            'height' => $img->height(),
            'sha256' => $sha256,
        ]);


        $this->ensureVariants($image, $img);
        return $image;
    }
    private function ensureVariants(Image $image, $sourceImg = null): void
    {
        $targets = [256, 512, 1024];
        $disk = $image->disk;


        foreach ($targets as $side) {
            $existing = $image->variants()->where('max_side', $side)->first();
            if ($existing) continue;


            $img = $sourceImg ? clone $sourceImg : Img::read(Storage::disk($disk)->get($image->path));
            // maintain aspect ratio, no upscaling beyond original
            $img->scaleDown(width: $side, height: $side);


            $variantPath = 'uploads/variants/' . $image->id . '_' . $side . '.jpg';
            Storage::disk($disk)->put($variantPath, (string) $img->toJpeg(85));


            $image->variants()->create([
                'max_side' => $side,
                'path' => $variantPath,
                'width' => $img->width(),
                'height' => $img->height(),
            ]);
        }
    }
}
