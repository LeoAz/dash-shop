<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isItem = $this->faker->boolean(50);
        return [
            'shop_id' => null,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 1, 200),
            'quantity' => $isItem ? $this->faker->numberBetween(0, 100) : 0,
            'type' => $isItem ? 'item' : 'service',
            'sku' => strtoupper($this->faker->bothify('SKU-####-??')),
        ];
    }
}
