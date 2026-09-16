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
            
            // 🔥 CLEAR: margin_percentage yahan se hamesha ke liye hata diya
            'rm_cost' => $product->rm_cost,
            'grinding_cost' => $product->grinding_cost,
            'yield_percentage' => $product->yield_percentage,
            'keywords' => $product->keywords,
            'description' => $product->description,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()->with('category');

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

        // 🔥 CLEAR: margin_percentage hata diya
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'rm_cost' => 'required|numeric|min:0', 
            'grinding_cost' => 'nullable|numeric|min:0',
            'yield_percentage' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'keywords' => 'nullable|string|max:255',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $this->formatProduct($product->load('category')),
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
            'data' => $this->formatProduct($product->load('category'))
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        // 🔥 CLEAR: margin_percentage hata diya
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'rm_cost' => 'required|numeric|min:0',
            'grinding_cost' => 'nullable|numeric|min:0',
            'yield_percentage' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'keywords' => 'nullable|string|max:255',
        ]);

        $product->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => $this->formatProduct($product->load('category')),
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
            ->with('category')
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
            ->with('category')
            ->get()
            ->map(fn($product) => $this->formatProduct($product));

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }
}