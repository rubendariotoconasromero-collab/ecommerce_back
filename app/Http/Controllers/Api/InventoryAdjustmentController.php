<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryAdjustmentRequest;
use App\Http\Resources\InventoryAdjustmentBatchResource;
use App\Models\InventoryAdjustmentBatch;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;

class InventoryAdjustmentController extends Controller
{
    public function __construct(private readonly InventoryAdjustmentService $service) {}

    /**
     * GET /api/inventory-adjustments
     * Lista paginada de lotes de ajuste, del más reciente al más antiguo.
     */
    public function index(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:entry,exit,initial_stock,correction',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = InventoryAdjustmentBatch::with('creator')
            ->withCount('lines')
            ->orderByDesc('confirmed_at');

        if ($request->filled('type')) {
            $query->where('adjustment_type', $request->type);
        }

        $batches = $query->paginate(20);

        return InventoryAdjustmentBatchResource::collection($batches);
    }

    /**
     * POST /api/inventory-adjustments
     * Crea y aplica un nuevo lote de ajuste de stock (múltiples productos).
     */
    public function store(StoreInventoryAdjustmentRequest $request)
    {
        $batch = $this->service->createBatch(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => "Ajuste {$batch->batch_number} registrado y aplicado correctamente.",
            'batch'   => new InventoryAdjustmentBatchResource($batch),
        ], 201);
    }

    /**
     * GET /api/inventory-adjustments/{batch}
     * Devuelve el detalle completo de un lote: cabecera + todas las líneas.
     */
    public function show(InventoryAdjustmentBatch $batch)
    {
        $batch->load(['lines', 'creator']);

        return new InventoryAdjustmentBatchResource($batch);
    }
}
