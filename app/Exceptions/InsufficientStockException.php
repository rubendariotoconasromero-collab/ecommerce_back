<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public readonly int $requested;
    public readonly int $available;
    public readonly string $productName;

    public function __construct(string $productName, int $requested, int $available)
    {
        $this->productName = $productName;
        $this->requested   = $requested;
        $this->available   = $available;

        parent::__construct(
            "Stock insuficiente para '{$productName}': solicitado {$requested}, disponible para venta {$available}."
        );
    }
}
