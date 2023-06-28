<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class Token extends Model
{
    use HasFactory;

    public function user() {
        return $this->belongsTo(User::class);
    }

    public static function Generate(): string {
        return Str::random(20);
    }
}
