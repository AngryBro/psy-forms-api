<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statistic extends Model
{
    use HasFactory;

    public function getResultAttribute(string $value) :array {
        return json_decode($value, true);
    }

    public function research() {
        return $this->belongsTo(Research::class);
    }

    public function groups() {
        return $this
        ->belongsToMany(Group::class, "statistic_group")
        ->select("name");
    }
}
