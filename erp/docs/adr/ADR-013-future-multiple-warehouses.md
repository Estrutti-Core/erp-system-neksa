# ADR-013: Estratégia de Preparação para Múltiplas Localizações de Estoque

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo E — Estoque Completo e Pedidos de Compra

---

## Contexto

Embora a versão atual do Neksa ERP gerencie apenas um estoque geral centralizado, prevemos a necessidade futura de controlar estoque por depósitos múltiplos, almoxarifados físicos e depósitos móveis (estoque no veículo de cada técnico de campo). A modelagem de dados atual deve nascer preparada para essa expansão sem exigir alterações profundas nas tabelas de movimentações de estoque ou tabelas de relacionamento.

## Decisão

**Estratégia escolhida: Modelagem de movimentações de estoque extensível via coluna de localização física (`warehouse_id` nullable).**

1. A tabela `stock_movements` possuirá a coluna `warehouse_id` (unsignedBigInteger, nullable), preparada para referenciar uma tabela futura `warehouses`.
2. Nesta versão inicial:
   - A coluna `warehouse_id` será criada como `nullable`.
   - O sistema tratará todas as movimentações sob o depósito geral implícito (que pode ser considerado o `id = null` ou omitido).
3. Quando o controle de múltiplos depósitos for introduzido:
   - Criaremos a tabela `warehouses`.
   - O saldo físico de produtos poderá ser consultado consolidando as movimentações agrupadas por `product_id` e `warehouse_id`, ou criando uma tabela de pivot `product_warehouse` para cache de saldo rápido por depósito.

## Consequências

- Extensibilidade garantida: A trilha de movimentações de estoque (`stock_movements`) não precisará ser refatorada ou migrada futuramente.
- Código desacoplado: O `StockMovementService` já poderá aceitar um parâmetro opcional `warehouseId`, preparando a lógica de movimentação para o futuro.
