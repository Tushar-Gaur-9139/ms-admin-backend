<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplianceType;
use App\Models\Brand;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class AdminInventoryController extends Controller
{
    use SavesCatalogImages;

    // ─── Authorization ─────────────────────────────────────────────────────────

    private function authorizeRead(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $user->is_active,
            401,
            'Unauthenticated.'
        );
        abort_unless(
            $user->isSuperAdmin()
                || $user->role === 'admin'
                || in_array('inventory.view', $user->permissionKeys(), true)
                || in_array('products.view', $user->permissionKeys(), true),
            403,
            'You do not have permission to view inventory.'
        );

        return $user;
    }

    private function authorizeWrite(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $user->is_active,
            401,
            'Unauthenticated.'
        );
        abort_unless(
            $user->isSuperAdmin() || $user->role === 'admin',
            403,
            'Only administrators can manage inventory.'
        );

        return $user;
    }

    // ─── Listing ───────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $query = SparePart::with([
            'brand:id,name,short_code,logo_url',
            'applianceType:id,name,icon',
            'partCategory:id,name',
            'compatibleModels:id,model_number',
        ]);

        // Filters
        if ($request->filled('brand_id')) {
            $query->where('brand_id', (int) $request->query('brand_id'));
        }
        if ($request->filled('appliance_type_id')) {
            $query->where('appliance_type_id', (int) $request->query('appliance_type_id'));
        }
        if ($request->filled('qc_status')) {
            $query->where('qc_status', $request->query('qc_status'));
        }
        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'min_stock_alert')->where('stock_quantity', '>', 0);
        }
        if ($request->boolean('out_of_stock')) {
            $query->where('stock_quantity', 0);
        }
        if ($request->filled('search')) {
            $term = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('rack_location', 'like', $term);
            });
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        // Stats for banner badges (computed before pagination)
        $statsQuery = clone $query;
        $totalParts = $statsQuery->count();
        $lowStockCount = (clone $statsQuery)->whereColumn('stock_quantity', '<=', 'min_stock_alert')->where('stock_quantity', '>', 0)->count();
        $outOfStock = (clone $statsQuery)->where('stock_quantity', 0)->count();

        // Pagination
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $parts = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'stats' => [
                'total_parts' => $totalParts,
                'low_stock_count' => $lowStockCount,
                'out_of_stock' => $outOfStock,
            ],
            'parts' => $parts->items(),
            'current_page' => $parts->currentPage(),
            'last_page' => $parts->lastPage(),
            'total' => $parts->total(),
            'per_page' => $parts->perPage(),
            // Dropdown options for Add/Edit form
            'brands' => Brand::active()->with('applianceTypes:id,name')->orderBy('name')->get(['id', 'name', 'short_code']),
            'appliance_types' => ApplianceType::active()->orderBy('sort_order')->get(['id', 'name', 'icon', 'image_url']),
            'part_categories' => PartCategory::active()->orderBy('name')->get(['id', 'appliance_type_id', 'name']),
        ]);
    }

    public function storeApplianceType(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);
        if ($request->filled('existing_product_id')) {
            $data = $request->validate([
                'existing_product_id' => ['required', 'integer', Rule::exists('appliance_types', 'id')->where('is_active', true)],
                'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where('is_active', true)],
            ]);
            $product = DB::transaction(function () use ($data): ApplianceType {
                $brand = Brand::active()->lockForUpdate()->findOrFail($data['brand_id']);
                $product = ApplianceType::active()->findOrFail($data['existing_product_id']);
                $brand->applianceTypes()->syncWithoutDetaching([$product->id]);

                return $product;
            });

            return response()->json(['appliance_type' => $product]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:appliance_types,name'],
            'brand_id' => ['sometimes', 'required', 'integer', Rule::exists('brands', 'id')->where('is_active', true)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $request->merge(['slug' => Str::slug($data['name'])]);
        $data += $request->validate(['slug' => ['required', 'max:120', 'unique:appliance_types,slug']]);

        $product = $this->saveCatalogImage($data, function (array $fields): ApplianceType {
            $brandId = $fields['brand_id'] ?? null;
            unset($fields['brand_id']);
            $product = ApplianceType::create($fields);

            if ($brandId !== null) {
                Brand::active()->findOrFail($brandId)->applianceTypes()->attach($product->id);
            }

            return $product;
        });

        return response()->json(['appliance_type' => $product], 201);
    }

    public function storePartCategory(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);
        $data = $request->validate([
            'appliance_type_id' => ['required', 'integer', 'exists:appliance_types,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('part_categories')->where('appliance_type_id', $request->integer('appliance_type_id'))],
        ]);
        $request->merge(['slug' => $data['appliance_type_id'].'-'.Str::slug($data['name'])]);
        $data += $request->validate(['slug' => ['required', 'max:120', 'unique:part_categories,slug']]);

        return response()->json(['part_category' => PartCategory::create($data)], 201);
    }

    public function updateApplianceType(Request $request, ApplianceType $applianceType): JsonResponse
    {
        $this->authorizeWrite($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('appliance_types')->ignore($applianceType)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $this->saveCatalogImage($data, function (array $fields) use ($applianceType): ApplianceType {
            $applianceType->update($fields);

            return $applianceType;
        });

        return response()->json(['appliance_type' => $applianceType]);
    }

    public function destroyApplianceType(Request $request, ApplianceType $applianceType): JsonResponse
    {
        $this->authorizeWrite($request);

        DB::transaction(function () use ($applianceType): void {
            $product = ApplianceType::query()->lockForUpdate()->findOrFail($applianceType->id);
            abort_if(
                $product->brands()->exists() || $product->applianceModels()->exists()
                    || $product->spareParts()->exists() || $product->partCategories()->exists(),
                422,
                'This product is in use. Remove its company mappings, models, spare parts and categories before deleting it.'
            );
            $product->delete();
        });

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    // ─── Create ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'appliance_type_id' => ['required', 'integer', 'exists:appliance_types,id'],
            'part_category_id' => ['nullable', 'integer', $this->partCategoryRule($request->integer('appliance_type_id'))],
            'description' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'b2c_price' => ['required', 'numeric', 'min:0'],
            'b2b_price' => ['required', 'numeric', 'min:0'],
            'mrp_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0'],
            'rack_location' => ['nullable', 'string', 'max:50'],
            'qc_status' => ['nullable', Rule::in(['original_oem', 'tested_qc_pass', 'universal_fit'])],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'specs_json' => ['nullable', 'array'],
            'compatible_model_ids' => ['nullable', 'array'],
            'compatible_model_ids.*' => ['integer', $this->compatibleModelRule($request->integer('brand_id'), $request->integer('appliance_type_id'))],
            'serial_notes' => ['nullable', 'string', 'max:300'],
        ]);

        // Auto-generate SKU
        $brand = Brand::findOrFail($data['brand_id']);
        $sku = $brand->generateNextSku();

        $part = SparePart::create([
            'sku' => $sku,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.strtolower($sku),
            'brand_id' => $data['brand_id'],
            'appliance_type_id' => $data['appliance_type_id'],
            'part_category_id' => $data['part_category_id'] ?? null,
            'description' => $data['description'] ?? null,
            'purchase_price' => $data['purchase_price'] ?? 0,
            'b2c_price' => $data['b2c_price'],
            'b2b_price' => $data['b2b_price'],
            'mrp_price' => $data['mrp_price'] ?? null,
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'min_stock_alert' => $data['min_stock_alert'] ?? 5,
            'rack_location' => $data['rack_location'] ?? null,
            'qc_status' => $data['qc_status'] ?? SparePart::QC_ORIGINAL,
            'is_active' => $data['is_active'] ?? true,
            'is_featured' => $data['is_featured'] ?? false,
            'image_url' => $data['image_url'] ?? null,
            'specs_json' => $data['specs_json'] ?? null,
        ]);

        // Attach compatible models
        if (! empty($data['compatible_model_ids'])) {
            $syncData = [];
            foreach ($data['compatible_model_ids'] as $modelId) {
                $syncData[$modelId] = ['serial_notes' => $data['serial_notes'] ?? null];
            }
            $part->compatibleModels()->sync($syncData);
        }

        $part->load(['brand:id,name,short_code', 'applianceType:id,name,icon', 'partCategory:id,name', 'compatibleModels:id,model_number,model_name']);

        return response()->json([
            'message' => 'Spare part created successfully.',
            'part' => $this->formatPart($part),
        ], 201);
    }

    // ─── Show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, SparePart $part): JsonResponse
    {
        $this->authorizeRead($request);

        $part->load([
            'brand:id,name,short_code,logo_url',
            'applianceType:id,name,icon',
            'partCategory:id,name',
            'compatibleModels:id,model_number,model_name,series_year,serial_prefix',
        ]);

        return response()->json(['part' => $this->formatPart($part)]);
    }

    // ─── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, SparePart $part): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'brand_id' => ['sometimes', 'integer', 'exists:brands,id'],
            'appliance_type_id' => ['sometimes', 'integer', 'exists:appliance_types,id'],
            'part_category_id' => ['nullable', 'integer', $this->partCategoryRule($request->integer('appliance_type_id', $part->appliance_type_id))],
            'description' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'b2c_price' => ['sometimes', 'numeric', 'min:0'],
            'b2b_price' => ['sometimes', 'numeric', 'min:0'],
            'mrp_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0'],
            'rack_location' => ['nullable', 'string', 'max:50'],
            'qc_status' => ['nullable', Rule::in(['original_oem', 'tested_qc_pass', 'universal_fit'])],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'specs_json' => ['nullable', 'array'],
            'compatible_model_ids' => ['nullable', 'array'],
            'compatible_model_ids.*' => ['integer', $this->compatibleModelRule(
                $request->integer('brand_id', $part->brand_id),
                $request->integer('appliance_type_id', $part->appliance_type_id),
            )],
            'serial_notes' => ['nullable', 'string', 'max:300'],
        ]);

        $updatePayload = collect($data)->except(['compatible_model_ids', 'serial_notes'])->toArray();
        if (isset($updatePayload['name'])) {
            $updatePayload['slug'] = Str::slug($updatePayload['name']).'-'.strtolower($part->sku);
        }
        $part->update($updatePayload);

        if (array_key_exists('compatible_model_ids', $data)) {
            $syncData = [];
            foreach (($data['compatible_model_ids'] ?? []) as $modelId) {
                $syncData[$modelId] = array_key_exists('serial_notes', $data) ? ['serial_notes' => $data['serial_notes']] : [];
            }
            $part->compatibleModels()->sync($syncData);
        }

        $part->load(['brand:id,name,short_code', 'applianceType:id,name,icon', 'partCategory:id,name', 'compatibleModels:id,model_number,model_name']);

        return response()->json([
            'message' => 'Spare part updated successfully.',
            'part' => $this->formatPart($part),
        ]);
    }

    // ─── Quick stock adjustment ────────────────────────────────────────────────

    public function updateStock(Request $request, SparePart $part): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'adjustment_type' => ['nullable', Rule::in(['replenishment', 'sale_adjustment', 'correction'])],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $part->update(['stock_quantity' => $data['stock_quantity']]);

        return response()->json([
            'message' => 'Stock updated successfully.',
            'sku' => $part->sku,
            'name' => $part->name,
            'stock_quantity' => $part->stock_quantity,
            'is_low_stock' => $part->isLowStock(),
            'is_out_of_stock' => $part->isOutOfStock(),
        ]);
    }

    // ─── Delete ────────────────────────────────────────────────────────────────

    public function destroy(Request $request, SparePart $part): JsonResponse
    {
        $this->authorizeWrite($request);

        $part->compatibleModels()->detach();
        $part->delete();

        return response()->json(['message' => 'Spare part deleted successfully.']);
    }

    // ─── Format ────────────────────────────────────────────────────────────────

    private function formatPart(SparePart $p): array
    {
        return [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'slug' => $p->slug,
            'brand' => $p->brand ? ['id' => $p->brand->id, 'name' => $p->brand->name, 'short_code' => $p->brand->short_code] : null,
            'appliance_type' => $p->applianceType ? ['id' => $p->applianceType->id, 'name' => $p->applianceType->name, 'icon' => $p->applianceType->icon] : null,
            'part_category' => $p->partCategory ? ['id' => $p->partCategory->id, 'name' => $p->partCategory->name] : null,
            'description' => $p->description,
            'purchase_price' => (float) $p->purchase_price,
            'b2c_price' => (float) $p->b2c_price,
            'b2b_price' => (float) $p->b2b_price,
            'mrp_price' => $p->mrp_price ? (float) $p->mrp_price : null,
            'stock_quantity' => $p->stock_quantity,
            'min_stock_alert' => $p->min_stock_alert,
            'rack_location' => $p->rack_location,
            'qc_status' => $p->qc_status,
            'qc_label' => $p->qcLabel(),
            'is_active' => $p->is_active,
            'is_featured' => $p->is_featured,
            'is_low_stock' => $p->isLowStock(),
            'is_out_of_stock' => $p->isOutOfStock(),
            'image_url' => $p->image_url,
            'specs_json' => $p->specs_json,
            'compatible_models' => $p->compatibleModels ?? [],
            'created_at' => $p->created_at?->toDateString(),
            'updated_at' => $p->updated_at?->toDateString(),
        ];
    }

    private function compatibleModelRule(int $brandId, int $applianceTypeId): Exists
    {
        return Rule::exists('appliance_models', 'id')
            ->where('brand_id', $brandId)
            ->where('appliance_type_id', $applianceTypeId);
    }

    private function partCategoryRule(int $applianceTypeId): Exists
    {
        return Rule::exists('part_categories', 'id')
            ->where(fn (Builder $query) => $query->whereNull('appliance_type_id')->orWhere('appliance_type_id', $applianceTypeId));
    }
}
