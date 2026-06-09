# ADR-009: Controle e Configuração de Estoque Negativo

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo E — Estoque Completo e Pedidos de Compra

---

## Contexto

A operação de campo (técnicos realizando serviços) e vendas comerciais podem exigir que um produto seja baixado imediatamente para não bloquear o fluxo de atendimento ou faturamento, mesmo que a entrada física do produto no sistema (conferência do pedido de compra) ainda não tenha sido registrada pelo operador. No entanto, algumas empresas operam sob políticas rígidas onde o estoque não pode ficar negativo em hipótese alguma para fins contábeis e fiscais.

## Decisão

**Estratégia escolhida: Controle configurável por empresa (`allow_negative_stock` na tabela `companies`).**

1. A tabela `companies` receberá a coluna `allow_negative_stock` (boolean, default `false`).
2. Se `allow_negative_stock` for `false` (padrão):
   - O `StockMovementService` validará antes de realizar a movimentação.
   - Se o saldo do produto for ficar menor que zero, a movimentação é rejeitada, lançando um `ValidationException`.
3. Se `allow_negative_stock` for `true`:
   - A movimentação é executada normalmente.
   - O saldo físico do produto passará a ser negativo no banco.
   - No Kardex (extrato de movimentações), a linha correspondente exibirá um alerta vermelho chamativo indicando inconsistência temporária de estoque.

## Consequências

- Flexibilidade de operação: empresas que preferem agilidade podem permitir estoque negativo.
- Integridade total: empresas com rigor contábil têm a garantia de que saídas sem saldo correspondente serão bloqueadas.
- O controle é verificado de forma centralizada e atômica no `StockMovementService`.
