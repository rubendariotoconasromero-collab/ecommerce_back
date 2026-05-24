<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Ajuste manual de stock por parte de un administrador.
     * Retorna el registro de inventario actualizado.
     */
    public function adjust(Inventory $inventory, int $newQtyAvailable, int $newReorderPoint, string $reason): Inventory
    {
        return DB::transaction(function () use ($inventory, $newQtyAvailable, $newReorderPoint, $reason) {
            $fresh = Inventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            $previousQty = $fresh->qty_available;

            $fresh->qty_available  = $newQtyAvailable;
            $fresh->reorder_point  = $newReorderPoint;
            $fresh->save();

            Log::info('inventory.adjusted', [
                'product_id'   => $fresh->product_id,
                'by_user_id'   => auth()->id(),
                'from_qty'     => $previousQty,
                'to_qty'       => $newQtyAvailable,
                'reorder_point'=> $newReorderPoint,
                'reason'       => $reason,
            ]);

            return $fresh->refresh();
        });
    }

    /**
     * Reserva stock al confirmar una orden.
     * Lanza InsufficientStockException si no hay stock vendible suficiente.
     */
    public function reserve(string $productId, int $qty, string $productName = ''): void
    {
        DB::transaction(function () use ($productId, $qty, $productName) {
            $inventory = Inventory::where('product_id', $productId)
                                  ->lockForUpdate()
                                  ->firstOrFail();

            $sellable = $inventory->qty_available - $inventory->qty_reserved;

            if ($sellable < $qty) {
                throw new InsufficientStockException(
                    $productName ?: $productId,
                    $qty,
                    max(0, $sellable)
                );
            }

            $inventory->qty_reserved += $qty;
            $inventory->save();
        });
    }

    /**
     * Libera stock reservado al cancelar o rechazar una orden.
     */
    public function release(string $productId, int $qty): void
    {
        DB::transaction(function () use ($productId, $qty) {
            $inventory = Inventory::where('product_id', $productId)
                                  ->lockForUpdate()
                                  ->firstOrFail();

            $inventory->qty_reserved = max(0, $inventory->qty_reserved - $qty);
            $inventory->save();
        });
    }

    /**
     * Mueve stock de disponible a en-producción al iniciar una orden de producción.
     */
    public function moveToProduction(string $productId, int $qty): void
    {
        DB::transaction(function () use ($productId, $qty) {
            $inventory = Inventory::where('product_id', $productId)
                                  ->lockForUpdate()
                                  ->firstOrFail();

            $inventory->qty_reserved     = max(0, $inventory->qty_reserved - $qty);
            $inventory->qty_in_production += $qty;
            $inventory->save();
        });
    }

    /**
     * Cancela una producción en curso: revierte el stock de in_production a reserved.
     * Usar cuando una ProductionOrder en estado 'in_progress' es cancelada.
     */
    public function cancelProduction(string $productId, int $qty): void
    {
        DB::transaction(function () use ($productId, $qty) {
            $inventory = Inventory::where('product_id', $productId)
                                  ->lockForUpdate()
                                  ->firstOrFail();

            $moved = min($qty, $inventory->qty_in_production);

            $inventory->qty_in_production = max(0, $inventory->qty_in_production - $moved);
            $inventory->qty_reserved     += $moved;
            $inventory->save();
        });
    }

    /**
     * Completa producción: el stock en-producción pasa a disponible.
     */
    public function completeProduction(string $productId, int $qty): void
    {
        DB::transaction(function () use ($productId, $qty) {
            $inventory = Inventory::where('product_id', $productId)
                                  ->lockForUpdate()
                                  ->firstOrFail();

            $moved = min($qty, $inventory->qty_in_production);

            $inventory->qty_in_production = max(0, $inventory->qty_in_production - $moved);
            $inventory->qty_available    += $moved;
            $inventory->save();
        });
    }

    /**
     * Crea el registro de inventario inicial para un producto recién creado.
     */
    public function initForProduct(Product $product): Inventory
    {
        return Inventory::firstOrCreate(
            ['product_id' => $product->id],
            [
                'qty_available'    => 0,
                'qty_reserved'     => 0,
                'qty_in_production'=> 0,
                'reorder_point'    => 0,
            ]
        );
    }
}
