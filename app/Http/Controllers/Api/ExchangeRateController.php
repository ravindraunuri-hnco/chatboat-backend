<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    // 🔥 STRICT CHECK: Only Super Admin
    private function isSuperAdmin($user)
    {
        return $user->role && $user->role->name === 'super_admin';
    }

    public function show(Request $request)
    {
        if (!$this->isSuperAdmin($request->user())) {
            return response()->json(['message' => 'Unauthorized. Super Admin access only.'], 403);
        }

        $exchangeRate = ExchangeRate::firstOrCreate(
            ['currency_code' => 'USD'],
            ['rate' => 84.00]
        );

        return response()->json([
            'status' => 'success',
            'data' => $exchangeRate
        ]);
    }

    public function update(Request $request)
    {
        if (!$this->isSuperAdmin($request->user())) {
            return response()->json(['message' => 'Unauthorized. Super Admin access only.'], 403);
        }

        $request->validate([
            'rate' => 'required|numeric|min:1'
        ]);

        $exchangeRate = ExchangeRate::firstOrCreate(
            ['currency_code' => 'USD'],
            ['rate' => 84.00]
        );
        
        $exchangeRate->update(['rate' => $request->rate]);

        return response()->json([
            'status' => 'success',
            'message' => 'Exchange rate updated successfully',
            'data' => $exchangeRate
        ]);
    }
}