<?php

namespace Database\Seeders;

use App\Models\ApplianceModel;
use App\Models\ApplianceType;
use App\Models\Brand;
use App\Models\PartCategory;
use App\Models\SparePart;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Appliance Types ───────────────────────────────────────────────
        $applianceTypes = [
            ['name' => 'Air Conditioner',  'slug' => 'air-conditioner',  'icon' => '❄️',  'sort_order' => 1],
            ['name' => 'Washing Machine',  'slug' => 'washing-machine',  'icon' => '🫧',  'sort_order' => 2],
            ['name' => 'Refrigerator',     'slug' => 'refrigerator',     'icon' => '🧊',  'sort_order' => 3],
            ['name' => 'Microwave Oven',   'slug' => 'microwave-oven',   'icon' => '📡',  'sort_order' => 4],
        ];

        $typeMap = [];
        foreach ($applianceTypes as $at) {
            $typeMap[$at['slug']] = ApplianceType::firstOrCreate(['slug' => $at['slug']], $at);
        }

        $ac = $typeMap['air-conditioner'];
        $wm = $typeMap['washing-machine'];
        $ref = $typeMap['refrigerator'];
        $mwo = $typeMap['microwave-oven'];

        // ── 2. Brands ────────────────────────────────────────────────────────
        $brandsData = [
            [
                'name' => 'Samsung',    'slug' => 'samsung',    'short_code' => 'SAM',
                'description' => 'South Korean multinational electronics corporation.',
                'sort_order' => 1, 'appliances' => [$ac->id, $wm->id, $ref->id],
            ],
            [
                'name' => 'LG',         'slug' => 'lg',         'short_code' => 'LG',
                'description' => 'South Korean multinational electronics company.',
                'sort_order' => 2, 'appliances' => [$ac->id, $wm->id, $ref->id],
            ],
            [
                'name' => 'Daikin',     'slug' => 'daikin',     'short_code' => 'DAI',
                'description' => 'Japanese multinational air conditioning manufacturer.',
                'sort_order' => 3, 'appliances' => [$ac->id],
            ],
            [
                'name' => 'Voltas',     'slug' => 'voltas',     'short_code' => 'VOL',
                'description' => 'Indian air conditioning and engineering company (Tata Group).',
                'sort_order' => 4, 'appliances' => [$ac->id, $ref->id],
            ],
            [
                'name' => 'Godrej',     'slug' => 'godrej',     'short_code' => 'GOD',
                'description' => 'Indian multinational conglomerate company.',
                'sort_order' => 5, 'appliances' => [$ref->id, $wm->id],
            ],
            [
                'name' => 'Whirlpool',  'slug' => 'whirlpool',  'short_code' => 'WHL',
                'description' => 'American multinational manufacturer of home appliances.',
                'sort_order' => 6, 'appliances' => [$wm->id, $ref->id],
            ],
            [
                'name' => 'Universal',  'slug' => 'universal',  'short_code' => 'UNI',
                'description' => 'Multi-brand compatible universal spare parts.',
                'sort_order' => 7, 'appliances' => [$ac->id, $wm->id, $ref->id],
            ],
        ];

        $brandMap = [];
        foreach ($brandsData as $bd) {
            $applianceIds = $bd['appliances'];
            unset($bd['appliances']);
            $brand = Brand::firstOrCreate(['slug' => $bd['slug']], array_merge($bd, ['is_active' => true]));
            $brand->applianceTypes()->sync($applianceIds);
            $brandMap[$bd['slug']] = $brand;
        }

        // ── 3. Sample Models ─────────────────────────────────────────────────
        $modelsData = [
            // Samsung AC models
            ['brand' => 'samsung', 'appliance_type_id' => $ac->id,  'model_number' => 'AR12NV', 'model_name' => 'WindFree 12000BTU', 'series_year' => '2022-2024'],
            ['brand' => 'samsung', 'appliance_type_id' => $ac->id,  'model_number' => 'AR18AY', 'model_name' => 'Digital Inverter 18000BTU', 'series_year' => '2023'],
            // Samsung WM models
            ['brand' => 'samsung', 'appliance_type_id' => $wm->id,  'model_number' => 'WW80T', 'model_name' => 'EcoBubble Front Load 8kg', 'series_year' => '2022'],
            // LG AC models
            ['brand' => 'lg', 'appliance_type_id' => $ac->id,  'model_number' => 'LS-Q18JNXA', 'model_name' => 'Dual Inverter AC 1.5T', 'series_year' => '2023-2024'],
            ['brand' => 'lg', 'appliance_type_id' => $ac->id,  'model_number' => 'LS-Q12YNZA', 'model_name' => 'Smart Inverter 1T', 'series_year' => '2022'],
            // Daikin AC models
            ['brand' => 'daikin', 'appliance_type_id' => $ac->id, 'model_number' => 'FTKF35TV', 'model_name' => 'PM 2.5 Filter AC 1T', 'series_year' => '2022-2023'],
            ['brand' => 'daikin', 'appliance_type_id' => $ac->id, 'model_number' => 'FTKG50TV', 'model_name' => 'Streamer AC 1.5T', 'series_year' => '2023'],
            // Voltas AC models
            ['brand' => 'voltas', 'appliance_type_id' => $ac->id, 'model_number' => '183V-JZJ', 'model_name' => 'Adjustable Inverter 1.5T', 'series_year' => '2023'],
            // Universal
            ['brand' => 'universal', 'appliance_type_id' => $ac->id, 'model_number' => 'UNI-AC-PCB-01', 'model_name' => 'Universal AC PCB (Inverter)', 'series_year' => '2020-2026'],
        ];

        $modelMap = [];
        foreach ($modelsData as $md) {
            $brand = $brandMap[$md['brand']];
            unset($md['brand']);
            $model = ApplianceModel::firstOrCreate(
                ['brand_id' => $brand->id, 'model_number' => $md['model_number']],
                array_merge($md, ['brand_id' => $brand->id, 'is_active' => true])
            );
            $modelMap[$md['model_number']] = $model;
        }

        // ── 4. Part Categories ───────────────────────────────────────────────
        $categoriesData = [
            ['appliance_type_id' => $ac->id,  'name' => 'PCB & Control Boards',  'slug' => 'pcb-control-boards'],
            ['appliance_type_id' => $ac->id,  'name' => 'Compressor Parts',      'slug' => 'compressor-parts'],
            ['appliance_type_id' => $ac->id,  'name' => 'Fan Motors',            'slug' => 'fan-motors'],
            ['appliance_type_id' => $ac->id,  'name' => 'Sensors & Thermistors', 'slug' => 'sensors-thermistors'],
            ['appliance_type_id' => $wm->id,  'name' => 'Drum & Bearings',       'slug' => 'drum-bearings'],
            ['appliance_type_id' => $wm->id,  'name' => 'Motors & Pumps',        'slug' => 'motors-pumps'],
            ['appliance_type_id' => $ref->id, 'name' => 'Compressor & Gas',      'slug' => 'compressor-gas'],
            ['appliance_type_id' => $ref->id, 'name' => 'Thermostats & Sensors', 'slug' => 'thermostats-sensors'],
            ['appliance_type_id' => null,     'name' => 'Capacitors & Relays',   'slug' => 'capacitors-relays'],
            ['appliance_type_id' => null,     'name' => 'Wiring & Connectors',   'slug' => 'wiring-connectors'],
        ];

        $catMap = [];
        foreach ($categoriesData as $cat) {
            $catMap[$cat['slug']] = PartCategory::firstOrCreate(['slug' => $cat['slug']], array_merge($cat, ['is_active' => true]));
        }

        // ── 5. Sample Spare Parts ─────────────────────────────────────────────
        $partsData = [
            [
                'name' => 'Samsung Inverter AC PCB (12000-18000 BTU)',
                'brand' => 'samsung', 'appliance_type_id' => $ac->id,
                'part_category_slug' => 'pcb-control-boards',
                'purchase_price' => 850, 'b2c_price' => 1800, 'b2b_price' => 1350, 'mrp_price' => 2200,
                'stock_quantity' => 24, 'min_stock_alert' => 5, 'rack_location' => 'R1-A1',
                'qc_status' => 'original_oem', 'is_featured' => true,
                'description' => 'Original OEM PCB for Samsung AR12NV and AR18AY inverter AC series.',
                'compatible_models' => ['AR12NV', 'AR18AY'],
            ],
            [
                'name' => 'LG Dual Inverter AC Fan Motor',
                'brand' => 'lg', 'appliance_type_id' => $ac->id,
                'part_category_slug' => 'fan-motors',
                'purchase_price' => 1100, 'b2c_price' => 2200, 'b2b_price' => 1700, 'mrp_price' => 2600,
                'stock_quantity' => 12, 'min_stock_alert' => 4, 'rack_location' => 'R1-B2',
                'qc_status' => 'original_oem', 'is_featured' => false,
                'description' => 'Original OEM indoor blower fan motor for LG dual inverter AC.',
                'compatible_models' => ['LS-Q18JNXA', 'LS-Q12YNZA'],
            ],
            [
                'name' => 'Universal AC Run Capacitor 25µF 450V',
                'brand' => 'universal', 'appliance_type_id' => $ac->id,
                'part_category_slug' => 'capacitors-relays',
                'purchase_price' => 95, 'b2c_price' => 220, 'b2b_price' => 160, 'mrp_price' => 280,
                'stock_quantity' => 3, 'min_stock_alert' => 10, 'rack_location' => 'R3-C5',
                'qc_status' => 'universal_fit', 'is_featured' => false,
                'description' => 'Multi-brand compatible run capacitor for split AC compressors.',
                'compatible_models' => ['UNI-AC-PCB-01'],
            ],
            [
                'name' => 'Daikin AC Temperature Sensor NTC Thermistor',
                'brand' => 'daikin', 'appliance_type_id' => $ac->id,
                'part_category_slug' => 'sensors-thermistors',
                'purchase_price' => 180, 'b2c_price' => 450, 'b2b_price' => 340, 'mrp_price' => 550,
                'stock_quantity' => 18, 'min_stock_alert' => 5, 'rack_location' => 'R2-A3',
                'qc_status' => 'original_oem', 'is_featured' => false,
                'description' => 'OEM NTC thermistor for Daikin FTKF and FTKG series indoor units.',
                'compatible_models' => ['FTKF35TV', 'FTKG50TV'],
            ],
            [
                'name' => 'Samsung Top Load WM Pulsator (8kg)',
                'brand' => 'samsung', 'appliance_type_id' => $wm->id,
                'part_category_slug' => 'drum-bearings',
                'purchase_price' => 420, 'b2c_price' => 950, 'b2b_price' => 720, 'mrp_price' => 1150,
                'stock_quantity' => 0, 'min_stock_alert' => 3, 'rack_location' => 'R4-D1',
                'qc_status' => 'original_oem', 'is_featured' => false,
                'description' => 'OEM pulsator disc for Samsung EcoBubble 8kg washing machine.',
                'compatible_models' => ['WW80T'],
            ],
        ];

        foreach ($partsData as $pd) {
            $brand = $brandMap[$pd['brand']];
            $sku   = $brand->generateNextSku();
            $compatibleModelNos = $pd['compatible_models'] ?? [];
            unset($pd['brand'], $pd['compatible_models']);

            $partCategorySlug = $pd['part_category_slug'] ?? null;
            unset($pd['part_category_slug']);

            $part = SparePart::firstOrCreate(
                ['name' => $pd['name'], 'brand_id' => $brand->id],
                array_merge($pd, [
                    'sku' => $sku,
                    'slug' => \Illuminate\Support\Str::slug($pd['name']) . '-' . strtolower($sku),
                    'brand_id' => $brand->id,
                    'is_active' => true,
                    'part_category_id' => $partCategorySlug ? ($catMap[$partCategorySlug]->id ?? null) : null,
                ])
            );

            // Attach compatible models
            foreach ($compatibleModelNos as $modelNo) {
                if (isset($modelMap[$modelNo])) {
                    $part->compatibleModels()->syncWithoutDetaching([$modelMap[$modelNo]->id]);
                }
            }
        }

        $this->command->info('✅ Brands, Appliance Types, Models, Categories & Sample Spare Parts seeded.');
    }
}

