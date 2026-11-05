<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCustomerController extends Controller
{
    /**
     * Check if a customer exists by phone or email to distinguish new vs returning customers.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $phone = $request->input('phone');
        $email = $request->input('email');

        if (! $phone && ! $email) {
            return response()->json([
                'exists' => false,
                'customer' => null,
            ]);
        }

        $customer = Customer::query()
            ->where(function ($query) use ($phone, $email): void {
                if ($phone) {
                    $query->where('phone', $phone);
                }
                if ($email) {
                    $query->orWhere('email', $email);
                }
            })
            ->first();

        return response()->json([
            'exists' => $customer !== null,
            'is_existing_customer' => $customer !== null,
            'customer' => $customer ? CustomerResource::make($customer)->resolve($request) : null,
        ]);
    }

    /**
     * Customer registration endpoint from the public storefront website.
     */
    public function register(Request $request): JsonResponse
    {
        $isB2b = $request->input('customer_type') === Customer::TYPE_B2B;

        $validated = $request->validate([
            'customer_type' => ['nullable', Rule::in([Customer::TYPE_B2C, Customer::TYPE_B2B])],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_name' => [$isB2b ? 'required' : 'nullable', 'string', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:12'],
        ]);

        $type = $validated['customer_type'] ?? Customer::TYPE_B2C;
        $phone = trim($validated['phone']);

        // Check if customer already exists by phone
        $existing = Customer::query()->where('phone', $phone)->first();

        if ($existing) {
            // Update details and mark as existing
            $existing->update([
                'name' => $validated['name'],
                'customer_type' => $type,
                'email' => $validated['email'] ?? $existing->email,
                'business_name' => $type === Customer::TYPE_B2B ? ($validated['business_name'] ?? $existing->business_name) : null,
                'gst_number' => $type === Customer::TYPE_B2B && ! empty($validated['gst_number']) ? Str::upper(trim($validated['gst_number'])) : $existing->gst_number,
                'address_line1' => $validated['address_line1'] ?? $existing->address_line1,
                'city' => $validated['city'] ?? $existing->city,
                'state' => $validated['state'] ?? $existing->state,
                'pincode' => $validated['pincode'] ?? $existing->pincode,
                'classification' => Customer::CLASSIFICATION_EXISTING,
            ]);

            return response()->json([
                'message' => 'Welcome back! Your customer details have been updated.',
                'customer' => CustomerResource::make($existing)->resolve($request),
                'is_new' => false,
            ]);
        }

        // Create new customer
        $customer = Customer::create([
            'customer_type' => $type,
            'classification' => Customer::CLASSIFICATION_NEW,
            'name' => $validated['name'],
            'phone' => $phone,
            'email' => $validated['email'] ?? null,
            'business_name' => $type === Customer::TYPE_B2B ? ($validated['business_name'] ?? null) : null,
            'gst_number' => $type === Customer::TYPE_B2B && ! empty($validated['gst_number']) ? Str::upper(trim($validated['gst_number'])) : null,
            'address_line1' => $validated['address_line1'] ?? null,
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'source' => 'website',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Account registered successfully.',
            'customer' => CustomerResource::make($customer)->resolve($request),
            'is_new' => true,
        ], 201);
    }
}
