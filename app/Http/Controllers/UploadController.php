<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\UploadChunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class UploadController extends Controller
{
    public function init(Request $request)
    {
        $data = $request->validate([
            'filename' => 'required|string',
            'size' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
            'mime' => 'nullable|string',
            'sha256' => 'required|string', // full file checksum expected by client
            'meta' => 'nullable|array',
        ]);


        $upload = Upload::create([
            'public_id' => (string) Str::uuid(),
            'filename' => $data['filename'],
            'size' => $data['size'],
            'total_chunks' => $data['total_chunks'],
            'mime' => $data['mime'] ?? null,
            'sha256' => $data['sha256'],
            'status' => 'initiated',
            'meta' => $data['meta'] ?? null,
        ]);


        // create temp folder
        Storage::disk('local')->makeDirectory("chunked/{$upload->public_id}");


        return response()->json([
            'upload_id' => $upload->public_id,
            'status' => $upload->status,
        ], 201);
    }

    public function chunk(string $publicId, Request $request)
    {
        $data = $request->validate([
            'index' => 'required|integer|min:0',
            'sha256' => 'required|string',
            'blob' => 'required|file',
        ]);


        $upload = Upload::where('public_id', $publicId)->firstOrFail();


        if ($upload->status === 'completed') {
            return response()->json(['message' => 'Already completed'], 409);
        }


        if ($data['index'] >= $upload->total_chunks) {
            return response()->json(['message' => 'Invalid chunk index'], 422);
        }


        // store/overwrite chunk (idempotent for duplicate sends)
        $chunkPath = "chunked/{$upload->public_id}/{$data['index']}.part";
        Storage::disk('local')->put($chunkPath, file_get_contents($request->file('blob')->getRealPath()));


        // record/overwrite chunk row (unique by upload_id+index)
        UploadChunk::updateOrCreate(
            ['upload_id' => $upload->id, 'index' => $data['index']],
            ['size' => $request->file('blob')->getSize(), 'sha256' => $data['sha256']]
        );


        if ($upload->status === 'initiated') {
            $upload->update(['status' => 'in_progress']);
        }


        return response()->json(['received' => $data['index']]);
    }


    public function complete(string $publicId)
    {
        $upload = Upload::where('public_id', $publicId)->firstOrFail();


        // verify all chunks exist
        $received = $upload->chunks()->count();
        if ($received !== (int) $upload->total_chunks) {
            return response()->json(['message' => 'Missing chunks', 'received' => $received, 'expected' => $upload->total_chunks], 409);
        }


        // concatenate
        $dir = storage_path("app/chunked/{$upload->public_id}");
        $assembled = storage_path("app/chunked/{$upload->public_id}/assembled.bin");
        $out = fopen($assembled, 'w');
        for ($i = 0; $i < $upload->total_chunks; $i++) {
            $part = fopen("$dir/{$i}.part", 'r');
            stream_copy_to_stream($part, $out);
            fclose($part);
        }
        fclose($out);


        // checksum verify (sha256)
        $hash = hash_file('sha256', $assembled);
        if (!hash_equals($upload->sha256, $hash)) {
            $upload->update(['status' => 'failed']);
            return response()->json(['message' => 'Checksum mismatch'], 422);
        }


        // move to permanent location
        $ext = pathinfo($upload->filename, PATHINFO_EXTENSION);
        $storedPath = 'uploads/originals/' . $upload->public_id . ($ext ? ('.' . $ext) : '');
        Storage::disk('public')->put($storedPath, file_get_contents($assembled));


        // cleanup temp dir
        collect(glob("$dir/*.part"))->each(fn($p) => @unlink($p));
        @unlink($assembled);


        $upload->update(['status' => 'completed']);


        return response()->json(['status' => 'completed', 'public_path' => $storedPath]);
    }
}
