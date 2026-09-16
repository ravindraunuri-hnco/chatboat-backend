<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductImport implements ToCollection, WithHeadingRow
{
    public array $results = [
        'inserted' => 0,
        'skipped'  => 0,
        'errors'   => [],
    ];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {

            $rowNum = $index + 2;

            // Product Name (Required)
            $productName = trim($row['product_name'] ?? '');

            if (empty($productName)) {
                continue;
            }

            // Purchase Price / RM Cost (Required)
            $rmCost = $row['rm_cost'] ?? null;

            if ($rmCost === null || $rmCost === '' || !is_numeric($rmCost) || (float)$rmCost < 0) {
                $this->results['errors'][] =
                    "Row {$rowNum}: rm_cost required/invalid for '{$productName}'";
                continue;
            }

            // Category
            $categoryId = null;
            $categoryName = trim($row['category_name'] ?? '');

            if (!empty($categoryName)) {
                // Automatically create category if it does not exist
                $category = Category::firstOrCreate(
                    [
                        'name' => $categoryName,
                    ],
                    [
                        'description' => null,
                        // Margin fallback logic yahan se permanently hata diya gaya hai
                    ]
                );

                $categoryId = $category->id;
            }

            // Optional Fields
            $grindingCost = null;

            if (isset($row['grinding_cost']) && is_numeric($row['grinding_cost'])) {
                $grindingCost = (float)$row['grinding_cost'];
            }

            $yieldPercentage = null;

            if (isset($row['yield_percentage']) && is_numeric($row['yield_percentage'])) {
                $yieldPercentage = (float)$row['yield_percentage'];
            }

            $keywords = trim($row['keywords'] ?? '') ?: null;

            $description = trim($row['description'] ?? '') ?: null;

            // Insert new OR Update existing product
            // margin_percentage column db me exist nahi karta, isliye yahan se bhi hata diya
            Product::updateOrCreate(
                [
                    'name' => $productName,
                ],
                [
                    'category_id'       => $categoryId,
                    'rm_cost'           => (float)$rmCost,
                    'grinding_cost'     => $grindingCost,
                    'yield_percentage'  => $yieldPercentage,
                    'keywords'          => $keywords,
                    'description'       => $description,
                ]
            );

            $this->results['inserted']++;
        }
    }
}