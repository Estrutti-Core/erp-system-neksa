# ADR 0003 - SLA por Status e Histórico de Permanência

* **Status**: Aprovado
* **Data**: 2026-06-04
* **Autor**: Antigravity

## Contexto

Para o gerenciamento operacional de ordens de serviço externas, o cumprimento de prazos combinados com o cliente (SLA - Service Level Agreement) é um fator de sucesso crítico. Medir quanto tempo uma OS permanece em cada etapa do processo e emitir alertas preditivos ou reativos é fundamental para detectar gargalos operacionais e evitar atrasos.

### Alternativas Consideradas

* **Alternativa A**: Registrar tempos diretamente na tabela principal de Ordens de Serviço (colunas extras como `started_at`, `completed_at`).
  * *Prós*: Sem necessidade de tabelas secundárias.
  * *Contras*: Não escala se a OS oscilar de status múltiplas vezes ou retornar a um estado anterior. Perde a granularidade histórica por etapa.
* **Alternativa B**: Criar um histórico de auditoria textual genérico.
  * *Prós*: Fácil leitura em timeline visual.
  * *Contras*: Dificulta cálculos matemáticos automatizados e geração de indicadores de performance em dashboards.
* **Alternativa C**: Implementar uma tabela dedicada de permanência de status (`service_order_status_history`) e um motor de verificação de SLA em background.
  * *Prós*: Medição precisa do tempo em minutos por status com colunas estruturadas (`entered_at`, `left_at`, `duration_minutes`). Facilita a auditoria e permite disparar alertas de SLA excedido via Command/Job programado.
  * *Contras*: Requer lógica rigorosa no Service de OS para gerenciar e encerrar os registros do histórico de status a cada transição.

## Decisão

Adotamos a **Alternativa C**.
1. **Histórico Estruturado**: Criaremos a tabela `service_order_status_history` que monitora as transições com marcação temporal de entrada, saída, duração e usuário responsável.
2. **Eventos de Domínio**: Sempre que ocorrer uma transição de status, dispararemos os eventos `ServiceOrderStatusChanged` e `ServiceOrderEnteredStatus`.
3. **Motor de SLA**: Uma infraestrutura baseada em console command (`CheckServiceOrderSlaCommand`) executará periodicamente no Scheduler do Laravel para verificar se o tempo atual de permanência de qualquer OS ativa excede o limite estipulado (`max_stay_minutes`) no status configurado.
4. **Alerta**: Caso o SLA de permanência seja violado, o motor dispara o evento `ServiceOrderSlaExceeded` para que módulos de notificação (ex: e-mail, WhatsApp, push) ajam de forma assíncrona.

## Consequências

* **Positivas**:
  * Altamente acoplado à rastreabilidade operacional exigida por grandes clientes.
  * Preparado para alimentar dashboards gerenciais, gráficos de produtividade de técnicos e tempo médio por atendimento.
  * Notificações futuras de atrasos serão reativas e orientadas a eventos (Decoupled).
* **Negativas**:
  * Incremento do número de escritas no banco de dados a cada alteração de status.
  * Necessidade de configuração do cronjob do Laravel Scheduler no ambiente de produção.
