<?php
// FILE: app/Http/Controllers/Api/CategoryController.php
// ✅ FIX: indexForProduct() nayi method add ki
//         Yeh method sirf 'product' read permission check karta hai
//         Taaki Products page pe category dropdown kaam kare bina
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
     * Display a listing of the resource.
     * (Requires 'category' read permission - Categories page ke liye)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::all();
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * ✅ NAYI METHOD: Products page ke liye categories list
     * Yeh endpoint 'product' read permission check karta hai — 'category' nahi
     * Isliye agar kisi role ko sirf product read dia ho, woh bhi
     * product add/edit form mein categories dekh sakta hai
     */
    public function indexForProduct(Request $request)
    {
        // Product ki permission check karo — category ki nahi
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
            'margin_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $category
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
            'data' => $category
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);
        // dd($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'margin_percentage' => 'nullable|numeric|min:1|max:100',
            'export_margin' => 'nullable|numeric|min:1|max:100',
        ]);

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // 1. Pehle category ko find karo
        $category = \App\Models\Category::findOrFail($id);

        // 2. Category delete hone se pehle, us category_id wale saare products delete kar do
        \App\Models\Product::where('category_id', $category->id)->delete();

        // 3. Ab main category ko delete kar do
        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Category aur uske saare products successfully delete ho gaye hain.'
        ]);
    }
}