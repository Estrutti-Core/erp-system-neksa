# ADR-007: Estratégia de Anexos Locais

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo D — Ordens de Serviço, Anexos e Checklists

---

## Contexto

Técnicos em campo precisam enviar fotos e documentos durante o atendimento. Os anexos precisam ter metadados suficientes para auditoria, segurança e futuras validações.

## Decisão

Tabela `service_order_attachments` (renomeada de `service_order_photos`) com metadados completos:

| Campo | Tipo | Propósito |
|---|---|---|
| `service_order_id` | FK | Vinculação à OS |
| `uploaded_by` | FK (users) | Técnico que enviou |
| `path` | string | Caminho no disco |
| `original_name` | string nullable | Nome original do arquivo |
| `disk` | string | Disco (public/s3) |
| `type` | string | `before`, `after`, `general` |
| `caption` | string nullable | Legenda do arquivo |
| `size` | bigint nullable | Tamanho em bytes |
| `mime_type` | string nullable | Tipo MIME validado |

**Validações no upload:**
- MIME types permitidos: `image/*`, `application/pdf`, `video/*`
- Tamanho máximo: 10MB por arquivo
- Armazenamento: disco `public` (configurável para S3 futuramente)

## Consequências

- `mime_type` permite validar e categorizar arquivos no frontend.
- `size` permite relatórios de consumo de espaço por OS.
- `uploaded_by` permite auditoria de quem enviou cada arquivo.
- Auditoria via Spatie Activity Log registra upload e exclusão física.
