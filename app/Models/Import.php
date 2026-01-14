<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $fillable = ['user_id','original_filename','stored_path','status'];

    public function rows() { return $this->hasMany(ImportRow::class); }
    public function user() { return $this->belongsTo(User::class); }
}
