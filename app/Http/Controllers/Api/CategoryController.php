<?php
// FILE: app/Http/Controllers/Api/CategoryController.php
// ✅ FIX: added the new indexForProduct() method
//         This method checks only the 'product' read permission
//         So the category dropdown on the Products page works without
//         'category' read permission ke

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    /**
     * Category plus the name of the admin who created/updated it (for the admin Categories page).
     */
    private function formatCategory(Category $category): array
    {
        $category->loadMissing(['creator:id,name', 'updater:id,name']);

        $data = $category->toArray();
        unset($data['creator'], $data['updater']);

        return $data + [
            'created_by_name' => $category->creator?->name,
            'updated_by_name' => $category->updater?->name,
        ];
    }

    /**
     * Display a listing of the resource.
     * (Requires 'category' read permission - for the Categories page)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::with(['creator:id,name', 'updater:id,name'])->get()
            ->map(fn ($c) => $this->formatCategory($c));
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * ✅ NEW METHOD: categories list for the Products page
     * This endpoint checks the 'product' read permission — not 'category'
     * So a role that has only product read access can also
     * see the categories in the product add/edit form
     */
    public function indexForProduct(Request $request)
    {
        // Check the product permission — not the category one
        if (!$request->user()->hasPermission('products', 'read')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $categories = Category::all();
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Category::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'margin_percentage' => 'nullable|numeric|min:1|max:100',
            'export_margin' => 'nullable|numeric|min:1|max:100',
            'yield_percentage' => 'nullable|numeric|min:1|max:100',
            'grinding_cost' => 'nullable|numeric|min:0',
        ]);

        Category::assertNameAvailable($validated['name'], null, 'category');

        // Empty processing cost = 0 (no processing cost)
        $validated['grinding_cost'] = $validated['grinding_cost'] ?? 0;

        // Logged-in admin's id: new category -> both created_by and updated_by
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $category = Category::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $this->formatCategory($category)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $this->authorize('view', $category);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatCategory($category)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'margin_percentage' => 'nullable|numeric|min:1|max:100',
            'export_margin' => 'nullable|numeric|min:1|max:100',
            'yield_percentage' => 'nullable|numeric|min:1|max:100',
            'grinding_cost' => 'nullable|numeric|min:0',
        ]);

        // If the processing cost is sent empty, store 0 (the column is NOT NULL)
        if (array_key_exists('grinding_cost', $validated)) {
            $validated['grinding_cost'] = $validated['grinding_cost'] ?? 0;
        }

        Category::assertNameAvailable($validated['name'], $category->id, 'category');

        // Logged-in admin's id: only updated_by changes, created_by stays the same
        $validated['updated_by'] = $request->user()->id;

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $this->formatCategory($category)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // 1. First find the category
        $category = \App\Models\Category::findOrFail($id);

        // Permission check ('categories' delete) — there was no check here before
        $this->authorize('delete', $category);

        // 2 + 3. All products of the category and then the category — together (atomic)
        \Illuminate\Support\Facades\DB::transaction(function () use ($category) {
            \App\Models\Product::where('category_id', $category->id)->delete();
            $category->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'The category and all its products were deleted successfully.'
        ]);
    }
}