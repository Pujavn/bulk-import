<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class Upload extends Model
{
    protected $fillable = ['public_id', 'filename', 'size', 'total_chunks', 'mime', 'sha256', 'status', 'meta'];
    protected $casts = ['meta' => 'array'];


    public function chunks()
    {
        return $this->hasMany(UploadChunk::class);
    }
}
