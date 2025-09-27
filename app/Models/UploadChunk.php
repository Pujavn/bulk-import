<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class UploadChunk extends Model
{
    protected $fillable = ['upload_id', 'index', 'size', 'sha256'];


    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
