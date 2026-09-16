<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UpdateExchangeRate extends Command
{
    // Command ka naam jo terminal ya background me chalega
    protected $signature = 'app:update-exchange-rate';
    
    protected $description = 'Free API se live USD to INR rate fetch karke Cache me save karta hai';

    public function handle()
    {
        try {
            // Google ki jagah ab hum Free Exchange Rate API (JSON) use kar rahe hain
            $response = Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD');

            if ($response->successful()) {
                $data = $response->json();
                
                // Direct INR ka rate nikal liya
                if (isset($data['rates']['INR'])) {
                    $rate = (float) $data['rates']['INR'];
                    
                    // Rate ko memory (Cache) me life-time save kar do
                    Cache::put('usd_to_inr_rate', $rate);
                    $this->info("✅ Success! Live Rate Saved: 1 USD = ₹{$rate}");
                    return;
                }
            }
            $this->warn("⚠️ API data fetch nahi ho paya, purana cache use hoga.");
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}