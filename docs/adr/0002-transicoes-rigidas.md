# ADR 0002 - Transições Rígidas de Status

* **Status**: Aprovado
* **Data**: 2026-06-04
* **Autor**: Antigravity

## Contexto

Em operações de campo e prestação de serviços, a conformidade de processos é vital. Os técnicos e operadores não devem ter permissão de mover uma Ordem de Serviço arbitrariamente entre quaisquer estados. Por exemplo, concluir uma OS sem que ela tenha passado pelo estado de atendimento em campo que aciona o preenchimento de checklists obrigatórios e registro de geolocalização.

Além disso, regras operacionais como a baixa física de peças em estoque e faturamento dependem da transição exata para o estado de conclusão.

### Alternativas Consideradas

* **Alternativa A**: Validação de transição rígida em código (hardcoded no PHP).
  * *Prós*: Fácil de programar no início.
  * *Contras*: Impossibilita o administrador de configurar fluxos alternativos ou customizar as transições de novos status criados.
* **Alternativa B**: Permitir transições livres no sistema.
  * *Prós*: Simplicidade extrema de implementação.
  * *Contras*: Risco de quebra de regras fiscais e de estoque. Ausência de controle de processo corporativo.
* **Alternativa C**: Tabela de transições no banco de dados (`service_order_status_transitions`).
  * *Prós*: O administrador gerencia quais transições são válidas diretamente no painel. O backend valida a transição dinamicamente a cada mudança.
  * *Contras*: Necessidade de interface intuitiva para configurar a matriz de transições e pequena sobrecarga de consulta no banco.

## Decisão

Adotamos a **Alternativa C**. O banco de dados terá a tabela `service_order_status_transitions` associando `from_status_id` a `to_status_id`.
* Qualquer tentativa de alteração de status através do `ServiceOrderService` será interceptada e validada contra esta tabela.
* Se a transição proposta não existir na tabela, a alteração será bloqueada e uma exceção de validação (`ValidationException`) será lançada.
* O painel administrativo permitirá gerenciar essas associações de forma clara.

## Consequências

* **Positivas**:
  * Rigidez e padronização do processo em campo.
  * Flexibilidade para o administrador criar fluxos paralelos e transições específicas.
  * Facilidade para acoplar regras de validação nos módulos futuros de checklist (Módulo D) e estoque (Módulo E).
* **Negativas**:
  * Adiciona a necessidade de consultas na base de transições, o que pode ser otimizado via cache ou indexação adequada.
