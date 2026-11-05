<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeCustomerAccess($request);

        $search = $request->query('search');
        $type = $request->query('type');
        $classification = $request->query('classification');
        $status = $request->query('status');
        $perPage = max(5, min(100, $request->integer('per_page', 10)));

        $query = Customer::query()
            ->with('createdBy:id,name')
            ->search($search);

        if (in_array($type, [Customer::TYPE_B2B, Customer::TYPE_B2C], true)) {
            $query->where('customer_type', $type);
        }

        if (in_array($classification, [Customer::CLASSIFICATION_NEW, Customer::CLASSIFICATION_EXISTING], true)) {
            $query->where('classification', $classification);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $paginator = $query->latest('id')->paginate($perPage);

        $stats = [
            'total' => Customer::count(),
            'b2c' => Customer::where('customer_type', Customer::TYPE_B2C)->count(),
            'b2b' => Customer::where('customer_type', Customer::TYPE_B2B)->count(),
            'new' => Customer::where('classification', Customer::CLASSIFICATION_NEW)->count(),
            'existing' => Customer::where('classification', Customer::CLASSIFICATION_EXISTING)->count(),
        ];

        return response()->json([
            'customers' => CustomerResource::collection($paginator->items())->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Any staff member with customer access can create/register a new customer.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $this->authorizeCustomerAccess($request);
        $validated = $this->validateCustomer($request);

        $customer = Customer::create([
            ...$validated,
            'source' => $validated['source'] ?? 'Direct',
            'created_by_user_id' => $actor->id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Customer registered successfully.',
            'customer' => CustomerResource::make($customer->load('createdBy:id,name'))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomerAccess($request);

        return response()->json([
            'customer' => CustomerResource::make($customer->load('createdBy:id,name'))->resolve($request),
        ]);
    }

    /**
     * ONLY admin and super_admin can edit a saved customer record.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeAdminOnly($request);
        $validated = $this->validateCustomer($request, $customer);

        $customer->update([
            ...$validated,
            'is_active' => $request->boolean('is_active', $customer->is_active),
        ]);

        return response()->json([
            'message' => 'Customer details updated.',
            'customer' => CustomerResource::make($customer->refresh()->load('createdBy:id,name'))->resolve($request),
        ]);
    }

    /**
     * ONLY admin and super_admin can toggle customer active/inactive status.
     */
    public function setStatus(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeAdminOnly($request);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $customer->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'message' => $customer->is_active ? 'Customer activated.' : 'Customer deactivated.',
            'customer' => CustomerResource::make($customer->refresh()->load('createdBy:id,name'))->resolve($request),
        ]);
    }

    /**
     * ONLY admin and super_admin can delete a customer record.
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeAdminOnly($request);

        if ($customer->orders_count > 0) {
            abort(422, 'Cannot delete a customer who has existing order records. You can deactivate them instead.');
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer record deleted.',
        ]);
    }

    /**
     * Bulk import customers from parsed sheet / CSV / JSON.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $actor = $this->authorizeCustomerAccess($request);

        $request->validate([
            'customers' => ['required', 'array', 'min:1', 'max:500'],
            'customers.*.phone' => ['required', 'string'],
        ]);

        $rows = $request->input('customers', []);
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $phone = preg_replace('/\D/', '', (string) ($row['phone'] ?? ''));
                if (strlen($phone) !== 10) {
                    $skippedCount++;
                    $errors[] = "Row #".($index + 1).": Invalid 10-digit mobile number ({$row['phone']}).";
                    continue;
                }

                if (Customer::where('phone', $phone)->exists()) {
                    $skippedCount++;
                    $errors[] = "Row #".($index + 1).": Mobile {$phone} is already registered.";
                    continue;
                }

                $type = strtolower($row['customer_type'] ?? 'b2c') === 'b2b' ? Customer::TYPE_B2B : Customer::TYPE_B2C;
                $isB2b = $type === Customer::TYPE_B2B;

                $firstName = trim($row['first_name'] ?? '');
                $lastName = trim($row['last_name'] ?? '');
                $name = trim($row['name'] ?? '');
                if ($name === '') {
                    $name = trim("{$firstName} {$lastName}");
                }
                if ($name === '' && $isB2b) {
                    $name = trim($row['business_name'] ?? $row['company_name'] ?? 'B2B Client');
                }

                $classification = (isset($row['classification']) && strtolower($row['classification']) === 'existing')
                    || (isset($row['stage']) && str_contains(strtolower($row['stage']), 'old'))
                    ? Customer::CLASSIFICATION_EXISTING
                    : Customer::CLASSIFICATION_NEW;

                Customer::create([
                    'customer_type' => $type,
                    'classification' => $classification,
                    'first_name' => $firstName !== '' ? $firstName : null,
                    'last_name' => $lastName !== '' ? $lastName : null,
                    'name' => $name !== '' ? $name : 'Customer',
                    'business_name' => $isB2b ? ($row['business_name'] ?? $row['company_name'] ?? $name) : null,
                    'phone' => $phone,
                    'alternate_phone' => ! empty($row['alternate_phone']) ? preg_replace('/\D/', '', (string) $row['alternate_phone']) : null,
                    'email' => ! empty($row['email']) ? trim($row['email']) : null,
                    'gst_number' => ! empty($row['gst_number']) ? strtoupper(trim($row['gst_number'])) : (! empty($row['gstin']) ? strtoupper(trim($row['gstin'])) : null),
                    'pan_number' => ! empty($row['pan_number']) ? strtoupper(trim($row['pan_number'])) : (! empty($row['pan']) ? strtoupper(trim($row['pan'])) : null),
                    'address_line1' => $row['address'] ?? $row['address_line1'] ?? $row['street_address'] ?? null,
                    'city' => $row['city'] ?? 'New Delhi',
                    'state' => $row['state'] ?? 'Delhi NCR',
                    'country' => $row['country'] ?? 'India',
                    'pincode' => ! empty($row['pincode']) ? preg_replace('/\D/', '', (string) $row['pincode']) : (! empty($row['pin']) ? preg_replace('/\D/', '', (string) $row['pin']) : null),
                    'segment' => $row['segment'] ?? ($isB2b ? 'Wholesale' : 'Retail'),
                    'stage' => $row['stage'] ?? ($classification === Customer::CLASSIFICATION_EXISTING ? 'Old / Existing Customer' : 'New Customer'),
                    'source' => $row['source'] ?? 'Bulk Import',
                    'notes' => $row['notes'] ?? 'Imported via bulk customer upload.',
                    'created_by_user_id' => $actor->id,
                    'is_active' => true,
                ]);

                $importedCount++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Bulk import error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to process bulk import. Please check file formatting and try again.',
            ], 500);
        }

        return response()->json([
            'message' => "Bulk import completed. {$importedCount} customer(s) imported successfully.",
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk delete selected customer records (Strict Admin Only).
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorizeAdminOnly($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = $validated['ids'];
        $eligibleCustomers = Customer::whereIn('id', $ids)
            ->where(function ($query) {
                $query->where('orders_count', 0)->orWhereNull('orders_count');
            })
            ->get();

        $deletedCount = 0;
        foreach ($eligibleCustomers as $c) {
            $c->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "{$deletedCount} customer record(s) deleted successfully.",
            'deleted_count' => $deletedCount,
            'skipped_with_orders' => count($ids) - $deletedCount,
        ]);
    }

    /**
     * Checks if staff user has access to view or create customers.
     */
    private function authorizeCustomerAccess(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (
                $user->canManageRoles() ||
                in_array('customers.view', $user->permissionKeys(), true)
            ),
            403,
            'You do not have permission to access customers.',
        );

        return $user;
    }

    /**
     * Enforces strict role restriction: ONLY Admin and Super Admin can edit or delete.
     */
    private function authorizeAdminOnly(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->canManageRoles(),
            403,
            'Only administrators and super administrators can edit or delete saved customer records.',
        );

        return $user;
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        $isB2b = $request->input('customer_type') === Customer::TYPE_B2B;

        $rules = [
            'customer_type' => ['required', Rule::in([Customer::TYPE_B2C, Customer::TYPE_B2B])],
            'classification' => ['nullable', Rule::in([Customer::CLASSIFICATION_NEW, Customer::CLASSIFICATION_EXISTING])],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->ignore($customer?->id)->whereNull('deleted_at'),
            ],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'business_name' => [$isB2b ? 'required' : 'nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'pan_number' => ['nullable', 'string', 'max:15'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'string', 'max:50'],
            'company_type' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'annual_revenue' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:60'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'zip_code' => ['nullable', 'string', 'max:12'],
            'segment' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:50'],
            'primary_manager' => ['nullable', 'string', 'max:100'],
            'customer_priority' => ['nullable', 'string', 'max:30'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:50'],
            'preferred_currency' => ['nullable', 'string', 'max:10'],
            'preferred_language' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];

        $validated = $request->validate($rules);

        // Address mapping
        if (isset($validated['street_address']) && ! isset($validated['address_line1'])) {
            $validated['address_line1'] = $validated['street_address'];
        }
        if (isset($validated['zip_code']) && ! isset($validated['pincode'])) {
            $validated['pincode'] = $validated['zip_code'];
        }

        // Name composition
        $firstName = trim($validated['first_name'] ?? '');
        $lastName = trim($validated['last_name'] ?? '');
        if ($firstName !== '' || $lastName !== '') {
            $validated['name'] = trim("{$firstName} {$lastName}");
        } elseif (empty($validated['name'])) {
            $validated['name'] = $validated['business_name'] ?? 'Customer';
        }

        // Classification / Stage mapping (New vs Old)
        if (! empty($validated['stage'])) {
            if (stripos($validated['stage'], 'old') !== false || stripos($validated['stage'], 'exist') !== false || stripos($validated['stage'], 'repeat') !== false) {
                $validated['classification'] = Customer::CLASSIFICATION_EXISTING;
            } else {
                $validated['classification'] = Customer::CLASSIFICATION_NEW;
            }
        } elseif (empty($validated['classification'])) {
            $validated['classification'] = Customer::CLASSIFICATION_NEW;
        }

        if (! empty($validated['gst_number'])) {
            $validated['gst_number'] = Str::upper(trim($validated['gst_number']));
        }

        if (! empty($validated['pan_number'])) {
            $validated['pan_number'] = Str::upper(trim($validated['pan_number']));
        }

        if ($validated['customer_type'] === Customer::TYPE_B2C) {
            $validated['business_name'] = null;
            $validated['legal_name'] = null;
            $validated['trade_name'] = null;
            $validated['registration_number'] = null;
            $validated['industry'] = null;
            $validated['company_size'] = null;
            $validated['company_type'] = null;
            $validated['website'] = null;
            $validated['annual_revenue'] = null;
            $validated['job_title'] = null;
            $validated['department'] = null;
            $validated['billing_email'] = null;
            $validated['payment_terms'] = null;
        }

        return $validated;
    }
}
