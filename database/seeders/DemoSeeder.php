<?php

namespace Database\Seeders;

use App\Actions\Attendants\CreateAttendant;
use App\Actions\Institutions\CreateInstitution;
use App\Enums\GameStatus;
use App\Models\GameRecord;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sample data for a developer machine. The password is random and printed once.
 */
class DemoSeeder extends Seeder
{
    private const DIRECTOR_EMAIL = 'director@demo.test';

    public function run(CreateInstitution $createInstitution, CreateAttendant $createAttendant): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Demo data may only be seeded in the local environment.');
        }

        if (User::query()->where('email', self::DIRECTOR_EMAIL)->exists()) {
            $this->command->warn('Demo data is already there.');

            return;
        }

        $password = Str::password(16, symbols: false);

        $institution = $createInstitution->handle('Demo Sports Bar', [
            'name' => 'Demo Director',
            'email' => self::DIRECTOR_EMAIL,
            'password' => $password,
        ]);

        foreach (['one', 'two'] as $number) {
            $attendant = $createAttendant->handle($institution, "Demo Attendant {$number}", "attendant-{$number}@demo.test", $password)->user;

            foreach (range(1, 20) as $index) {
                GameRecord::factory()->create([
                    'institution_id' => $institution->id,
                    'attendant_id' => $attendant->id,
                    'status' => fake()->randomElement([GameStatus::Used, GameStatus::Used, GameStatus::Used, GameStatus::Failed]),
                    'local_timestamp' => now()->subHours($index * 3),
                ]);
            }

            Shift::factory()->create(['institution_id' => $institution->id, 'attendant_id' => $attendant->id]);
        }

        $this->command->info('Demo accounts: '.self::DIRECTOR_EMAIL.', attendant-one@demo.test, attendant-two@demo.test');
        $this->command->info("Password for all of them: {$password}");
    }
}
