<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Company;
use App\Models\User;
use App\Enums\StockMovementType;
use App\Enums\StockMovementSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /**
     * Registra uma movimentação física de estoque de forma atômica e segura.
     *
     * @param Product $product Produto a ser movimentado
     * @param float $quantity Quantidade (positiva para entrada, negativa para saída)
     * @param StockMovementType|string $type Tipo de movimentação (input, output)
     * @param StockMovementSource|string $sourceType Origem da movimentação
     * @param int|null $sourceId ID da origem
     * @param User|null $user Usuário responsável
     * @param string|null $notes Observações adicionais
     * @param float|null $unitCost Custo unitário histórico
     * @param int|null $warehouseId ID do depósito
     * @return StockMovement|null
     * @throws ValidationException
     */
    public function move(
        Product $product,
        float $quantity,
        StockMovementType|string $type,
        StockMovementSource|string $sourceType,
        ?int $sourceId = null,
        ?User $user = null,
        ?string $notes = null,
        ?float $unitCost = null,
        ?int $warehouseId = null
    ): ?StockMovement {
        // Se o produto não for controlado por estoque, ignora
        if (!$product->is_stock_controlled) {
            return null;
        }

        // Converte strings para Enums se necessário
        $enumType = is_string($type) ? StockMovementType::from($type) : $type;
        $enumSource = is_string($sourceType) ? StockMovementSource::from($sourceType) : $sourceType;

        return DB::transaction(function () use ($product, $quantity, $enumType, $enumSource, $sourceId, $user, $notes, $unitCost, $warehouseId) {
            // Lock para concorrência
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $stockBefore = (float) $lockedProduct->stock;
            $stockAfter = $stockBefore + (float) $quantity;

            // Validação de Estoque Negativo (ADR-009)
            if ($stockAfter < 0) {
                $company = Company::first();
                $allowNegative = $company ? (bool) $company->allow_negative_stock : false;

                if (!$allowNegative) {
                    throw ValidationException::withMessages([
                        'stock' => "Estoque insuficiente para o produto '{$lockedProduct->name}'. Saldo atual: {$stockBefore}, solicitado: " . abs($quantity) . ".",
                    ]);
                }
            }

            // Define custo histórico da movimentação (ADR-010)
            $resolvedCost = $unitCost ?? (float) ($lockedProduct->cost_price ?? 0);

            // Cria o registro da movimentação de estoque (Ledger Imutável - ADR-011)
            $movement = StockMovement::create([
                'product_id'   => $lockedProduct->id,
                'user_id'      => $user?->id,
                'warehouse_id' => $warehouseId,
                'quantity'     => $quantity,
                'stock_before' => $stockBefore,
                'stock_after'  => $stockAfter,
                'unit_cost'    => $resolvedCost,
                'type'         => $enumType,
                'source_type'  => $enumSource,
                'source_id'    => $sourceId,
                'notes'        => $notes,
            ]);

            // Atualiza o estoque do produto
            $lockedProduct->stock = $stockAfter;
            $lockedProduct->save();

            return $movement;
        });
    }
}
