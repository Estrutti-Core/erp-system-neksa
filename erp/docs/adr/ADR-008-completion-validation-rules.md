# ADR-008: Regras de Bloqueio de Conclusão da OS

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo D — Ordens de Serviço, Anexos e Checklists

---

## Contexto

Para garantir consistência operacional, a transição de uma OS para um status de conclusão (`is_completed_state = true`) deve ser bloqueada enquanto critérios mínimos de execução não forem atendidos.

## Decisão

A validação é executada em `ServiceOrderService::changeStatus()` **antes** da transação de banco.

### Critérios de Bloqueio (em ordem de verificação)

| # | Critério | Mensagem de Erro |
|---|---|---|
| 1 | Transição permitida pelo fluxo de status | "Não é possível alterar o status de X para Y." |
| 2 | Todos os checklists ativos preenchidos (`filled_at IS NOT NULL`) | "Existem checklists ativos pendentes de preenchimento." |
| 3 | Todas as perguntas obrigatórias respondidas | "Existem perguntas obrigatórias sem resposta." |
| 4 | Assinatura do cliente coletada | "A assinatura do cliente ainda não foi coletada." |
| 5 | Check-in registrado | "Nenhum check-in de chegada foi registrado." |

### O que NÃO bloqueia

- Checklists marcados como `is_inactive = true` (evidências preservadas de serviços removidos).
- Perguntas do tipo `label` (são informativas, não precisam de resposta).

## Consequências

- O técnico não consegue concluir uma OS sem completar o protocolo operacional completo.
- Erros são apresentados de forma clara e específica, indicando exatamente o que está pendente.
- A lógica centralizada em `ServiceOrderService` garante que a regra se aplica tanto via web quanto via API futura.
