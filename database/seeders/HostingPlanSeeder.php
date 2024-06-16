<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HostingPlan;

class HostingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        HostingPlan::create([
            'name' => 'Free',
            'description' => 'Free hosting plan with limited resources',
            'disk_space' => 1000, // 1 GB
            'bandwidth' => 10000, // 10 GB
            'price' => 0.00,
        ]);

        HostingPlan::create([
            'name' => 'Premium',
            'description' => 'Premium hosting plan with enhanced resources',
            'disk_space' => 10000, // 10 GB
            'bandwidth' => 100000, // 100 GB
            'price' => 9.99,
        ]);
    }
}