# ADR 0001 - Status Dinâmicos nas Ordens de Serviço

* **Status**: Aprovado
* **Data**: 2026-06-04
* **Autor**: Antigravity

## Contexto

Até o momento, as Ordens de Serviço (OS) possuíam controle de estados baseado em um Enum PHP estático (`App\Enums\ServiceOrderStatus`) com cinco valores fixados em código (`open`, `in_route`, `in_service`, `completed`, `cancelled`).

Essa abordagem apresenta limitações operacionais severas para um ERP moderno:
1. Empresas clientes necessitam de fluxos de atendimento diferentes (ex: incluir "Aguardando Peças", "Aprovado Financeiro", "Em Laboratório").
2. Adicionar novos estados exige alteração de código, novas migrations para restrições de banco de dados (se houver enums nativos) e deploy.
3. Não há possibilidade de atrelar configurações dinâmicas de SLA (tempo de permanência limite) ou metadados de apresentação (como cores) a nível de registro.

### Alternativas Consideradas

* **Alternativa A**: Manter o Enum estático e simular status adicionais por tags dinâmicas.
  * *Prós*: Sem alteração estrutural nas tabelas existentes.
  * *Contras*: Lógica propensa a erros, duplicação e complexidade desnecessária nas queries de filtragem.
* **Alternativa B**: Abandonar o Enum e migrar para tabela de status no banco de dados (`service_order_statuses`).
  * *Prós*: Flexibilidade total, suporte a SoftDeletes para integridade histórica, metadados (cor, SLA) no banco, regras genéricas baseadas em flags booleanas (`is_open_state`, `is_completed_state`, `is_cancelled_state`).
  * *Contras*: Maior complexidade na migração inicial dos dados existentes e necessidade de refatorar queries antigas baseadas em strings.

## Decisão

Adotamos a **Alternativa B**. O enum `ServiceOrderStatus` será descontinuado para fins de persistência direta nas tabelas. Em seu lugar, as Ordens de Serviço possuirão uma chave estrangeira `status_id` apontando para `service_order_statuses`.

Para evitar o acoplamento do código de negócio às strings visuais dos status:
1. Usaremos flags booleanas na tabela (`is_open_state`, `is_completed_state`, `is_cancelled_state`).
2. Garante-se por validação de banco/aplicação que existirá exatamente um status com `is_completed_state = true` e exatamente um com `is_cancelled_state = true`.
3. Qualquer validação de estoque, faturamento ou contabilidade fará referência a essas flags, blindando o backend contra renomeação de status pelo usuário.

## Consequências

* **Positivas**:
  * Flexibilidade para o usuário customizar seu fluxo operacional.
  * Código limpo e genérico, preparado para o faturamento automático no módulo financeiro (Módulo F) e estoque (Módulo E).
  * Preservação da integridade histórica dos dados através de SoftDeletes nos status.
* **Negativas**:
  * É necessário refatorar os testes, seeders e controladores para utilizar a relação de `status` e carregar o ID correspondente.
