<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    public function research() {
        return $this->belongsTo(Research::class);
    }

    public function getConditionsAttribute(string $value) :array {
        return json_decode($value, true);
    }
}
