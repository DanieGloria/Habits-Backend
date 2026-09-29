<?php

namespace Database\Seeders;

use App\Models\Habit;
use Illuminate\Database\Seeder;

class HabitSeeder extends Seeder
{
    public function run(): void
    {
        $habits = [
            ['name' => 'Ejercicio',           'type' => 'POSITIVE', 'icon' => '💪', 'is_global' => true],
            ['name' => 'Correr',              'type' => 'POSITIVE', 'icon' => '🏃', 'is_global' => true],
            ['name' => 'Leer',                'type' => 'POSITIVE', 'icon' => '📚', 'is_global' => true],
            ['name' => 'No beber refrescos',  'type' => 'NEGATIVE', 'icon' => '🥤', 'is_global' => true],
            ['name' => 'No comer chucherías', 'type' => 'NEGATIVE', 'icon' => '🍬', 'is_global' => true],
        ];

        foreach ($habits as $habit) {
            Habit::firstOrCreate(
                ['name' => $habit['name'], 'is_global' => true],
                $habit
            );
        }
    }
}