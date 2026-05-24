<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\InventoryService;

class ProductObserver
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * Al crear un producto se inicializa automáticamente su ficha de inventario en cero.
     */
    public function created(Product $product): void
    {
        $this->inventoryService->initForProduct($product);
    }
}
