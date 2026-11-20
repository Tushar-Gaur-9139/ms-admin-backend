<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplianceModel;
use App\Models\ApplianceType;
use App\Models\Brand;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminBrandController extends Controller
{
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
                || in_array('products.view', $user->permissionKeys(), true)
                || in_array('inventory.view', $user->permissionKeys(), true),
            403,
            'You do not have permission to view brands.'
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
            'Only administrators can manage brands.'
        );

        return $user;
    }

    // ─── Brands ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $brands = Brand::with(['applianceTypes:id,name,slug,icon'])
            ->withCount(['applianceModels', 'spareParts'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $b) => $this->formatBrand($b));

        $applianceTypes = ApplianceType::active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon']);

        return response()->json([
            'brands' => $brands,
            'appliance_types' => $applianceTypes,
            'total' => $brands->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:brands,name'],
            'short_code' => ['required', 'string', 'max:10', 'unique:brands,short_code'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'appliance_type_ids' => ['nullable', 'array'],
            'appliance_type_ids.*' => ['integer', 'exists:appliance_types,id'],
        ]);

        $brand = $this->saveWithLogo($data, function (array $data): Brand {
            $brand = Brand::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'short_code' => strtoupper($data['short_code']),
                'logo_url' => $data['logo_url'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            if (! empty($data['appliance_type_ids'])) {
                $brand->applianceTypes()->sync($data['appliance_type_ids']);
            }

            return $brand;
        });

        $brand->load('applianceTypes:id,name,slug,icon');
        $brand->loadCount(['applianceModels', 'spareParts']);

        return response()->json([
            'message' => 'Brand created successfully.',
            'brand' => $this->formatBrand($brand),
        ], 201);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($brand->id)],
            'short_code' => ['sometimes', 'string', 'max:10', Rule::unique('brands', 'short_code')->ignore($brand->id)],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'appliance_type_ids' => ['nullable', 'array'],
            'appliance_type_ids.*' => ['integer', 'exists:appliance_types,id'],
        ]);

        $this->saveWithLogo($data, function (array $data) use ($brand): Brand {
            $updatePayload = collect($data)->except('appliance_type_ids')->toArray();
            if (isset($updatePayload['name'])) {
                $updatePayload['slug'] = Str::slug($updatePayload['name']);
            }
            if (isset($updatePayload['short_code'])) {
                $updatePayload['short_code'] = strtoupper($updatePayload['short_code']);
            }

            $brand->update($updatePayload);

            if (array_key_exists('appliance_type_ids', $data)) {
                $brand->applianceTypes()->sync($data['appliance_type_ids'] ?? []);
            }

            return $brand;
        });

        $brand->load('applianceTypes:id,name,slug,icon');
        $brand->loadCount(['applianceModels', 'spareParts']);

        return response()->json([
            'message' => 'Brand updated successfully.',
            'brand' => $this->formatBrand($brand),
        ]);
    }

    public function showLogo(string $filename): StreamedResponse
    {
        $disk = Storage::disk('public');
        $path = 'brand-logos/'.$filename;
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param Closure(array): Brand $save */
    private function saveWithLogo(array $data, Closure $save): Brand
    {
        $logo = $data['logo'] ?? null;
        unset($data['logo']);
        $path = null;

        if ($logo) {
            $path = $logo->store('brand-logos', 'public');
            abort_if($path === false, 500, 'The company logo could not be saved.');
            $data['logo_url'] = route('brand-logos.show', ['filename' => basename($path)], absolute: false);
        }

        try {
            return DB::transaction(fn (): Brand => $save($data));
        } catch (Throwable $failure) {
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }

            throw $failure;
        }
    }

    public function destroy(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeWrite($request);

        if ($brand->spareParts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete brand: it has spare parts linked to it. Remove or reassign spare parts first.',
            ], 422);
        }

        // Remove models and pivot records first
        $brand->applianceModels()->delete();
        $brand->applianceTypes()->detach();
        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully.']);
    }

    // ─── Appliance Models ──────────────────────────────────────────────────────

    public function models(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeRead($request);

        $query = ApplianceModel::where('brand_id', $brand->id)
            ->with('applianceType:id,name,slug,icon');

        if ($request->filled('appliance_type_id')) {
            $query->where('appliance_type_id', (int) $request->query('appliance_type_id'));
        }

        $models = $query->orderBy('appliance_type_id')->orderBy('model_number')->get()
            ->map(fn (ApplianceModel $m) => $this->formatModel($m));

        return response()->json([
            'brand' => ['id' => $brand->id, 'name' => $brand->name, 'short_code' => $brand->short_code],
            'models' => $models,
            'total' => $models->count(),
        ]);
    }

    public function storeModel(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'appliance_type_id' => ['required', 'integer', 'exists:appliance_types,id'],
            'model_number' => ['required', 'string', 'max:100'],
            'model_name' => ['nullable', 'string', 'max:200'],
            'series_year' => ['nullable', 'string', 'max:20'],
            'serial_prefix' => ['nullable', 'string', 'max:30'],
            'serial_range_start' => ['nullable', 'string', 'max:50'],
            'serial_range_end' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        // Check uniqueness within the brand
        $exists = ApplianceModel::where('brand_id', $brand->id)
            ->where('model_number', $data['model_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Model number already exists for this brand.',
                'errors' => ['model_number' => ['This model number is already registered under '.$brand->name]],
            ], 422);
        }

        $model = ApplianceModel::create(array_merge($data, ['brand_id' => $brand->id]));
        $model->load('applianceType:id,name,slug,icon');

        return response()->json([
            'message' => 'Model added successfully.',
            'model' => $this->formatModel($model),
        ], 201);
    }

    public function updateModel(Request $request, ApplianceModel $model): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'appliance_type_id' => ['sometimes', 'integer', 'exists:appliance_types,id'],
            'model_number' => ['sometimes', 'string', 'max:100'],
            'model_name' => ['nullable', 'string', 'max:200'],
            'series_year' => ['nullable', 'string', 'max:20'],
            'serial_prefix' => ['nullable', 'string', 'max:30'],
            'serial_range_start' => ['nullable', 'string', 'max:50'],
            'serial_range_end' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['model_number']) && $data['model_number'] !== $model->model_number) {
            $exists = ApplianceModel::where('brand_id', $model->brand_id)
                ->where('model_number', $data['model_number'])
                ->where('id', '!=', $model->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Model number already exists for this brand.',
                    'errors' => ['model_number' => ['This model number is already registered under this brand.']],
                ], 422);
            }
        }

        $model->update($data);
        $model->load('applianceType:id,name,slug,icon');

        return response()->json([
            'message' => 'Model updated successfully.',
            'model' => $this->formatModel($model),
        ]);
    }

    public function destroyModel(Request $request, ApplianceModel $model): JsonResponse
    {
        $this->authorizeWrite($request);

        if ($model->spareParts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete model: spare parts are linked to it. Remove compatibility entries first.',
            ], 422);
        }

        $model->delete();

        return response()->json(['message' => 'Model deleted successfully.']);
    }

    // ─── Format helpers ────────────────────────────────────────────────────────

    private function formatBrand(Brand $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'short_code' => $b->short_code,
            'logo_url' => $b->logo_url,
            'description' => $b->description,
            'is_active' => $b->is_active,
            'sort_order' => $b->sort_order,
            'appliance_types' => $b->applianceTypes ?? [],
            'appliance_models_count' => $b->appliance_models_count ?? 0,
            'spare_parts_count' => $b->spare_parts_count ?? 0,
            'created_at' => $b->created_at?->toDateString(),
        ];
    }

    private function formatModel(ApplianceModel $m): array
    {
        return [
            'id' => $m->id,
            'brand_id' => $m->brand_id,
            'appliance_type_id' => $m->appliance_type_id,
            'appliance_type' => $m->applianceType ? ['id' => $m->applianceType->id, 'name' => $m->applianceType->name, 'icon' => $m->applianceType->icon] : null,
            'model_number' => $m->model_number,
            'model_name' => $m->model_name,
            'series_year' => $m->series_year,
            'serial_prefix' => $m->serial_prefix,
            'serial_range_start' => $m->serial_range_start,
            'serial_range_end' => $m->serial_range_end,
            'is_active' => $m->is_active,
            'created_at' => $m->created_at?->toDateString(),
        ];
    }
}
