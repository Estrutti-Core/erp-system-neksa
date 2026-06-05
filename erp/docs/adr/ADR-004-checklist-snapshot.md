# ADR-004: Estratégia de Snapshot de Checklists

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo D — Ordens de Serviço, Anexos e Checklists

---

## Contexto

Os templates de checklist são configurados por administradores e podem ser alterados a qualquer momento. Uma OS criada hoje precisa manter exatamente as perguntas existentes no momento de sua criação, independente de alterações futuras no template.

## Decisão

**Estratégia escolhida: Instanciação completa das perguntas (Full Snapshot).**

Ao criar um `ServiceOrderChecklist`, o sistema copia todas as perguntas do template para a tabela `service_order_checklist_questions`. Esta tabela contém um snapshot imutável das perguntas no momento da instanciação.

```
Template (checklist_templates + checklist_questions)
    ↓ no momento da criação da OS
Instância (service_order_checklists + service_order_checklist_questions)
```

A partir do snapshot:
- A OS nunca mais lê diretamente de `checklist_questions` para exibir as perguntas.
- Respostas (`checklist_answers`) referenciam `service_order_checklist_question_id` (instância).
- A coluna `checklist_question_id` em `service_order_checklist_questions` é mantida como referência opcional para rastreabilidade, com `nullOnDelete`.

## Alternativas Consideradas

| Opção | Prós | Contras |
|---|---|---|
| Versionamento de templates | Sem duplicação de dados | Complexidade alta de controle de versão |
| Snapshot em JSON | Simples | Difícil de consultar/filtrar por pergunta |
| **Instanciação completa (escolhida)** | Queries simples, integridade referencial | Mais dados no banco |

## Consequências

- Alterações em templates não afetam OS já existentes.
- O banco de dados cresce proporcionalmente com o volume de OS e perguntas por template.
- Para rastrear a origem de uma pergunta instanciada, consultar `checklist_question_id` (nullable).
