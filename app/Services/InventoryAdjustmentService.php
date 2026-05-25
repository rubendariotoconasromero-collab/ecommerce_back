<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryAdjustmentBatch;
use App\Models\InventoryAdjustmentLine;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    /**
     * Genera el próximo número de lote correlativo por año.
     * Formato: AJU-YYYY-NNNNN
     */
    private function generateBatchNumber(): string
    {
        $year   = now()->year;
        $prefix = "AJU-{$year}-";

        $last = InventoryAdjustmentBatch::where('batch_number', 'like', "{$prefix}%")
            ->orderByDesc('batch_number')
            ->value('batch_number');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Calcula el delta (cambio de stock) según el tipo de ajuste.
     * - entry / initial_stock : suma la cantidad
     * - exit                  : resta la cantidad
     * - correction            : la cantidad puede ser positiva o negativa
     *   (el frontend siempre envía quantity >= 1; se usa qty_delta directamente)
     */
    private function computeDelta(string $type, int $quantity): int
    {
        return match ($type) {
            'entry', 'initial_stock' => $quantity,
            'exit'                   => -$quantity,
            'correction'             => $quantity, // positivo=entrada, negativo=salida en correction
            default                  => $quantity,
        };
    }

    /**
     * Crea un lote de ajuste, aplica los cambios de stock y,
     * opcionalmente, actualiza precios de los productos afectados.
     *
     * @param array $data     Datos validados del request
     * @param User  $actor    Usuario que realiza el ajuste
     * @return InventoryAdjustmentBatch
     */
    public function createBatch(array $data, User $actor): InventoryAdjustmentBatch
    {
        return DB::transaction(function () use ($data, $actor) {
            $type  = $data['adjustment_type'];
            $lines = $data['lines'];

            // Recopilar product_ids únicos (sin duplicados en el mismo lote)
            $productIds = collect($lines)->pluck('product_id')->unique()->values()->all();

            // Cargar productos e inventarios con lockForUpdate para garantizar consistencia
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $inventories = Inventory::whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            // Crear el registro de cabecera del lote
            $batch = InventoryAdjustmentBatch::create([
                'batch_number'    => $this->generateBatchNumber(),
                'adjustment_type' => $type,
                'notes'           => $data['notes'] ?? null,
                'created_by'      => $actor->id,
                'confirmed_at'    => now(),
            ]);

            foreach ($lines as $lineData) {
                $productId = $lineData['product_id'];
                $product   = $products[$productId];
                $inventory = $inventories[$productId];

                $previousQty = $inventory->qty_available;
                $delta       = $this->computeDelta($type, (int) $lineData['quantity']);
                $newQty      = max(0, $previousQty + $delta);

                // Crear línea de detalle con snapshot de precios actuales
                InventoryAdjustmentLine::create([
                    'batch_id'            => $batch->id,
                    'product_id'          => $productId,
                    'product_name'        => $product->name,
                    'product_sku'         => $product->sku,
                    'previous_qty'        => $previousQty,
                    'qty_delta'           => $delta,
                    'new_qty'             => $newQty,
                    'previous_cost_price' => $product->cost_price,
                    'new_cost_price'      => $lineData['new_cost_price'] ?? null,
                    'previous_sale_price' => $product->sale_price,
                    'new_sale_price'      => $lineData['new_sale_price'] ?? null,
                    'line_notes'          => $lineData['line_notes'] ?? null,
                ]);

                // Aplicar cambio de stock
                $inventory->qty_available = $newQty;
                $inventory->save();

                // Aplicar cambios de precio si fueron indicados
                $priceUpdates = [];
                if (isset($lineData['new_cost_price'])) {
                    $priceUpdates['cost_price'] = $lineData['new_cost_price'];
                }
                if (isset($lineData['new_sale_price'])) {
                    $priceUpdates['sale_price'] = $lineData['new_sale_price'];
                }
                if (!empty($priceUpdates)) {
                    $product->update($priceUpdates);
                }
            }

            return $batch->load(['lines', 'creator']);
        });
    }
}
