<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProductController extends Controller
{
    use AuthorizesRequests;

    /**
     * Shape a product model into the response array used by index/search/getByCategory.
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'product_name' => $product->name,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            
            // 🔥 CLEAR: margin_percentage has been removed from here permanently
            'rm_cost' => $product->rm_cost,
            'keywords' => $product->keywords,
            'description' => $product->description,
            'slug' => $product->slug,
            'created_by' => $product->created_by,
            'created_by_name' => $product->creator?->name,
            'updated_by' => $product->updated_by,
            'updated_by_name' => $product->updater?->name,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()->with(['category', 'creator:id,name', 'updater:id,name']);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');

            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $searchTerm . '%')
                    ->orWhere('keywords', 'like', '%' . $searchTerm . '%');
            });
        }

        $products = $query->get()->map(fn($product) => $this->formatProduct($product));

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        // 🔥 CLEAR: margin_percentage removed
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'rm_cost' => 'required|numeric|min:1',
            'description' => 'nullable|string',
            'keywords' => 'nullable|string|max:255',
        ]);

        Product::assertNameAvailable($validated['name'], null, 'product');

        // Logged-in admin's id: new product -> both created_by and updated_by
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $product = Product::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $this->formatProduct($product->load(['category', 'creator:id,name', 'updater:id,name'])),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $this->authorize('view', $product);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatProduct($product->load(['category', 'creator:id,name', 'updater:id,name']))
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        // 🔥 CLEAR: margin_percentage removed
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'rm_cost' => 'required|numeric|min:1',
            'description' => 'nullable|string',
            'keywords' => 'nullable|string|max:255',
        ]);

        Product::assertNameAvailable($validated['name'], $product->id, 'product');

        // Logged-in admin's id: only updated_by changes, created_by stays the same
        $validated['updated_by'] = $request->user()->id;

        $product->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => $this->formatProduct($product->load(['category', 'creator:id,name', 'updater:id,name'])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Get products by category.
     */
    public function getByCategory($categoryId)
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::where('category_id', $categoryId)
            ->with(['category', 'creator:id,name', 'updater:id,name'])
            ->get()
            ->map(fn($product) => $this->formatProduct($product));

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Search products.
     */
    public function search(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $request->validate([
            'query' => 'required|string',
        ]);

        $searchTerm = $request->query('query');

        $products = Product::where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', '%' . $searchTerm . '%')
                ->orWhere('description', 'like', '%' . $searchTerm . '%')
                ->orWhere('keywords', 'like', '%' . $searchTerm . '%');
        })
            ->with(['category', 'creator:id,name', 'updater:id,name'])
            ->get()
            ->map(fn($product) => $this->formatProduct($product));

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }
}