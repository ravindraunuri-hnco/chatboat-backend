<?php
// FILE: app/Http/Controllers/Api/ImportController.php
// CREATE this new file

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Imports\CategoryImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ImportController extends Controller
{
    use AuthorizesRequests;

    /**
     * POST /api/product-categories/import
     * Excel columns: category_name | description (optional) | margin_percentage (optional)
     */
    // app/Http/Controllers/Api/ImportController.php

    public function importCategories(Request $request)
    {
        // ✅ REPLACE $this->authorize() with this:
        if (!$request->user()->hasPermission('categories', 'create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new CategoryImport();
            Excel::import($import, $request->file('file'));

            return response()->json([
                'status'  => 'success',
                'message' => 'Categories import complete.',
                'data'    => $import->results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function importProducts(Request $request)
    {
        // ✅ REPLACE $this->authorize() with this:
        if (!$request->user()->hasPermission('products', 'create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new ProductImport();
            Excel::import($import, $request->file('file'));

            return response()->json([
                'status'  => 'success',
                'message' => 'Products import complete.',
                'data'    => $import->results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
