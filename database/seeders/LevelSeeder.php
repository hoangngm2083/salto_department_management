<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            ['name' => 'Intern', 'slug' => 'intern', 'rank' => 10, 'probation_salary_percentage' => null],
            ['name' => 'Fresher', 'slug' => 'fresher', 'rank' => 20, 'probation_salary_percentage' => null],
            ['name' => 'Probation', 'slug' => 'probation', 'rank' => 30, 'probation_salary_percentage' => 85],
            ['name' => 'Junior', 'slug' => 'junior', 'rank' => 40, 'probation_salary_percentage' => null],
            ['name' => 'Middle', 'slug' => 'middle', 'rank' => 50, 'probation_salary_percentage' => null],
            ['name' => 'Senior', 'slug' => 'senior', 'rank' => 60, 'probation_salary_percentage' => null],
            ['name' => 'Principal', 'slug' => 'principal', 'rank' => 70, 'probation_salary_percentage' => null],
        ];

        foreach ($levels as $level) {
            Level::create([...$level, 'status' => 'active']);
        }
    }
}
