<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $city = fake()->unique()->city();

        return [
            'slug' => str($city)->slug()->value(),
            'name' => "ویکی پلاس {$city}",
            'type' => Branch::FRANCHISE,
            'presence' => 'both',
            'city' => $city,
            'is_active' => true,
        ];
    }

    public function central(): static
    {
        return $this->state(['type' => Branch::CENTRAL, 'slug' => 'central', 'name' => 'ویکی پلاس']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Give the branch a host, the way a real one is reached.
     */
    public function reachableAt(string $host): static
    {
        return $this->afterCreating(fn (Branch $branch) => $branch->domains()->create([
            'host' => $host,
            'is_primary' => true,
        ]));
    }
}
