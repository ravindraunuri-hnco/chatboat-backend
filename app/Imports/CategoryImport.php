<?php
// FILE: app/Imports/CategoryImport.php
// CREATE this file — new folder app/Imports/ bhi banao

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
                'description' => $description,
            ];

            // Margin sirf tab update hoga jab sheet me value ho (existing margin overwrite/null na ho)
            if ($marginPercentage !== null) {
                $attributes['margin_percentage'] = $marginPercentage;
            }

            // Optional yield percentage (sirf tab update hoga jab sheet me value ho,
            // taaki column na hone par existing yield overwrite na ho)
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

            // Optional grinding cost (khali = 0 on create; existing me sirf tab update jab sheet me value ho)
            if (isset($row['grinding_cost']) && $row['grinding_cost'] !== '') {

                if (!is_numeric($row['grinding_cost']) || (float) $row['grinding_cost'] < 0) {
                    $this->results['errors'][] =
                        "Row {$rowNum}: grinding_cost must be a number (0 or more).";
                    continue;
                }

                $attributes['grinding_cost'] = (float) $row['grinding_cost'];
            }

            // Create new category or update existing one
            Category::updateOrCreate(
                [
                    'name' => $name,
                ],
                $attributes
            );

            $this->results['inserted']++;
        }
    }
}
