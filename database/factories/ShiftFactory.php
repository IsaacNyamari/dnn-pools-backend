<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Team::factory(),
            'attendant_id' => User::factory(),
            'uploaded_by' => fn (array $attributes) => $attributes['attendant_id'],
            'device_id' => '',
            'location_id' => 'MAIN',
            'start_time' => now()->subHours(8),
            'end_time' => now(),
            'total_cash' => fake()->numberBetween(0, 30),
            'total_mpesa' => fake()->numberBetween(0, 30),
        ];
    }
}
