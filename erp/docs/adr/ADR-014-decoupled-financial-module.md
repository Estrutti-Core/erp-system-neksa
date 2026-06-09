# ADR-014: Desacoplamento do Módulo Financeiro via Polimorfismo

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo F — Financeiro Completo (Contas a Receber/Pagar)

---

## Contexto

O ERP Neksa está crescendo e necessita de um controle financeiro completo de Contas a Receber e Contas a Pagar. Porém, o ERP tem como foco a gestão operacional de Ordens de Serviço (OS) externas, Vendas comerciais e Pedidos de Compra.
No futuro, o sistema poderá gerenciar outros fatos geradores de receita ou despesa, como Contratos Recorrentes, Assinaturas, Locações ou lançamentos manuais diretos.

Se utilizarmos Chaves Estrangeiras (FKs) diretas para `service_orders`, `sales` ou `purchase_orders` nas tabelas financeiras, geraremos um alto acoplamento (tight coupling). Isso tornará o módulo financeiro dependente de tabelas operacionais específicas, dificultando a extensão do ERP para novos tipos de faturamentos e poluindo a modelagem com colunas nulas.

## Opções Avaliadas

1. **Chaves Estrangeiras Diretas (FKs):**
   - *Vantagens:* Integridade referencial garantida pelo banco de dados PostgreSQL.
   - *Desvantagens:* Alto acoplamento, tabelas financeiras com muitas colunas de chaves estrangeiras anuláveis (`service_order_id`, `sale_id`, `purchase_order_id`, `contract_id`, etc.), complexidade para adicionar novos tipos de faturamento.

2. **Estrutura por Mensageria/Eventos de Integração:**
   - *Vantagens:* Desacoplamento extremo e assíncrono.
   - *Desvantagens:* Sobrecarga de complexidade (event loops, brokers) para uma aplicação monolítica PHP/Laravel de pequeno a médio porte, aumentando a possibilidade de inconsistências temporárias e rastreamento complexo.

3. **Estrutura Polimórfica (`source_type` + `source_id`):**
   - *Vantagens:* Desacoplamento das tabelas financeiras, capacidade ilimitada de estender novos modelos geradores de obrigações (OS, Vendas, Compras, Contratos), modelagem enxuta e limpa.
   - *Desvantagens:* Integridade física no banco de dados não pode ser garantida por FKs nativas (resolvido via validações em nível de serviço/application layer).

## Decisão

**Estratégia escolhida: Modelagem Polimórfica Desacoplada (`source_type` + `source_id`) com numeração de códigos próprios (`code` único).**

1. As tabelas `receivables` e `payables` não conterão nenhuma chave estrangeira direta para entidades operacionais.
2. O relacionamento com a origem do título será polimórfico usando as colunas `source_type` e `source_id` (anuláveis para lançamentos manuais avulsos).
3. Os títulos possuirão numeração de código própria e única formatada em strings amigáveis para comunicação externa (`REC-YYYY-000001` e `PAY-YYYY-000001`).
4. Todas as transições de ciclo de vida e a geração de log em trilha de auditoria específica (`financial_events`) serão governados pelo `FinancialService` de forma isolada, protegendo contra duplicidades (idempotência).

## Consequências

- **Extensibilidade:** Qualquer novo módulo operacional poderá gerar obrigações financeiras simplesmente registrando um novo tipo no polimorfismo.
- **Isolamento de Negócio:** As regras operacionais de OS e Compras são completamente cegas para as regras internas do financeiro (baixa de parcelas, juros, descontos, conciliação).
- **Sem chaves físicas:** A integridade referencial para exclusões deve ser tratada defensivamente via Policies e regras na camada de serviço (ex: bloquear a exclusão de uma OS se o título correspondente já possuir parcelas liquidadas).
