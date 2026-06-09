# ADR-012: Recebimentos Parciais e Múltiplas Conferências de Compras

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo E — Estoque Completo e Pedidos de Compra

---

## Contexto

Fornecedores frequentemente entregam pedidos de compra de forma fracionada (por falta de estoque de algum item ou capacidade logística). O sistema deve ser capaz de registrar essas entregas parciais de forma precisa, sem obrigar o operador a cancelar o pedido original ou recriar itens para as quantidades não entregues.

## Decisão

**Estratégia escolhida: Suporte a múltiplas conferências físicas (`inventory_conferences`) por Pedido de Compra e cálculo dinâmico de saldo.**

1. Um `PurchaseOrder` pode possuir múltiplos registros filhos de `InventoryConference`.
2. O saldo pendente de entrega de cada produto em um Pedido de Compra é calculado dinamicamente como:
   $$\text{Saldo Pendente} = \text{Quantidade Pedida} - \sum \text{Quantidade Recebida nas Conferências Finalizadas}$$
3. Ciclo de vida da compra:
   - O pedido é enviado ao fornecedor (status passa a `ordered`).
   - A cada entrega física do fornecedor, uma nova `InventoryConference` é criada com status `pending`.
   - Os itens esperados na conferência representam o saldo pendente atual de cada produto.
   - O operador realiza a contagem física e preenche as quantidades recebidas (`quantity_received`).
   - Ao concluir a conferência (`completed` ou `divergent`):
     - As quantidades realmente recebidas geram movimentações do tipo `input` no estoque físico.
     - Se todos os itens do pedido de compra foram entregues em sua totalidade (saldo pendente = 0), a `PurchaseOrder` muda de status para `received` (concluído).
     - Se restar saldo pendente em qualquer item, a `PurchaseOrder` passa a ter status `partially_received` e permanece aberta para novas conferências.

## Consequências

- Rastreabilidade precisa: sabemos exatamente o que foi entregue em cada remessa.
- Menos burocracia: não há necessidade de desmembrar pedidos de compra manualmente na retaguarda.
- Aumento no controle de divergências físicas por remessa entregue pelo fornecedor.
