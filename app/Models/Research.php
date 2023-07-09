<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class Research extends Model
{
    use HasFactory;

    protected $table = "researches";

    public static function slug(): string {
        $slug = Str::random(10);
        while(self::firstWhere("slug", $slug) !== null) {
            $slug = Str::random(10);
        }
        return $slug;
    }

    public static function findBySlug(string $slug) {
        return self::firstWhere("slug", $slug);
    }

    public function respondents() {
        return $this->hasMany(Respondent::class);
    }

    public function groups() {
        return $this->hasMany(Group::class);
    }
}
