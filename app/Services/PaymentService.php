<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class PaymentService
{
    // -------------------------------------------------------------------------
    // Registro de nuevo pago
    // -------------------------------------------------------------------------

    /**
     * Registra un pago para la orden.
     *
     * @param  bool $markCompleted  Si true, marca el pago como 'completed' de inmediato
     *                              (útil para efectivo o transferencias confirmadas al instante).
     */
    public function register(Order $order, array $data, User $actor, bool $markCompleted = false): Payment
    {
        return DB::transaction(function () use ($order, $data, $actor, $markCompleted) {

            // Bloquear fila de la orden para evitar race conditions
            $order = Order::lockForUpdate()->findOrFail($order->id);

            $this->assertOrderAcceptsPayments($order);

            $amountPending = $this->getOrderBalance($order)['amount_pending'];

            if ((float) $data['amount'] > $amountPending + 0.001) {
                throw new LogicException(
                    "El monto ingresado ({$data['amount']}) supera el saldo pendiente ({$amountPending}) de la orden."
                );
            }

            $status = $markCompleted ? 'completed' : 'pending';
            $paidAt = $markCompleted ? now() : null;

            $payment = Payment::create([
                'order_id'       => $order->id,
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'amount'         => $data['amount'],
                'status'         => $status,
                'paid_at'        => $paidAt,
            ]);

            if ($markCompleted) {
                $this->checkAndAutoConfirm($order);
            }

            return $payment;
        });
    }

    // -------------------------------------------------------------------------
    // Cambios de estado
    // -------------------------------------------------------------------------

    /**
     * Marca un pago pendiente como completado.
     */
    public function complete(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {

            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'pending') {
                throw new LogicException(
                    "Solo se pueden completar pagos en estado 'pending'. Estado actual: '{$payment->status}'."
                );
            }

            $payment->update([
                'status'  => 'completed',
                'paid_at' => now(),
            ]);

            // Recargar la orden desde la BD para tener totales actualizados
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);
            $this->checkAndAutoConfirm($order);

            return $payment->fresh();
        });
    }

    /**
     * Marca un pago pendiente como fallido.
     */
    public function fail(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {

            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'pending') {
                throw new LogicException(
                    "Solo se pueden marcar como fallidos pagos en estado 'pending'. Estado actual: '{$payment->status}'."
                );
            }

            $payment->update(['status' => 'failed']);

            return $payment->fresh();
        });
    }

    /**
     * Reembolsa un pago completado.
     * Requiere que la orden tenga al menos una devolución en estado 'approved' o 'resolved'.
     */
    public function refund(Payment $payment, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($payment, $notes) {

            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'completed') {
                throw new LogicException(
                    "Solo se pueden reembolsar pagos en estado 'completed'. Estado actual: '{$payment->status}'."
                );
            }

            $hasApprovedReturn = OrderReturn::where('order_id', $payment->order_id)
                ->whereIn('status', ['approved', 'resolved'])
                ->exists();

            if (!$hasApprovedReturn) {
                throw new LogicException(
                    'Para reembolsar un pago debe existir una devolución aprobada o resuelta para esta orden.'
                );
            }

            $payment->update(['status' => 'refunded']);

            return $payment->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Consultas de balance
    // -------------------------------------------------------------------------

    /**
     * Retorna un resumen del balance financiero de la orden.
     */
    public function getOrderBalance(Order $order): array
    {
        $completed = (float) Payment::forOrder($order->id)->completed()->sum('amount');
        $pending   = (float) Payment::forOrder($order->id)->pending()->sum('amount');
        $refunded  = (float) Payment::forOrder($order->id)->refunded()->sum('amount');
        $total     = (float) $order->total_amount;

        return [
            'total_amount'    => $total,
            'amount_paid'     => $completed,
            'amount_pending'  => max(0, $total - $completed),
            'amount_in_escrow'=> $pending,
            'amount_refunded' => $refunded,
            'is_fully_paid'   => ($total - $completed) <= 0,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Auto-confirma la orden si el total de pagos completados cubre el monto total
     * y la orden sigue en estado 'pending'.
     * Solo confirma el estado; la reserva de stock la maneja OrderService::transition().
     */
    private function checkAndAutoConfirm(Order $order): void
    {
        if ($order->status !== 'pending') {
            return;
        }

        $paid  = (float) Payment::forOrder($order->id)->completed()->sum('amount');
        $total = (float) $order->total_amount;

        if ($paid >= $total) {
            // Usar OrderService para respetar la lógica de transición y registro de handler
            app(OrderService::class)->transition(
                $order,
                'confirmed',
                $order->user ?? new \App\Models\User(['name' => 'Sistema', 'id' => null]),
                'Confirmación automática: pago total recibido.'
            );
        }
    }

    private function assertOrderAcceptsPayments(Order $order): void
    {
        if (in_array($order->status, ['cancelled', 'returned'])) {
            throw new LogicException(
                "No se pueden registrar pagos para una orden en estado '{$order->status}'."
            );
        }

        if ($order->status === 'delivered' && $this->getOrderBalance($order)['is_fully_paid']) {
            throw new LogicException('Esta orden ya está completamente pagada.');
        }
    }
}
