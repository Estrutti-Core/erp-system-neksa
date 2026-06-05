# ROADMAP DO PROJETO - ERP NEKSA

Este arquivo serve como o guia oficial de progresso para o desenvolvimento dos novos módulos do ERP Neksa. Ele detalha as funcionalidades de cada módulo, correções necessárias e o estado de desenvolvimento atual, permitindo que diferentes sessões do assistente continuem o trabalho com contexto consistente.

---

## Fluxo de Desenvolvimento e Dependências

O desenvolvimento do sistema segue uma ordem lógica onde os cadastros base e as ordens de serviço (fontes operacionais) são concluídos antes do módulo financeiro e estoque, reduzindo o acoplamento e retrabalhos.

Ordem de Desenvolvimento:
Modulo A -> Modulo B -> Modulo C -> Modulo D -> Modulo E -> Modulo F -> Modulo G

---

## Módulos e Status de Desenvolvimento

### Modulo A: Serviços, Clientes PJ, CNAEs e Contatos
* **Status**: CONCLUIDO
* **Entregas**:
  * Tabela `services` e CRUD de Serviços independente de produtos (com campos fiscais: CFOP, CST, taxas de ISS, PIS, COFINS, CSLL, INSS).
  * Tabela `cnaes`, tabela pivô `client_cnaes` e tabela `client_contacts`.
  * Atualização da tabela de Clientes com campos PJ específicos (Razão Social, Nome Fantasia, Data de Abertura, Natureza Jurídica, Capital Social, CNAE Principal/Secundários).
  * Serviço `CnpjaService` que faz a consulta automatizada de CNPJ (com cache em banco e fallbacks automáticos para BrasilAPI e dados mockados).
  * Busca automática de CNPJ integrada no formulário de criação/edição do cliente (disparada ao digitar 14 dígitos).
  * Configuração de permissões e Policies de acesso para Serviços.

### Modulo B: Equipamentos, Orçamentos e PDFs Inline
* **Status**: CONCLUIDO
* **Entregas**:
  * Tabela `client_equipments` e relacionamento com clientes.
  * Cadastro e edição de Equipamentos diretamente na tela de visualização do cliente (CRUD com modais assíncronos) e nos formulários de criação/edição de clientes.
  * Vinculação de `equipment_id` nas tabelas `service_orders` e `quotes`.
  * Seletor dinâmico de equipamentos nos formulários de OS e Orçamentos (filtrados por cliente via Fetch API).
  * Duplicação de Ordens de Serviço (reinicia fluxo de status, zera histórico, remove assinaturas/anexos).
  * Geração e streaming de PDFs (Quotes, Sales e Service Orders) diretamente no navegador (inline) em vez de download automático imediato.
  * Padronização do nome do arquivo PDF gerado no formato `NOME_DO_CLIENTE-{ORC|VENDA|OS}-NUMERO.pdf` (com normalização de caracteres).
  * Copiar automaticamente o `equipment_id` do Orçamento para a OS no fluxo de conversão.

### Modulo C: Status Customizados, SLA e Transições
* **Status**: CONCLUIDO
* **Entregas**:
  * Tabela `service_order_statuses` para armazenar status customizáveis (com flags booleanas de sistema: `is_system`, `is_open_state`, `is_completed_state`, `is_cancelled_state`, cores e tempos limite de SLA).
  * Tabela `service_order_status_transitions` regulando as transições de status válidas configuradas pelo administrador.
  * Tabela `service_order_status_history` para auditoria operacional de permanência e monitoramento de SLA por status.
  * Lógica operacional de transição e duplicação integrada no `ServiceOrderService` e `ConvertQuoteAction`.
  * Comando console command agendado `service-orders:check-sla` para detecção de estouro de SLA e disparo de alertas/eventos.
  * Painel administrativo completo para CRUD de status e regras de transição (exclusividade de status sistêmicos e bloqueio de exclusão).

### Modulo D: Ordens de Serviço, Anexos e Checklists
* **Status**: PROXIMO
* **Funcionalidades Planejadas**:
  * Tabelas de checklist (`checklist_templates`, `checklist_questions`, `service_order_checklists`, `checklist_answers`).
  * Tabela pivô `service_type_checklists` ligando Serviços a Modelos de Checklist.
  * Inclusão de Checklists obrigatórios em Ordens de Serviço com base nos serviços vinculados.
  * Expansão da tabela de fotos da OS para `service_order_attachments` permitindo upload de múltiplos arquivos (mídias locais).
  * Assinatura digital do cliente e preenchimento de checklists via interface responsiva mobile-first para o técnico.

### Modulo E: Estoque Completo e Pedidos de Compra
* **Status**: PENDENTE
* **Funcionalidades Planejadas**:
  * Tabelas `purchase_orders`, `purchase_order_items`, `inventory_conferences` e `stock_movements`.
  * Baixa automática em estoque físico ao concluir OS (`is_completed_state = true`) ou gerar venda.
  * Estorno automático em estoque ao cancelar OS (`is_cancelled_state = true`) ou venda.
  * Fluxo de Pedido de Compra -> Recebimento -> Entrada em estoque com conferência.

### Modulo F: Financeiro Completo (Contas a Receber/Pagar)
* **Status**: PENDENTE
* **Funcionalidades Planejadas**:
  * Tabelas polimórficas/decoupled `accounts_receivable`, `accounts_receivable_installments`, `accounts_payable`, `accounts_payable_installments`.
  * Geração automática de Contas a Receber a partir da conclusão de OS/Vendas.
  * Fluxo de caixa completo: controle de vencimentos, recebimentos, baixas, pagamentos de despesas gerais e log de alterações críticas (auditoria).

### Modulo G: Relatórios, Fechamento e PDFs A4
* **Status**: PENDENTE
* **Funcionalidades Planejadas**:
  * Dashboard de Fechamento Financeiro mensal (Caixa vs Competência), cálculo de alíquota efetiva do Simples Nacional com base no RBT12.
  * PDFs estruturados sob padrão A4 para orçamentos e Ordens de Serviço (versões de impressão operacional e do cliente).
  * Exportação de relatórios em formato CSV.
