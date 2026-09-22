<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatUser;
use App\Models\ChatHistory;
use App\Models\Product;
use App\Models\Category;
use App\Models\ExchangeRate; // 🔥 Yahan ExchangeRate model import kiya hai
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ChatbotController extends Controller
{
    // ===================================
    // ADMIN PANEL METHODS FOR CHAT USERS
    // ===================================
    public function getAdminChatUsers(Request $request)
    {
        if (!$request->user()->hasPermission('users', 'read')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json([
            'status' => 'success',
            'data' => ChatUser::orderBy('id', 'desc')->get()
        ]);
    }

    public function updateAdminChatUser(Request $request, $id)
    {
        if (!$request->user()->hasPermission('users', 'update')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $request->validate(['user_type' => 'required|in:normal,export']);
        $chatUser = ChatUser::findOrFail($id);
        $chatUser->update(['user_type' => $request->user_type]);
        return response()->json([
            'status' => 'success',
            'message' => 'Chat User type updated successfully',
            'data' => $chatUser
        ]);
    }

    public function deleteAdminChatUser(Request $request, $id)
    {
        if (!$request->user()->hasPermission('users', 'delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        ChatUser::findOrFail($id)->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Chat User deleted successfully'
        ]);
    }

    // ===================================
    // AUTHENTICATION LOGIC (CHATBOT)
    // ===================================
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:chat_users,email',
            'password' => 'required|string|min:6'
        ]);

        ChatUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'normal'
        ]);

        return response()->json([
            "status" => "success", 
            "message" => "Registration successful! Ab aap login kar sakte hain."
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = ChatUser::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(["status" => "error", "message" => "Email ya Password galat hai!"], 401);
        }

        $token = $user->createToken('chatbot-token')->plainTextToken;

        return response()->json([
            "status" => "success",
            "token" => $token,
            "user" => ["name" => $user->name, "email" => $user->email, "user_type" => $user->user_type]
        ]);
    }

    // ===================================
    // MANUAL CURRENCY READER (From DB)
    // ===================================
    private function getUsdExchangeRate()
    {
        // 🔥 Cache hata kar ab direct Professional ExchangeRate Table se rate uthayega
        $exchange = ExchangeRate::where('currency_code', 'USD')->first();
        return $exchange ? (float) $exchange->rate : 84.00; 
    }

    // ===================================
    // EXPORT MARGIN & USD CALCULATION ENGINE
    // ===================================
    private function calculateFinalPrice($product, $category, $user = null)
    {
        try {
            $baseCost = $product->rm_cost + $product->grinding_cost;
            if ($product->yield_percentage <= 0) return 0;

            $finalCost = $baseCost / ($product->yield_percentage / 100);
            
            // 1. Normal Sales Price
            $marginPct = $category ? ($category->margin_percentage ?? 0) : 0;
            $marginVal = $finalCost * ($marginPct / 100);
            $salesPrice = $finalCost + $marginVal;

            // 2. Export Calculation (Margin on Margin + USD Conversion)
            if ($user && $user->user_type === 'export') {
                $exportMarginPct = $category->export_margin ?? 0;
                if ($exportMarginPct > 0) {
                    $exportMarginVal = $salesPrice * ($exportMarginPct / 100);
                    $salesPrice = $salesPrice + $exportMarginVal;
                }
                
                // Convert INR to USD (Division)
                $usdRate = $this->getUsdExchangeRate();
                $salesPrice = $salesPrice / $usdRate;
            }

            return round($salesPrice, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDetailedCalculation($product, $category, $user = null)
    {
        try {
            $baseCost = $product->rm_cost + $product->grinding_cost;
            $yieldPct = $product->yield_percentage;
            $finalCost = $yieldPct > 0 ? $baseCost / ($yieldPct / 100) : 0;
            
            $marginPct = $category ? ($category->margin_percentage ?? 0) : 0;
            $marginVal = $finalCost * ($marginPct / 100);
            $salesPrice = $finalCost + $marginVal;

            $exportMarginApplied = false;
            $currencySymbol = '₹';
            $currencyCode = 'INR';

            if ($user && $user->user_type === 'export') {
                $exportMarginPct = $category->export_margin ?? 0;
                if ($exportMarginPct > 0) {
                    $exportMarginVal = $salesPrice * ($exportMarginPct / 100);
                    $salesPrice = $salesPrice + $exportMarginVal;
                    $exportMarginApplied = true;
                }
                
                // Convert INR to USD (Division)
                $usdRate = $this->getUsdExchangeRate();
                $salesPrice = $salesPrice / $usdRate;
                
                $currencySymbol = '$';
                $currencyCode = 'USD';
            }

            return [
                "product_name" => $product->name,
                "category_name" => $category->name ?? 'None',
                "rm_cost" => round($product->rm_cost, 2),
                "grinding_cost" => round($product->grinding_cost, 2),
                "yield_percentage" => round($yieldPct, 2),
                "margin_percentage" => round($marginPct, 2),
                "base_cost" => round($baseCost, 2),
                "final_cost" => round($finalCost, 2),
                "margin_value" => round($marginVal, 2),
                "sales_price" => round($salesPrice, 2),
                "export_margin_applied" => $exportMarginApplied,
                "currency_symbol" => $currencySymbol, // 🔥 Symbol for frontend
                "currency_code" => $currencyCode
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    // ===================================
    // MAIN CHAT LOGIC
    // ===================================
    public function chat(Request $request)
    {
        $user = $request->user();
        $message = strtolower(trim($request->message ?? ''));
        $sessionId = $request->session_id ?? (string) now()->timestamp;

        $currencySymbol = ($user && $user->user_type === 'export') ? '$' : '₹';

        if (in_array($message, ['hi', 'hello', 'hey', 'namaste', 'help'])) {
            $responsePayload = [
                "response" => "👋 Hello! Main HNCO ka AI Assistant hu.\nAap mujhse kisi bhi Product ya Category ka price pooch sakte hain."
            ];
            
            ChatHistory::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $request->message ?? '',
                'role' => 'user',
                'response' => json_encode($responsePayload),
                'created_at' => now(),
            ]);
            
            return response()->json($responsePayload);
        }

        $searchStr = str_replace(["price of", "cost of", "rate of", "tell me"], "", $message);
        $searchStr = trim($searchStr);
        
        $responseText = "❌ Product or Category not found";
        $calculationDetails = null;
        $categoryCalculations = null;

        $products = Product::with('category')->get();
        $matchedProduct = null;

        foreach ($products as $p) {
            $searchKeys = array_map('trim', explode(',', strtolower($p->keywords . ',' . $p->name)));
            foreach ($searchKeys as $key) {
                if (!empty($key) && str_contains($searchStr, $key)) {
                    $matchedProduct = $p;
                    break 2;
                }
            }
        }

        if ($matchedProduct && $matchedProduct->category) {
            $price = $this->calculateFinalPrice($matchedProduct, $matchedProduct->category, $user);
            $responseText = "✅ {$matchedProduct->name} Price: {$currencySymbol}{$price}/KG";
            $calculationDetails = $this->getDetailedCalculation($matchedProduct, $matchedProduct->category, $user);
        } else {
            $categories = Category::with('products')->get();
            foreach ($categories as $c) {
                if (str_contains($searchStr, strtolower($c->name))) {
                    $responseText = "🟢 {$c->name} Products\n\n";
                    $categoryCalculations = [];
                    foreach ($c->products as $p) {
                        $price = $this->calculateFinalPrice($p, $c, $user);
                        $responseText .= "✅ {$p->name} : {$currencySymbol}{$price}/KG\n";
                        $calc = $this->getDetailedCalculation($p, $c, $user);
                        if ($calc) {
                            $categoryCalculations[] = $calc;
                        }
                    }
                    break;
                }
            }
        }

        $responsePayload = ["response" => $responseText];
        if ($calculationDetails) $responsePayload["calculation"] = $calculationDetails;
        if ($categoryCalculations) $responsePayload["calculations"] = $categoryCalculations;

        ChatHistory::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => $request->message ?? '',
            'role' => 'user',
            'response' => json_encode($responsePayload),
            'created_at' => now(),
        ]);

        return response()->json($responsePayload);
    }

    public function getChat(Request $request)
    {
        $istNow = now()->timezone('Asia/Kolkata');
        $istMidnight = $istNow->copy()->startOfDay();
        $utcCutoff = $istMidnight->timezone('UTC');

        ChatHistory::where('created_at', '<', $utcCutoff)->delete();

        $chats = ChatHistory::where('user_id', $request->user()->id)
            ->where('created_at', '>=', $utcCutoff)
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [];
        foreach ($chats as $chat) {
            $result[] = [
                "id" => $chat->id,
                "session_id" => $chat->session_id,
                "message" => $chat->message,
                "response" => $chat->response,
                "created_at" => $chat->created_at->toIso8601String()
            ];
        }

        return response()->json($result);
    }

    public function deleteChat(Request $request)
    {
        ChatHistory::where('user_id', $request->user()->id)->delete();
        return response()->json(["message" => "all chats deleted successfully"]);
    }
}