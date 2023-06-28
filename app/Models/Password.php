<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class Password extends Model
{
    use HasFactory;

    public static function Generate() : string {
        return Str::random(5);
    }
}
