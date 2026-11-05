<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $addressParts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->pincode,
            $this->country,
        ]);

        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code ?? sprintf('MSE%07d', $this->id),
            'customer_type' => $this->customer_type,
            'classification' => $this->classification,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'alternate_phone' => $this->alternate_phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'business_name' => $this->business_name,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'gst_number' => $this->gst_number,
            'pan_number' => $this->pan_number,
            'registration_number' => $this->registration_number,
            'industry' => $this->industry,
            'company_size' => $this->company_size,
            'company_type' => $this->company_type,
            'website' => $this->website,
            'annual_revenue' => $this->annual_revenue,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'credit_limit' => (float) $this->credit_limit,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country ?? 'India',
            'pincode' => $this->pincode,
            'full_address' => implode(', ', $addressParts),
            'segment' => $this->segment,
            'stage' => $this->stage ?? ($this->classification === 'existing' ? 'Old / Existing Customer' : 'New Customer'),
            'primary_manager' => $this->primary_manager,
            'customer_priority' => $this->customer_priority,
            'billing_email' => $this->billing_email,
            'payment_terms' => $this->payment_terms,
            'preferred_currency' => $this->preferred_currency ?? 'INR',
            'preferred_language' => $this->preferred_language ?? 'English',
            'orders_count' => (int) $this->orders_count,
            'total_spent' => (float) $this->total_spent,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'source' => $this->source,
            'created_by' => $this->createdBy?->name ?? 'Direct',
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
