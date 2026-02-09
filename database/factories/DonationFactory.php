<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Donation>
 */
class DonationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stripe_payment_id' => 'pi_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'amount' => fake()->randomElement([500, 1000, 2500, 5000, 10000]),
            'currency' => 'usd',
            'status' => 'completed',
            'donor_name' => fake()->name(),
            'donor_email' => fake()->email(),
            'message' => fake()->optional()->sentence(),
        ];
    }
}
