<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UpdateExchangeRate extends Command
{
    // Command name that runs from the terminal or in the background
    protected $signature = 'app:update-exchange-rate';
    
    protected $description = 'Fetches the live USD to INR rate from a free API and saves it in the cache';

    public function handle()
    {
        try {
            // We now use a free Exchange Rate API (JSON) instead of Google
            $response = Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD');

            if ($response->successful()) {
                $data = $response->json();
                
                // Read the INR rate directly
                if (isset($data['rates']['INR'])) {
                    $rate = (float) $data['rates']['INR'];
                    
                    // Save the rate in memory (Cache) permanently
                    Cache::put('usd_to_inr_rate', $rate);
                    $this->info("✅ Success! Live Rate Saved: 1 USD = ₹{$rate}");
                    return;
                }
            }
            $this->warn("⚠️ Could not fetch API data, the old cache will be used.");
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}