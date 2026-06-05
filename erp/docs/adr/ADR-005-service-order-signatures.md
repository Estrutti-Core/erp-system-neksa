# ADR-005: Modelagem de Assinaturas Digitais

**Data:** 2026-06-05  
**Status:** Aceito  
**Contexto:** Módulo D — Ordens de Serviço, Anexos e Checklists

---

## Contexto

A coleta de assinatura digital do cliente é evidência legal e operacional de aceite do serviço. Precisa ser auditável, rastreável e preservada indefinidamente.

## Decisão

Tabela dedicada `service_order_signatures` com:

| Campo | Tipo | Propósito |
|---|---|---|
| `service_order_id` | FK | Vinculação à OS |
| `signer_name` | string | Nome de quem assinou |
| `signer_document` | string nullable | CPF/RG para identificação |
| `path` | string | Caminho da imagem PNG da assinatura |
| `disk` | string | Disco de armazenamento (public/s3) |
| `signed_latitude` | decimal | GPS no momento da coleta |
| `signed_longitude` | decimal | GPS no momento da coleta |
| `ip_address` | string(45) | IPv4/IPv6 do dispositivo |
| `user_agent` | text | Navegador/dispositivo |
| `signed_at` | timestamp | Momento exato da coleta |

A assinatura é capturada via HTML5 Canvas com suporte a touch (mobile). O canvas é exportado como PNG base64 e salvo como arquivo no disco configurado.

## Consequências

- Rastreabilidade completa de quando, onde e em qual dispositivo a assinatura foi coletada.
- `ip_address` e `user_agent` permitem investigação forense em caso de disputas.
- Uma OS pode ter no máximo uma assinatura (HasOne). Re-assinar substitui a anterior.
- Auditoria via Spatie Activity Log registra coleta e exclusão.
