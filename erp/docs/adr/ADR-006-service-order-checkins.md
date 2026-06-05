# ADR-006: Modelagem de Check-in Operacional

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo D — Ordens de Serviço, Anexos e Checklists

---

## Contexto

Técnicos em campo precisam registrar a chegada ao local de atendimento com GPS para rastreabilidade operacional. No futuro, será necessário calcular tempo em campo e gerar relatórios de produtividade.

## Decisão

Tabela dedicada `service_order_checkins` com suporte a múltiplos eventos por OS:

| Campo | Tipo | Propósito |
|---|---|---|
| `service_order_id` | FK | Vinculação à OS |
| `user_id` | FK | Técnico que fez o evento |
| `type` | string | `checkin` ou `checkout` |
| `latitude` | decimal(10,7) | Coordenada GPS |
| `longitude` | decimal(10,7) | Coordenada GPS |
| `notes` | text nullable | Observação opcional |
| `checked_at` | timestamp | Momento do evento |

**Check-in obrigatório para conclusão:** Uma OS só pode ser finalizada se houver ao menos um evento do tipo `checkin` registrado.

## Alternativas Consideradas

- **Campos diretos em `service_orders`**: Simples, mas impede múltiplos eventos e histórico.
- **Tabela dedicada (escolhida)**: Permite check-in/checkout, cálculo de tempo em campo e relatórios futuros.

## Consequências

- Possibilita futuros relatórios de: tempo médio de atendimento, produtividade por técnico, mapa de calor de atendimentos.
- A API mobile poderá registrar check-in/checkout via GPS automaticamente.
- Auditoria via Spatie Activity Log.
