<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDepartment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    private const LEGACY_CATEGORY_MAP = [
        'bebidas' => ['Refrescos y Bebidas', 'Refrescos'],
        'botanas' => ['Alimentos', 'Botanas'],
        'lacteos' => ['Lacteos y Frescos', 'Cremeria'],
        'quimicos' => ['Limpieza', 'Limpiadores'],
        'vinos' => ['Vinos y Licores', 'Vinos'],
    ];

    public function run(): void
    {
        $now = now();
        $departments = ProductDepartment::query()
            ->whereIn('name', collect(ProductDepartmentSeeder::CATALOG)->pluck('name')->all())
            ->pluck('id', 'name');
        $categories = [];

        foreach (ProductDepartmentSeeder::CATALOG as $departmentIndex => $departmentData) {
            $departmentId = $departments[$departmentData['name']] ?? null;

            if (!$departmentId) {
                continue;
            }

            foreach ($departmentData['categories'] as $categoryIndex => $categoryName) {
                $categories[] = [
                    'product_department_id' => $departmentId,
                    'name' => $categoryName,
                    'sort_order' => ($departmentIndex * 100) + $categoryIndex + 1,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (DB::table('categories')->doesntExist() && $categories) {
            DB::table('categories')->insert($categories);
        } else {
            foreach (ProductDepartmentSeeder::CATALOG as $departmentIndex => $departmentData) {
                $departmentId = $departments[$departmentData['name']] ?? null;

                if (!$departmentId) {
                    continue;
                }

                $department = new ProductDepartment(['name' => $departmentData['name']]);
                $department->id = $departmentId;

                foreach ($departmentData['categories'] as $categoryIndex => $categoryName) {
                    $this->syncCategory(
                        department: $department,
                        name: $categoryName,
                        sortOrder: ($departmentIndex * 100) + $categoryIndex + 1,
                    );
                }
            }
        }

        $this->migrateLegacyCategories();
    }

    public static function resolveLegacyCategory(string $categoryName): ?Category
    {
        $normalizedCategory = self::normalize($categoryName);
        $mappedCategory = self::LEGACY_CATEGORY_MAP[$normalizedCategory] ?? null;

        if (!$mappedCategory) {
            return Category::query()
                ->get()
                ->first(fn (Category $category) => self::normalize($category->name) === $normalizedCategory);
        }

        [$departmentName, $resolvedCategoryName] = $mappedCategory;

        $department = ProductDepartment::where('name', $departmentName)->first();

        if (!$department) {
            return null;
        }

        return Category::firstOrCreate(
            [
                'product_department_id' => $department->id,
                'name' => $resolvedCategoryName,
            ],
            [
                'active' => true,
            ]
        );
    }

    private function syncCategory(ProductDepartment $department, string $name, int $sortOrder): Category
    {
        $category = Category::query()
            ->where('product_department_id', $department->id)
            ->where('name', $name)
            ->first();

        if (!$category) {
            $category = Category::query()
                ->whereNull('product_department_id')
                ->where('name', $name)
                ->first() ?? new Category(['name' => $name]);
        }

        $category->fill([
            'product_department_id' => $department->id,
            'name' => $name,
            'sort_order' => $sortOrder,
            'active' => true,
        ]);

        $category->save();

        return $category;
    }

    private function migrateLegacyCategories(): void
    {
        Category::query()
            ->get(['id', 'name', 'product_department_id'])
            ->each(function (Category $legacyCategory) {
                if (!array_key_exists(self::normalize($legacyCategory->name), self::LEGACY_CATEGORY_MAP)) {
                    return;
                }

                $targetCategory = self::resolveLegacyCategory($legacyCategory->name);

                if (!$targetCategory || $targetCategory->id === $legacyCategory->id) {
                    return;
                }

                Product::where('category_id', $legacyCategory->id)
                    ->update(['category_id' => $targetCategory->id]);

                if (!$legacyCategory->products()->exists() && !$legacyCategory->subcategories()->exists()) {
                    $legacyCategory->delete();
                }
            });
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(
            [
                "\xc3\x83\xc2\xa1", "\xc3\xa1",
                "\xc3\x83\xc2\xa9", "\xc3\xa9",
                "\xc3\x83\xc2\xad", "\xc3\xad",
                "\xc3\x83\xc2\xb3", "\xc3\xb3",
                "\xc3\x83\xc2\xba", "\xc3\xba",
            ],
            ['a', 'a', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u'],
            $value
        );

        return Str::of($value)->lower()->toString();
    }
}
