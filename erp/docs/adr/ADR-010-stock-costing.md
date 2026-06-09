# ADR-010: Custeio Histórico e Preservação de Custo Unitário

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo E — Estoque Completo e Pedidos de Compra

---

## Contexto

A flutuação dos preços de compra de fornecedores afeta diretamente o valor do estoque físico e o cálculo futuro do Custo de Mercadorias Vendidas (CMV). Se o sistema armazenar apenas o custo atual no cadastro de produtos, perderemos a rastreabilidade histórica do valor real investido nas aquisições no momento de cada entrada ou consumo.

## Decisão

**Estratégia escolhida: Registro de custo unitário (`unit_cost`) individualizado por movimentação de estoque.**

1. A tabela `stock_movements` incluirá a coluna `unit_cost` (decimal 12,2).
2. Origem do custo no registro da movimentação:
   - **Conferência de Pedido de Compra (`inventory_conference`)**: O `unit_cost` gravado é exatamente o valor negociado no item correspondente de `purchase_order_items`.
   - **Ordem de Serviço (`service_order`) e Vendas (`sale`)**: O `unit_cost` gravado é o custo de aquisição (`cost_price`) do cadastro de `products` no instante da conclusão.
   - **Ajustes Manuais (`manual`)**: O operador poderá informar o custo unitário correspondente, caindo no custo atual do produto por padrão.

## Consequências

- Trilha histórica completa: o sistema sabe exatamente quanto custou cada item que entrou ou saiu.
- Flexibilidade fiscal e gerencial: viabiliza a geração de relatórios de CMV (Custo de Mercadorias Vendidas) históricos e controle de margens precisas por OS e Venda.
- Aumento de duas colunas decimais no banco, sem impacto significativo de performance.
