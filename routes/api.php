<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\MarginController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ExchangeRateController; // 🔥 Controller import kiya

Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

Route::middleware('auth:sanctum')->group(function () {

    /*
    |----------------------
    | AUTH
    |----------------------
    */
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    /*
    |----------------------
    | PRODUCT FEATURES
    |----------------------
    */
    Route::get('/products/category/{category}', [ProductController::class, 'getByCategory']);
    Route::get('/search', [ProductController::class, 'search']);

    /*
    |----------------------
    | CATEGORY (FOR PRODUCT UI)
    |----------------------
    */
    Route::get('/product-categories/for-product', [CategoryController::class, 'indexForProduct']);

    /*
    |----------------------
    | CRUD RESOURCES
    |----------------------
    */
    Route::apiResource('product-categories', CategoryController::class)
        ->parameters(['product-categories' => 'category']);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('margins', MarginController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);

    /*
    |----------------------
    | CHAT USERS (ADMIN PANEL SIDE)
    |----------------------
    */
    Route::get('chat-users', [ChatbotController::class, 'getAdminChatUsers']);
    Route::put('chat-users/{id}', [ChatbotController::class, 'updateAdminChatUser']);
    Route::delete('chat-users/{id}', [ChatbotController::class, 'deleteAdminChatUser']);

    /*
    |----------------------
    | EXCHANGE RATE (SUPER ADMIN ONLY)
    |----------------------
    */
    // 🔥 Naye routes add kiye Exchange Rate ke liye
    Route::get('exchange-rate', [ExchangeRateController::class, 'show']);
    Route::put('exchange-rate', [ExchangeRateController::class, 'update']);

    /*
    |----------------------
    | IMPORT
    |----------------------
    */
    Route::post('/product-categories/import', [ImportController::class, 'importCategories']);
    Route::post('/products/import', [ImportController::class, 'importProducts']);

    /*
    |----------------------
    | PERMISSIONS
    |----------------------
    */
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::post('permissions', [PermissionController::class, 'store']);
    Route::get('permissions/{permission}', [PermissionController::class, 'show']);
    Route::put('permissions/{permission}', [PermissionController::class, 'update']);
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy']);

    /*
    |----------------------
    | ROLE PERMISSIONS
    |----------------------
    */
    Route::get('role-permissions', [RolePermissionController::class, 'index']);
    Route::post('role-permissions', [RolePermissionController::class, 'store']);
    Route::get('role-permissions/{id}', [RolePermissionController::class, 'show']);
    Route::put('role-permissions/{id}', [RolePermissionController::class, 'update']);
    Route::delete('role-permissions/{id}', [RolePermissionController::class, 'delete']);
});

/*
|--------------------------------------------------------------------------
| CHATBOT ROUTES (USER SIDE)
|--------------------------------------------------------------------------
*/
Route::prefix('chatbot')->group(function () {
    // Public Routes (Bina login ke chalenge)
    Route::post('register', [ChatbotController::class, 'register']);
    Route::post('login', [ChatbotController::class, 'login']);

    // Protected Routes (Sirf logged-in chatbot users ke liye)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('chat', [ChatbotController::class, 'chat']);
        Route::get('chat', [ChatbotController::class, 'getChat']);
        Route::delete('chat', [ChatbotController::class, 'deleteChat']);
    });
});