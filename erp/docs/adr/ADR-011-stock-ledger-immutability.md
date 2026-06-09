# ADR-011: Imutabilidade e Ledger de Movimentações de Estoque

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo E — Estoque Completo e Pedidos de Compra

---

## Contexto

Alterações ou exclusões retroativas de movimentações de estoque podem desalinhar completamente o saldo atual de produtos em relação ao histórico de auditoria. Isso dificulta a detecção de desvios físicos de mercadorias e invalida qualquer tentativa de auditoria gerencial.

## Decisão

**Estratégia escolhida: Imutabilidade absoluta da tabela `stock_movements` (Livro Razão / Ledger).**

1. Registros de `stock_movements` **nunca** serão excluídos (`delete`) ou alterados (`update`) via lógica de negócio do sistema.
2. Cada movimentação física de estoque deverá registrar de forma atômica e imutável as colunas:
   - `stock_before`: o saldo do produto imediatamente anterior à movimentação.
   - `stock_after`: o saldo do produto imediatamente posterior à movimentação.
3. Se um operador cometer um erro de digitação ou se uma transição for revertida:
   - Lança-se um novo registro de movimentação de ajuste (contra-lançamento) com valor inverso para estornar o saldo físico.
   - O motivo do estorno é gravado no campo `notes`.

## Consequências

- Segurança de Auditoria: Qualquer auditoria pode reconstruir o saldo de qualquer produto a partir da ordem cronológica dos registros, sabendo exatamente quem, quando e por que moveu cada unidade.
- Impossibilidade de "fantasmas" no estoque (erros de saldo físico que não possuem movimentações correspondentes).
