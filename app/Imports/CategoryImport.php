<?php
// FILE: app/Imports/CategoryImport.php
// CREATE this file — also create the new folder app/Imports/

namespace App\Imports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CategoryImport implements ToCollection, WithHeadingRow
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

            // Required category name
            $name = trim($row['category_name'] ?? '');

            if (empty($name)) {
                continue;
            }

            // Optional description
            $description = trim($row['description'] ?? '') ?: null;

            // Optional margin percentage
            $marginPercentage = null;

            if (isset($row['margin_percentage']) && $row['margin_percentage'] !== '') {

                if (!is_numeric($row['margin_percentage'])) {
                    $this->results['errors'][] =
                        "Row {$rowNum}: margin_percentage must be numeric.";
                    continue;
                }

                $marginPercentage = (float) $row['margin_percentage'];

                if ($marginPercentage < 1 || $marginPercentage > 100) {
                    $this->results['errors'][] =
                        "Row {$rowNum}: margin_percentage must be between 1 and 100.";
                    continue;
                }
            }

            $attributes = [
                'name'        => $name,
                'description' => $description,
                'updated_by'  => $this->userId,
            ];

            // Margin is updated only when the sheet has a value (so the existing margin is not overwritten/nulled)
            if ($marginPercentage !== null) {
                $attributes['margin_percentage'] = $marginPercentage;
            }

            // Optional yield percentage (updated only when the sheet has a value,
            // so the existing yield is not overwritten if the column is absent)
            if (isset($row['yield_percentage']) && $row['yield_percentage'] !== '') {

                if (!is_numeric($row['yield_percentage'])) {
                    $this->results['errors'][] =
                        "Row {$rowNum}: yield_percentage must be numeric.";
                    continue;
                }

                $yieldPercentage = (float) $row['yield_percentage'];

                if ($yieldPercentage < 1 || $yieldPercentage > 100) {
                    $this->results['errors'][] =
                        "Row {$rowNum}: yield_percentage must be between 1 and 100.";
                    continue;
                }

                $attributes['yield_percentage'] = $yieldPercentage;
            }

            // Optional grinding cost (empty = 0 on create; for existing rows, updated only when the sheet has a value)
            if (isset($row['grinding_cost']) && $row['grinding_cost'] !== '') {

                if (!is_numeric($row['grinding_cost']) || (float) $row['grinding_cost'] < 0) {
                    $this->results['errors'][] =
                        "Row {$rowNum}: grinding_cost must be a number (0 or more).";
                    continue;
                }

                $attributes['grinding_cost'] = (float) $row['grinding_cost'];
            }

            // Create new category or update existing one
            // Look up by slug so "Amla Powder" / "amla-powder" is not created again
            $existing = Category::where('slug', Category::slugFor($name))->first();

            if ($existing) {
                // created_by stays the same, only updated_by changes
                $existing->update($attributes);
            } else {
                Category::create($attributes + ['created_by' => $this->userId]);
            }

            $this->results['inserted']++;
        }
    }
}
