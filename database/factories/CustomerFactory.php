<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isB2b = fake()->boolean(35);
        $isExisting = fake()->boolean(40);

        return [
            'customer_type' => $isB2b ? Customer::TYPE_B2B : Customer::TYPE_B2C,
            'classification' => $isExisting ? Customer::CLASSIFICATION_EXISTING : Customer::CLASSIFICATION_NEW,
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('98########'),
            'email' => fake()->unique()->safeEmail(),
            'alternate_phone' => fake()->boolean(40) ? fake()->numerify('97########') : null,
            'business_name' => $isB2b ? fake()->company().' Electricals & Service' : null,
            'gst_number' => $isB2b ? '07'.fake()->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}') : null,
            'pan_number' => $isB2b ? fake()->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}') : null,
            'credit_limit' => $isB2b ? fake()->randomElement([10000, 25000, 50000, 100000]) : 0,
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->secondaryAddress(),
            'city' => fake()->randomElement(['New Delhi', 'Noida', 'Gurugram', 'Ghaziabad', 'Faridabad', 'Jaipur', 'Lucknow']),
            'state' => 'Delhi NCR',
            'pincode' => fake()->numerify('1100##'),
            'orders_count' => $isExisting ? fake()->numberBetween(1, 15) : 0,
            'total_spent' => $isExisting ? fake()->randomFloat(2, 500, 45000) : 0,
            'notes' => fake()->boolean(30) ? 'Regular client for AC cooling coils and universal remotes' : null,
            'is_active' => true,
            'source' => fake()->randomElement(['admin', 'website']),
            'created_by_user_id' => User::query()->value('id'),
        ];
    }

    public function b2b(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_type' => Customer::TYPE_B2B,
            'business_name' => fake()->company().' Services',
            'gst_number' => '07AAAAA0000A1Z5',
        ]);
    }

    public function b2c(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_type' => Customer::TYPE_B2C,
            'business_name' => null,
            'gst_number' => null,
            'credit_limit' => 0,
        ]);
    }

    public function existing(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => Customer::CLASSIFICATION_EXISTING,
            'orders_count' => 3,
            'total_spent' => 4500.00,
        ]);
    }

    public function newCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => Customer::CLASSIFICATION_NEW,
            'orders_count' => 0,
            'total_spent' => 0.00,
        ]);
    }
}
