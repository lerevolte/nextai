<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tariffs = Tariff::getDefaultTariffs();
        
        foreach ($tariffs as $tariffData) {
            Tariff::updateOrCreate(
                ['slug' => $tariffData['slug']],
                $tariffData
            );
        }
        
        $this->command->info('Тарифы успешно созданы!');
    }
}