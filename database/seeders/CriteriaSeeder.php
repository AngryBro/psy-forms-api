<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Criteria;

class CriteriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $criterias = ["Угловое преобразование Фишера"];
        foreach($criterias as $criteria) {
            $new = new Criteria;
            $new->name = $criteria;
            $new->save();
        }
    }
}
