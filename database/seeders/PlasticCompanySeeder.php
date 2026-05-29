<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlasticCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * NOTA: Este seeder ha sido refactorizado y separado en seeders individuales 
     * e independientes para una mayor claridad, modularidad y mantenibilidad.
     * Se mantiene esta clase para preservar la compatibilidad con cualquier script o comando.
     */
    public function run(): void
    {
        $this->call([
            CompanySettingSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
