<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if monnify already exists
        $exists = DB::table('addon_settings')
            ->where('key_name', 'monnify')
            ->where('settings_type', 'payment_config')
            ->exists();

        if (!$exists) {
            DB::table('addon_settings')->insert([
                'key_name' => 'monnify',
                'settings_type' => 'payment_config',
                'mode' => 'test',
                'live_values' => json_encode([
                    'api_key' => '',
                    'secret_key' => '',
                    'contract_code' => '',
                    'mode' => 'live'
                ]),
                'test_values' => json_encode([
                    'api_key' => 'MK_TEST_8AGWBDUSGS',
                    'secret_key' => 'Y4FPBP1T72CGCP7RCVWNQCR80TYJ40AQ',
                    'contract_code' => '2295851286',
                    'mode' => 'test'
                ]),
                'is_active' => 0,
                'additional_data' => json_encode([
                    'gateway_title' => 'Monnify',
                    'gateway_image' => 'monnify.png'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('addon_settings')
            ->where('key_name', 'monnify')
            ->where('settings_type', 'payment_config')
            ->delete();
    }
};
