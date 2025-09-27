<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class Image extends Model
{
    protected $fillable = ['disk', 'path', 'mime', 'width', 'height', 'sha256'];


    public function variants()
    {
        return $this->hasMany(ImageVariant::class);
    }
}
