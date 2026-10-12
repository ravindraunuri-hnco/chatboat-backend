<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ADMIN PANEL SIDE — "Request New Product" requests submitted by chatbot (export) users.
 * Uses the "product_requests" permission (set in Role & Permissions): read = list, write = add, update = cancel.
 */
class ProductRequestController extends Controller
{
    private function format(ProductRequest $r): array
    {
        return [
            'id' => $r->id,
            'product_name' => $r->product_name,
            'category_id' => $r->category_id,
            'category_name' => $r->category ? $r->category->name : $r->custom_category_name,
            // false => this category is not in the categories table yet
            'category_exists' => $this->resolveCategory($r) !== null,
            'deadline' => $r->deadline->format('Y-m-d'),
            'status' => $r->status,
            'requested_by' => $r->chatUser ? [
                'id' => $r->chatUser->id,
                'name' => $r->chatUser->name,
                'email' => $r->chatUser->email,
                'user_type' => $r->chatUser->user_type,
            ] : null,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    /**
     * Look up the request's category in the categories table.
     * Use category_id if present; for an "Other" request, match by name (an admin may have created the category in the meantime).
     */
    private function resolveCategory(ProductRequest $r): ?Category
    {
        if ($r->category) {
            return $r->category;
        }
        if ($r->custom_category_name) {
            return Category::where('name', $r->custom_category_name)->first();
        }
        return null;
    }

    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('product_requests', 'read')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        ProductRequest::purgeProcessed();

        $query = ProductRequest::with(['category:id,name', 'chatUser:id,name,email,user_type'])
            ->orderBy('id', 'desc');

        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()->map(fn ($r) => $this->format($r)),
        ]);
    }

    /**
     * Sidebar notification badge: how many requests are pending.
     */
    public function pendingCount(Request $request)
    {
        if (!$request->user()->hasPermission('product_requests', 'read')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'count' => ProductRequest::where('status', 'pending')->count(),
        ]);
    }

    /**
     * ADD: put the request into the products table along with the purchase price (rm_cost).
     */
    public function add(Request $request, $id)
    {
        if (!$request->user()->hasPermission('product_requests', 'write')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            // the admin can edit the product name in the row
            'product_name' => 'required|string|max:255',
            'rm_cost' => 'required|numeric|min:1',
        ], [
            'product_name.required' => 'Product name is required.',
            'rm_cost.required' => 'Purchase Price (RM) is required.',
            'rm_cost.numeric' => 'Purchase Price (RM) must be a number.',
            'rm_cost.min' => 'Purchase Price (RM) must be at least 1.',
        ]);

        $adminId = $request->user()->id;

        return DB::transaction(function () use ($id, $validated, $adminId) {
            // lock: so the product is not created twice if two admins click add at the same time
            $productRequest = ProductRequest::with(['category', 'chatUser'])->lockForUpdate()->findOrFail($id);

            if ($productRequest->status !== 'pending') {
                return response()->json(['message' => 'This request has already been processed.'], 422);
            }

            // For old requests (from when "Other" was allowed), if the category still cannot be found, the product will not be added
            $category = $this->resolveCategory($productRequest);
            if (!$category) {
                return response()->json([
                    'message' => 'Category "' . $productRequest->custom_category_name . '" is not available in the system.',
                ], 422);
            }

            $productName = trim($validated['product_name']);

            // Slug check: "Organic Amla" / "organic-amla" are also treated as duplicates
            if (Product::nameTaken($productName)) {
                return response()->json([
                    'message' => 'This product already exists.',
                ], 422);
            }

            Product::create([
                'name' => $productName,
                'category_id' => $category->id,
                'rm_cost' => $validated['rm_cost'],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            $productRequest->update([
                'product_name' => $productName,
                'status' => 'approved',
                'processed_at' => now(),
                'category_id' => $category->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product added successfully.',
                'data' => $this->format($productRequest->fresh(['category', 'chatUser'])),
            ]);
        });
    }

    /**
     * CANCEL: reject the request (the user side will show "rejected").
     */
    public function cancel(Request $request, $id)
    {
        if (!$request->user()->hasPermission('product_requests', 'update')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return DB::transaction(function () use ($id) {
            $productRequest = ProductRequest::lockForUpdate()->findOrFail($id);

            if ($productRequest->status !== 'pending') {
                return response()->json(['message' => 'This request has already been processed.'], 422);
            }

            $productRequest->update(['status' => 'rejected', 'processed_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Request cancelled.',
            ]);
        });
    }
}
