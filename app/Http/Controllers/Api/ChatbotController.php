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
            // Grinding cost aur yield ab category se aate hain (product se nahi). Grinding khali = 0.
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
            // Grinding cost aur yield ab category se aate hain (product se nahi). Grinding khali = 0.
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
    // REPLY BUILDERS (same output jo pehle inline tha)
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
    // SMART MATCHING (spelling / adhoora naam / words ka order)
    //
    // Sirf tab chalta hai jab purana exact ("contains") match kuch nahi dhundh paaya.
    // Rule: price tabhi dikhao jab match bilkul clear ho (ek hi product/category).
    // Shak ho to price NAHI, sirf "Did you mean" suggestion text. Galat product kabhi nahi.
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
        // Numbers aur non-English words par spelling-guess nahi
        if (ctype_digit($q) || ctype_digit($t) || preg_match('/[^\x00-\x7F]/', $q . $t)) {
            return 0.0;
        }
        $lq = strlen($q);
        $lt = strlen($t);

        // Spelling guess: pehla letter same hona chahiye (galat match ka risk kam)
        if ($q[0] === $t[0]) {
            if ($lq >= 5 && $lq < $lt && strpos($t, $q) === 0) {
                return 0.9;
            }

            // Chhote words (<=4) me galti allowed nahi; 5-7 me 1; 8+ me 2
            $max = max($lq, $lt);
            $tolerance = $max <= 4 ? 0 : ($max <= 7 ? 1 : 2);

            if ($tolerance > 0 && levenshtein($q, $t) <= $tolerance) {
                return 0.95;
            }
        }

        // Bolne se aayi galti (voice): awaaz same, spelling alag (jaise "carella" / "karela")
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

        // Voice me words toot/jud sakte hain ("ashwa ganda" = "ashwagandha"):
        // saare words jod kar poore naam se compare (chhoti galti allowed)
        if (!$equal && count($queryTokens) > 0) {
            $joinedQuery = implode('', $queryTokens);
            $total = count($phraseTokens);

            // Naam ka koi bhi lagatar hissa (window) — poora naam ho to 'equal', warna sirf 'all'
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
            'all' => $all,                                                   // query ka har word is naam me mila
            'equal' => $equal,                                               // naam ka har word bhi query me mila
            'matched' => $matchedQuery,
            'score' => $score,
            'tokens' => array_keys($usedPhraseTokens),
        ];
    }

    /**
     * Returns ['type' => 'product'|'category', 'model' => ...]  (clear match)
     *      or ['type' => 'suggest', 'names' => [...]]            (shak hai, price nahi)
     *      or ['type' => 'none'].
     */
    private function smartResolve(string $searchStr, $products, $categories): array
    {
        $queryTokens = $this->searchTokens($searchStr, true);
        if (empty($queryTokens)) {
            return ['type' => 'none'];
        }

        // Entities: category wale products + categories; har ek ke naam/keywords phrases hain
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

        // 1) Naam bilkul wahi (spelling/order/plural ka farak ho sakta hai) — sirf ek ho tab
        $equal = array_keys(array_filter($results, fn ($m) => $m['equal']));
        if (count($equal) === 1) {
            return ['type' => $entities[$equal[0]]['type'], 'model' => $entities[$equal[0]]['model']];
        }
        if (count($equal) > 1) {
            return $this->suggestFrom($entities, array_slice($equal, 0, 5));
        }

        // 2) Adhoora naam (jaise sirf "moringa") — query ka har word mile aur entity sirf EK ho
        $all = array_keys(array_filter($results, fn ($m) => $m['all']));
        if (count($all) === 1) {
            return ['type' => $entities[$all[0]]['type'], 'model' => $entities[$all[0]]['model']];
        }
        if (count($all) > 1) {
            return $this->suggestFrom($entities, array_slice($all, 0, 5));
        }

        // 3) Sirf kuch words mile — price nahi, sabse nazdeek ke naam suggest
        $total = count($entities);
        $scored = [];
        foreach ($results as $key => $m) {
            if ($m['matched'] === 0) {
                continue;
            }
            $weight = 0.0;
            foreach ($m['tokens'] as $t) {
                $df = $documentFrequency[$t] ?? 1;
                // Bahut common word (jaise "organic" sab me) ka weight lagbhag zero
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

        // Exact match nahi mila — smart matching (spelling / adhoora naam / words ka order).
        // Price sirf clear match par; shak ho to sirf "Did you mean" text.
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