<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::query()->value('id');

        $customers = [
            // B2C Existing Customer
            [
                'customer_type' => Customer::TYPE_B2C,
                'classification' => Customer::CLASSIFICATION_EXISTING,
                'name' => 'Rahul Sharma',
                'phone' => '9811234567',
                'email' => 'rahul.sharma@example.com',
                'alternate_phone' => '9811234568',
                'business_name' => null,
                'gst_number' => null,
                'pan_number' => null,
                'credit_limit' => 0,
                'address_line1' => 'Flat 402, Sunshine Heights, Sector 62',
                'address_line2' => 'Near Metro Station',
                'city' => 'Noida',
                'state' => 'Uttar Pradesh',
                'pincode' => '201301',
                'orders_count' => 4,
                'total_spent' => 5400.00,
                'notes' => 'Existing customer since 2024. Bought Samsung washing machine drain pump and PCB.',
                'is_active' => true,
                'source' => 'website',
                'created_by_user_id' => $adminId,
            ],
            // B2C New Customer
            [
                'customer_type' => Customer::TYPE_B2C,
                'classification' => Customer::CLASSIFICATION_NEW,
                'name' => 'Priya Verma',
                'phone' => '9899123456',
                'email' => 'priya.verma@example.com',
                'alternate_phone' => null,
                'business_name' => null,
                'gst_number' => null,
                'pan_number' => null,
                'credit_limit' => 0,
                'address_line1' => 'House 12, Pocket B, Mayur Vihar Phase 2',
                'address_line2' => null,
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'pincode' => '110091',
                'orders_count' => 0,
                'total_spent' => 0.00,
                'notes' => 'New inquiry for LG inverter refrigerator fan motor.',
                'is_active' => true,
                'source' => 'admin',
                'created_by_user_id' => $adminId,
            ],
            // B2B Existing Customer (Technician / Service Centre)
            [
                'customer_type' => Customer::TYPE_B2B,
                'classification' => Customer::CLASSIFICATION_EXISTING,
                'name' => 'Amit Kumar',
                'business_name' => 'CoolTech Aircon & Refrigeration Services',
                'gst_number' => '07AABCC1234D1Z9',
                'pan_number' => 'AABCC1234D',
                'phone' => '9873456789',
                'email' => 'sales@cooltechservices.com',
                'alternate_phone' => '9873456780',
                'credit_limit' => 50000.00,
                'address_line1' => 'Shop 14, Commercial Complex, Sector 18',
                'address_line2' => 'Atta Market',
                'city' => 'Noida',
                'state' => 'Uttar Pradesh',
                'pincode' => '201301',
                'orders_count' => 18,
                'total_spent' => 84200.00,
                'notes' => 'Wholesale dealer. Authorized technician in Noida/Ghaziabad. 15-day credit allowed.',
                'is_active' => true,
                'source' => 'admin',
                'created_by_user_id' => $adminId,
            ],
            // B2B New Customer (New Shop / Workshop)
            [
                'customer_type' => Customer::TYPE_B2B,
                'classification' => Customer::CLASSIFICATION_NEW,
                'name' => 'Gurpreet Singh',
                'business_name' => 'Punjab Electricals & Spares Hub',
                'gst_number' => '03AAECP9876E1Z1',
                'pan_number' => 'AAECP9876E',
                'phone' => '9815098765',
                'email' => 'contact@punjabspares.com',
                'alternate_phone' => null,
                'credit_limit' => 25000.00,
                'address_line1' => 'Plot 88, Industrial Area Phase 1',
                'address_line2' => null,
                'city' => 'Chandigarh',
                'state' => 'Punjab',
                'pincode' => '160002',
                'orders_count' => 1,
                'total_spent' => 8900.00,
                'notes' => 'Newly onboarded distributor for Daikin and Voltas copper coils.',
                'is_active' => true,
                'source' => 'website',
                'created_by_user_id' => $adminId,
            ],
        ];

        foreach ($customers as $data) {
            Customer::query()->updateOrCreate(['phone' => $data['phone']], $data);
        }
    }
}
