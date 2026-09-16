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

                if ($marginPercentage < 0 || $marginPercentage > 100) {
                    $this->results['errors'][] =
                        "Row {$rowNum}: margin_percentage must be between 0 and 100.";
                    continue;
                }
            }

            // Create new category or update existing one
            Category::updateOrCreate(
                [
                    'name' => $name,
                ],
                [
                    'description' => $description,
                    'margin_percentage' => $marginPercentage,
                ]
            );

            $this->results['inserted']++;
        }
    }
}
