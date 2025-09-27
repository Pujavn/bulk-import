<?php


namespace App\Http\Controllers;


use App\Models\Product;
use App\Models\Upload;
use App\Services\ImageIngestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ProductImageController extends Controller
{
    public function attach(Product $product, string $publicId, ImageIngestService $ingest)
    {
        $upload = Upload::where('public_id', $publicId)->firstOrFail();
        abort_unless($upload->status === 'completed', 409, 'Upload not completed');


        // Create Image + Variants; idempotent by checksum
        $image = $ingest->ensureImageFromUpload($upload);


        // Idempotent: if already primary and same image -> no-op
        if ((int) $product->primary_image_id === (int) $image->id) {
            return response()->json(['message' => 'No change (same image already primary)']);
        }


        DB::transaction(function () use ($product, $image) {
            $product->update(['primary_image_id' => $image->id]);
        });


        return response()->json(['primary_image_id' => $image->id]);
    }
}
