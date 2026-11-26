<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\GameRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRecord>
 */
class GameRecordFactory extends Factory
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
            'table_id' => fake()->randomElement(['T01', 'T02', 'T03']),
            'location_id' => 'MAIN',
            'code' => fake()->unique()->regexify('[A-HJKMNP-Z2-9]{6}'),
            'payment_method' => fake()->randomElement(['Cash', 'M-Pesa']),
            'status' => GameStatus::Used,
            'local_timestamp' => now(),
        ];
    }
}
