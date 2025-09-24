<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => null,
            'user_id' => null,
            'customer_name' => $this->faker->name(),
            'sale_date' => now(),
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
            'status' => 'pending',
            'hairdresser_id' => null,
        ];
    }
}
