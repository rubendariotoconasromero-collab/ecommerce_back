<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Crea registros de inventario para todos los productos que aún no tienen uno.
     * Los valores son de ejemplo para desarrollo/pruebas.
     */
    public function run(): void
    {
        $stocks = [
            'Bidón Apilable 20L'          => ['qty' => 120, 'reorder' => 20],
            'Tambor 200L Soplado'          => ['qty' => 45,  'reorder' => 10],
            'Contenedor IBC 1000L'         => ['qty' => 12,  'reorder' => 3],
            'Set Herméticos x3'            => ['qty' => 300, 'reorder' => 50],
            'Cesto de Residuos 50L'        => ['qty' => 85,  'reorder' => 15],
            'Organizador Apilable Grande'  => ['qty' => 200, 'reorder' => 30],
            'Film Stretch 50cm x 5kg'      => ['qty' => 60,  'reorder' => 12],
            'Rollo Burbuja 1m x 50m'       => ['qty' => 35,  'reorder' => 8],
            'Botella PET 500ml Cristal'    => ['qty' => 5000,'reorder' => 500],
            'Botella PET 2L Económica'     => ['qty' => 2000,'reorder' => 300],
            'Tapa de Seguridad 28mm'       => ['qty' => 10000,'reorder' => 1000],
            'Botella HDPE 1L Blanca'       => ['qty' => 800, 'reorder' => 100],
        ];

        Product::doesntHave('inventory')->each(function (Product $product) use ($stocks) {
            $config = $stocks[$product->name] ?? ['qty' => 0, 'reorder' => 0];

            Inventory::create([
                'product_id'        => $product->id,
                'qty_available'     => $config['qty'],
                'qty_reserved'      => 0,
                'qty_in_production' => 0,
                'reorder_point'     => $config['reorder'],
            ]);
        });

        $count = Inventory::count();
        $this->command->info("InventorySeeder: {$count} registros de inventario en total.");
    }
}
