<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class ImageVariant extends Model
{
protected $fillable = ['image_id','max_side','path','width','height'];


public function image()
{
return $this->belongsTo(Image::class);
}
}
