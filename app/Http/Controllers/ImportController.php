<?php


namespace App\Http\Controllers;


use App\Services\ProductImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class ImportController extends Controller
{
    public function products(Request $request, ProductImportService $service)
    {
        $data = $request->validate([
            'csv' => 'required|file|mimes:csv,txt',
            'images_dir' => 'nullable|string', // e.g. 'import_images' under storage/app/
        ]);

        $imagesDir = $data['images_dir'] ?? null;

        // Force store on local disk so we know path = storage/app/...
        $path = $request->file('csv')->store('imports', 'local');
        $fullPath = Storage::disk('local')->path($path); // absolute OS path

        $summary = $service->import($fullPath, $imagesDir);

        return response()->json($summary);
    }
}
