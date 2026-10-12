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

    // Logged-in admin's id (for created_by / updated_by)
    public function __construct(private ?int $userId = null)
    {
    }

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

            if ($rmCost === null || $rmCost === '' || !is_numeric($rmCost) || (float)$rmCost < 1) {
                $this->results['errors'][] =
                    "Row {$rowNum}: rm_cost required (number, minimum 1) for '{$productName}'";
                continue;
            }

            // Category
            $categoryId = null;
            $categoryName = trim($row['category_name'] ?? '');

            if (!empty($categoryName)) {
                // Automatically create category if it does not exist
                // Look up by slug so "Amla Powder" / "amla-powder" is not created again
                $category = Category::where('slug', Category::slugFor($categoryName))->first()
                    ?? Category::create([
                        'name'        => $categoryName,
                        'description' => null,
                        'created_by'  => $this->userId,
                        'updated_by'  => $this->userId,
                    ]);

                $categoryId = $category->id;
            }

            // Optional Fields
            // yield_percentage and grinding_cost now live at category level (categories table)
            $keywords = trim($row['keywords'] ?? '') ?: null;

            $description = trim($row['description'] ?? '') ?: null;

            // Insert new OR Update existing product
            // the margin_percentage column does not exist in the db, so it was removed from here too
            $attributes = [
                'name'        => $productName,
                'category_id' => $categoryId,
                'rm_cost'     => (float)$rmCost,
                'keywords'    => $keywords,
                'description' => $description,
                'updated_by'  => $this->userId,
            ];

            $existing = Product::where('slug', Product::slugFor($productName))->first();

            if ($existing) {
                // created_by stays the same, only updated_by changes
                $existing->update($attributes);
            } else {
                Product::create($attributes + ['created_by' => $this->userId]);
            }

            $this->results['inserted']++;
        }
    }
}