# ADR-015: Modelagem de Parcelas e Baixas Parciais

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo F — Financeiro Completo

---

## Contexto

A liquidação de parcelas financeiras no contas a receber e a pagar do ERP Neksa exige o tratamento de baixas parciais (o cliente paga apenas uma parte do valor da parcela). Devemos decidir como modelar essas baixas e o saldo restante da parcela para fins de fluxo de caixa e auditoria contábil.

## Alternativas Avaliadas

1. **Desmembramento Automático (Split de Parcela):**
   - *Funcionamento:* Ao receber R$ 40,00 de uma parcela de R$ 100,00, a parcela original é marcada como totalmente paga (R$ 40,00) e o sistema gera automaticamente uma nova parcela pendente de R$ 60,00 com o mesmo vencimento.
   - *Vantagens:* Mantém as parcelas sempre em status binário (Paid ou Pending), facilitando relatórios simples.
   - *Desvantagens:* Altera o número original de parcelas (uma compra parcelada em 3 vezes pode virar 4 ou 5 parcelas no banco), dificultando a conciliação com contratos originais e poluindo a base de dados com registros dinâmicos gerados por transação.

2. **Ledger Financeiro / Tabela de Lançamentos de Baixa (Transacionais):**
   - *Funcionamento:* As parcelas são estáticas e as baixas parciais/totais são registradas em uma tabela secundária `financial_transactions` vinculada à parcela. O saldo pendente da parcela é a diferença entre o valor total da parcela e a soma de suas transações associadas.
   - *Vantagens:* Alta fidelidade histórica e contábil, permitindo múltiplos recebimentos na mesma parcela com datas e meios de pagamento diferentes.
   - *Desvantagens:* Aumenta a complexidade de queries e performance em relatórios básicos, exigindo constantes joins e agregações para calcular o saldo pendente.

3. **Acúmulo na Própria Parcela (Abordagem Pragmática):**
   - *Funcionamento:* A tabela de parcelas contém colunas para `amount` (valor original), `paid_amount` (valor pago acumulado) e `status` ('pending', 'paid', 'cancelled'). No caso de baixa parcial, o status da parcela é mantido como 'pending' (ou estendido a nível de cabeçalho do título como 'partially_paid'), e o valor pago é incrementado. A quitação total ocorre quando `paid_amount` atinge o valor total (ajustado por descontos/juros).
   - *Vantagens:* Simplicidade extrema, mantendo a estrutura de parcelas intacta e queries de faturamento fáceis de performar.
   - *Desvantagens:* Limita a riqueza de dados caso uma única parcela seja paga em múltiplos Pix em dias diferentes (apenas a última data de pagamento e meio de pagamento são armazenados diretamente na parcela).

## Decisão

**Estratégia escolhida: Acúmulo direto na parcela com suporte a auditoria detalhada via `financial_events`.**

1. A parcela de contas a receber/pagar terá os campos `amount` (valor nominal), `paid_amount` (total pago acumulado), `discount_amount` (desconto concedido), `interest_amount` (juros cobrados) e `paid_at` (data do último pagamento).
2. O status físico da parcela permanece `pending` se o pagamento for parcial e vira `paid` quando o valor quitado (somado a descontos) cobrir o valor nominal (mais juros).
3. Cada recebimento (mesmo que parcial) gerará um registro detalhado e imutável na tabela `financial_events` (com tipo `partial_payment` ou `full_payment`), garantindo que o histórico transacional completo de pagamentos parciais em datas distintas esteja preservado para auditorias de caixa.

## Consequências

- **Fidelidade Operacional:** O número de parcelas acordadas com o cliente nunca muda.
- **Rastreabilidade:** Embora a parcela mostre apenas o consolidado pago mais recente, o log `financial_events` guarda o histórico exato de cada centavo que entrou e saiu e quem executou a ação.
- **Simplicidade Contábil:** O cálculo de saldo a receber e a pagar é performático, pois não exige tabelas transacionais extras para queries simples de listagem.
