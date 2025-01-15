<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'admin01@posv01.com',
            'password' => bcrypt('@Admin123'),
        ]);

        $this->call(ProvinceSeeder::class);
        $this->call(RegencySeeder::class);
        $this->call(DistrictSeeder::class);
        $this->call(VillageSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(productSeeder::class);
        Customer::factory(50)->create();
        Supplier::factory(50)->create();
    }
}
