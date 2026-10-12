<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatUser;
use App\Models\ChatHistory;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductRequest;
use App\Models\ExchangeRate; // 🔥 ExchangeRate model imported here
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
    // PRODUCT REQUESTS (EXPORT USERS ONLY)
    // ===================================
    public function getMyProductRequests(Request $request)
    {
        $user = $request->user();
        if ($user->user_type !== 'export') {
            return response()->json(['message' => 'Only export users can view product requests.'], 403);
        }

        ProductRequest::purgeProcessed();

        $requests = ProductRequest::with('category:id,name')
            ->where('chat_user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'product_name' => $r->product_name,
                'category_name' => $r->category ? $r->category->name : $r->custom_category_name,
                'deadline' => $r->deadline->format('Y-m-d'),
                'status' => $r->status,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        return response()->json($requests);
    }

    public function getRequestCategories(Request $request)
    {
        if ($request->user()->user_type !== 'export') {
            return response()->json(['message' => 'Only export users can request new products.'], 403);
        }

        return response()->json(
            Category::orderBy('name')->get(['id', 'name'])
        );
    }

    public function storeProductRequest(Request $request)
    {
        $user = $request->user();
        if ($user->user_type !== 'export') {
            return response()->json(['message' => 'Only export users can request new products.'], 403);
        }

        $today = Carbon::today('Asia/Kolkata')->toDateString();

        // Only categories that exist in the database are allowed. Only the Super Admin can add a new category.
        $data = $request->validate([
            'product_name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'deadline' => "required|date|after_or_equal:$today",
        ], [
            'category_id.exists' => 'Please select a valid category.',
            'deadline.after_or_equal' => 'Deadline cannot be a past date.',
        ]);

        $productRequest = ProductRequest::create([
            'chat_user_id' => $user->id,
            'product_name' => trim($data['product_name']),
            'category_id' => (int) $data['category_id'],
            'deadline' => $data['deadline'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Your product request has been sent.',
            'data' => $productRequest,
        ], 201);
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
            "message" => "Registration successful! You can now log in."
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
            return response()->json(["status" => "error", "message" => "Incorrect email or password."], 401);
        }

        $token = $user->createToken('chatbot-token')->plainTextToken;

        return response()->json([
            "status" => "success",
            "token" => $token,
            "user" => ["name" => $user->name, "email" => $user->email, "user_type" => $user->user_type]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            "name" => $user->name,
            "email" => $user->email,
            "user_type" => $user->user_type,
            // Notification number shown next to "Request New Product"
            "pending_count" => $user->user_type === 'export'
                ? ProductRequest::where('chat_user_id', $user->id)->where('status', 'pending')->count()
                : 0,
        ]);
    }

    // ===================================
    // MANUAL CURRENCY READER (From DB)
    // ===================================
    private function getUsdExchangeRate()
    {
        // 🔥 Removed the cache; the rate is now read directly from the ExchangeRate table
        $exchange = ExchangeRate::where('currency_code', 'USD')->first();
        return $exchange ? (float) $exchange->rate : 84.00; 
    }

    // ===================================
    // EXPORT MARGIN & USD CALCULATION ENGINE
    // ===================================
    private function calculateFinalPrice($product, $category, $user = null)
    {
        try {
            // Processing (grinding) cost and yield now come from the category (not the product). Empty processing cost = 0.
            $grindingCost = $category ? (float) $category->grinding_cost : 0;
            $baseCost = (float) $product->rm_cost + $grindingCost;
            $yieldPct = $category ? (float) $category->yield_percentage : 0;
            if ($yieldPct <= 0) return 0;

            $finalCost = $baseCost / ($yieldPct / 100);
            
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
            // Processing (grinding) cost and yield now come from the category (not the product). Empty processing cost = 0.
            $grindingCost = $category ? (float) $category->grinding_cost : 0;
            $baseCost = (float) $product->rm_cost + $grindingCost;
            $yieldPct = $category ? (float) $category->yield_percentage : 0;
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
                "description" => trim((string) $product->description) !== '' ? trim((string) $product->description) : null,
                "category_name" => $category->name ?? 'None',
                "rm_cost" => round((float) $product->rm_cost, 2),
                "grinding_cost" => round($grindingCost, 2),
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
    // REPLY BUILDERS (same output that used to be inline)
    // ===================================
    private function productReply($product, $user, string $currencySymbol): array
    {
        $price = $this->calculateFinalPrice($product, $product->category, $user);
        $text = "✅ {$product->name} Price: {$currencySymbol}{$price}/KG";
        $calculation = $this->getDetailedCalculation($product, $product->category, $user);

        return [$text, $calculation];
    }

    private function categoryReply($category, $user, string $currencySymbol): array
    {
        $text = "🟢 {$category->name} Products\n\n";
        $calculations = [];

        foreach ($category->products as $p) {
            $price = $this->calculateFinalPrice($p, $category, $user);
            $text .= "✅ {$p->name} : {$currencySymbol}{$price}/KG\n";
            $calc = $this->getDetailedCalculation($p, $category, $user);
            if ($calc) {
                $calculations[] = $calc;
            }
        }

        return [$text, $calculations];
    }

    // ===================================
    // SMART MATCHING (spelling / partial name / word order)
    //
    // Runs only when the old exact ("contains") match found nothing.
    // Rule: show the price only when the match is completely clear (exactly one product/category).
    // If in doubt, NO price — only the "Did you mean" suggestion text. Never a wrong product.
    // ===================================
    private const SEARCH_STOPWORDS = [
        'price', 'prices', 'cost', 'costs', 'rate', 'rates', 'of', 'the', 'is', 'are', 'what', 'whats',
        'how', 'much', 'tell', 'me', 'show', 'give', 'please', 'pls', 'plz', 'kya', 'ka', 'ki', 'ke',
        'hai', 'hain', 'batao', 'bata', 'btao', 'do', 'dena', 'kitna', 'kitne', 'kitni', 'mujhe', 'muje',
        'ko', 'per', 'kg', 'kgs', 'kilo', 'for', 'in', 'a', 'an', 'and', 'to', 'about', 'rs', 'inr',
        'usd', 'dollar', 'rupee', 'rupees', 'detail', 'details', 'info',
    ];

    private function singularize(string $word): string
    {
        $len = strlen($word);

        if ($len > 4 && substr($word, -3) === 'ies') {
            return substr($word, 0, -3) . 'y';
        }
        if ($len > 4 && preg_match('/(ss|us|is)$/', $word)) {
            return $word;
        }
        if ($len > 4 && preg_match('/(ch|sh|x|z|s)es$/', $word)) {
            return substr($word, 0, -2);
        }
        if ($len > 3 && substr($word, -1) === 's') {
            return substr($word, 0, -1);
        }

        return $word;
    }

    private function searchTokens(string $text, bool $dropStopwords): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $tokens = [];

        foreach (preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if ($dropStopwords && in_array($word, self::SEARCH_STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $this->singularize($word);
        }

        return array_values(array_unique($tokens));
    }

    // 1.0 = same word, 0.95 = chhoti spelling galti, 0.9 = adhoora word (prefix), 0 = alag word
    private function tokenSimilarity(string $q, string $t): float
    {
        if ($q === $t) {
            return 1.0;
        }
        // No spelling guess for numbers and non-English words
        if (ctype_digit($q) || ctype_digit($t) || preg_match('/[^\x00-\x7F]/', $q . $t)) {
            return 0.0;
        }
        $lq = strlen($q);
        $lt = strlen($t);

        // Spelling guess: the first letter must match (lower risk of a wrong match)
        if ($q[0] === $t[0]) {
            if ($lq >= 5 && $lq < $lt && strpos($t, $q) === 0) {
                return 0.9;
            }

            // Short words (<=4): no mistakes allowed; 5-7: 1; 8+: 2
            $max = max($lq, $lt);
            $tolerance = $max <= 4 ? 0 : ($max <= 7 ? 1 : 2);

            if ($tolerance > 0 && levenshtein($q, $t) <= $tolerance) {
                return 0.95;
            }
        }

        // Mistakes caused by speaking (voice): same sound, different spelling (e.g. "carella" / "karela")
        if ($lq >= 5 && $lt >= 5) {
            $codeQ = metaphone($q);
            if (strlen($codeQ) >= 3 && $codeQ === metaphone($t)) {
                return 0.85;
            }
        }

        return 0.0;
    }

    private function joinedSimilar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (strlen($a) < 6 || strlen($b) < 6 || $a[0] !== $b[0] || preg_match('/[^\x00-\x7F]/', $a . $b) || ctype_digit($a . $b)) {
            return false;
        }

        $max = max(strlen($a), strlen($b));
        $tolerance = $max <= 7 ? 1 : 2;

        return levenshtein($a, $b) <= $tolerance;
    }

    private function matchPhrase(array $queryTokens, array $phraseTokens): array
    {
        $matchedQuery = 0;
        $usedPhraseTokens = [];
        $score = 0.0;

        foreach ($queryTokens as $q) {
            $best = 0.0;
            $bestToken = null;

            foreach ($phraseTokens as $t) {
                $sim = $this->tokenSimilarity($q, $t);
                if ($sim > $best) {
                    $best = $sim;
                    $bestToken = $t;
                }
            }

            if ($best > 0) {
                $matchedQuery++;
                $usedPhraseTokens[$bestToken] = true;
                $score += $best;
            }
        }

        $all = $matchedQuery === count($queryTokens);
        $equal = $all && count($usedPhraseTokens) === count($phraseTokens);

        // With voice input, words can split/merge ("ashwa ganda" = "ashwagandha"):
        // join all the words and compare with the full name (small mistakes allowed)
        if (!$equal && count($queryTokens) > 0) {
            $joinedQuery = implode('', $queryTokens);
            $total = count($phraseTokens);

            // Any contiguous part (window) of the name — 'equal' if it is the full name, otherwise only 'all'
            for ($start = 0; $start < $total; $start++) {
                for ($end = $start; $end < $total; $end++) {
                    $window = array_slice($phraseTokens, $start, $end - $start + 1);
                    if ($this->joinedSimilar($joinedQuery, implode('', $window))) {
                        return [
                            'all' => true,
                            'equal' => count($window) === $total,
                            'matched' => count($queryTokens),
                            'score' => 0.9 * count($queryTokens),
                            'tokens' => $window,
                        ];
                    }
                }
            }
        }

        return [
            'all' => $all,                                                   // every word of the query was found in this name
            'equal' => $equal,                                               // every word of the name was also found in the query
            'matched' => $matchedQuery,
            'score' => $score,
            'tokens' => array_keys($usedPhraseTokens),
        ];
    }

    /**
     * Returns ['type' => 'product'|'category', 'model' => ...]  (clear match)
     *      or ['type' => 'suggest', 'names' => [...]]            (in doubt, no price)
     *      or ['type' => 'none'].
     */
    private function smartResolve(string $searchStr, $products, $categories): array
    {
        $queryTokens = $this->searchTokens($searchStr, true);
        if (empty($queryTokens)) {
            return ['type' => 'none'];
        }

        // Entities: products that have a category + categories; each one's name/keywords are the phrases
        $entities = [];
        foreach ($products as $p) {
            if (!$p->category) {
                continue;
            }
            $phrases = [$p->name];
            foreach (explode(',', (string) $p->keywords) as $keyword) {
                if (trim($keyword) !== '') {
                    $phrases[] = $keyword;
                }
            }
            $entities['p' . $p->id] = ['type' => 'product', 'model' => $p, 'label' => $p->name, 'phrases' => $phrases];
        }
        foreach ($categories as $c) {
            $entities['c' . $c->id] = ['type' => 'category', 'model' => $c, 'label' => $c->name . ' (Category)', 'phrases' => [$c->name]];
        }

        $results = [];
        $documentFrequency = [];
        foreach ($entities as $key => $entity) {
            $seenTokens = [];
            $best = null;

            foreach ($entity['phrases'] as $phrase) {
                $phraseTokens = $this->searchTokens($phrase, false);
                if (empty($phraseTokens)) {
                    continue;
                }
                foreach ($phraseTokens as $t) {
                    $seenTokens[$t] = true;
                }

                $m = $this->matchPhrase($queryTokens, $phraseTokens);
                if ($best === null || [$m['equal'], $m['all'], $m['score']] > [$best['equal'], $best['all'], $best['score']]) {
                    $best = $m;
                }
            }

            foreach (array_keys($seenTokens) as $t) {
                $documentFrequency[$t] = ($documentFrequency[$t] ?? 0) + 1;
            }
            if ($best !== null) {
                $results[$key] = $best;
            }
        }

        // 1) Exactly the same name (spelling/order/plural may differ) — only when there is just one
        $equal = array_keys(array_filter($results, fn ($m) => $m['equal']));
        if (count($equal) === 1) {
            return ['type' => $entities[$equal[0]]['type'], 'model' => $entities[$equal[0]]['model']];
        }
        if (count($equal) > 1) {
            return $this->suggestFrom($entities, array_slice($equal, 0, 5));
        }

        // 2) Partial name (e.g. just "moringa") — every query word matches and there is only ONE entity
        $all = array_keys(array_filter($results, fn ($m) => $m['all']));
        if (count($all) === 1) {
            return ['type' => $entities[$all[0]]['type'], 'model' => $entities[$all[0]]['model']];
        }
        if (count($all) > 1) {
            return $this->suggestFrom($entities, array_slice($all, 0, 5));
        }

        // 3) Only some words matched — no price, suggest the closest names
        $total = count($entities);
        $scored = [];
        foreach ($results as $key => $m) {
            if ($m['matched'] === 0) {
                continue;
            }
            $weight = 0.0;
            foreach ($m['tokens'] as $t) {
                $df = $documentFrequency[$t] ?? 1;
                // A very common word (e.g. "organic" appears in everything) gets almost zero weight
                $weight += ($total >= 4 && $df / $total >= 0.5) ? 0.05 : 1 / $df;
            }
            if ($weight >= 0.3) {
                $scored[$key] = $weight;
            }
        }
        if (empty($scored)) {
            return ['type' => 'none'];
        }
        arsort($scored);

        return $this->suggestFrom($entities, array_slice(array_keys($scored), 0, 3));
    }

    private function suggestFrom(array $entities, array $keys): array
    {
        return [
            'type' => 'suggest',
            'names' => array_map(fn ($key) => $entities[$key]['label'], $keys),
        ];
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
                "response" => "👋 Hello! I'm the HNCO AI Assistant.\nYou can ask me the price of any product or category."
            ];
            
            ChatHistory::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => mb_substr((string) ($request->message ?? ''), 0, 500),
                'role' => 'user',
                'response' => json_encode($responsePayload),
                'created_at' => now(),
            ]);
            
            return response()->json($responsePayload);
        }

        $searchStr = str_replace(["price of", "cost of", "rate of", "tell me"], "", $message);
        $searchStr = trim($searchStr);
        
        $notFoundText = "❌ Product or Category not found";
        $responseText = $notFoundText;
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

        $categories = null;

        if ($matchedProduct && $matchedProduct->category) {
            [$responseText, $calculationDetails] = $this->productReply($matchedProduct, $user, $currencySymbol);
        } else {
            $categories = Category::with('products')->get();
            foreach ($categories as $c) {
                if (str_contains($searchStr, strtolower($c->name))) {
                    [$responseText, $categoryCalculations] = $this->categoryReply($c, $user, $currencySymbol);
                    break;
                }
            }
        }

        // No exact match found — smart matching (spelling / partial name / word order).
        // Price only on a clear match; if in doubt, only the "Did you mean" text.
        if ($responseText === $notFoundText) {
            $categories = $categories ?? Category::with('products')->get();
            $smart = $this->smartResolve($searchStr, $products, $categories);

            if ($smart['type'] === 'product') {
                [$responseText, $calculationDetails] = $this->productReply($smart['model'], $user, $currencySymbol);
            } elseif ($smart['type'] === 'category') {
                [$responseText, $categoryCalculations] = $this->categoryReply($smart['model'], $user, $currencySymbol);
            } elseif ($smart['type'] === 'suggest') {
                $responseText = "🤔 I couldn't find an exact match. Did you mean:\n\n• "
                    . implode("\n• ", $smart['names'])
                    . "\n\nPlease type the full name.";
            }
        }

        $responsePayload = ["response" => $responseText];
        if ($calculationDetails) $responsePayload["calculation"] = $calculationDetails;
        if ($categoryCalculations) $responsePayload["calculations"] = $categoryCalculations;

        ChatHistory::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => mb_substr((string) ($request->message ?? ''), 0, 500),
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