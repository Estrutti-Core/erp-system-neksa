# AGENTS.md

## Objetivo do Projeto

Este projeto consiste no desenvolvimento de um ERP web focado em Ordens de Serviço externas (serviços em campo).

O sistema será utilizado principalmente por técnicos na rua e operadores internos, portanto deve priorizar:

* simplicidade
* velocidade operacional
* usabilidade mobile
* baixo número de cliques
* organização operacional

O ERP NÃO é financeiro/contábil.
O foco principal é:

* gestão de clientes
* gestão de ordens de serviço
* roteirização
* operação em campo
* apoio operacional para emissão fiscal

---

# Stack Obrigatória

* Laravel
* Laravel Sail
* PostgreSQL
* Blade + Livewire OU Inertia.js
* Docker
* API-ready architecture

---

# Diretrizes Técnicas

## Arquitetura

O projeto deve seguir:

* Clean Code
* SOLID quando fizer sentido
* Baixo acoplamento
* Alta coesão
* Código legível e escalável

Evitar:

* Controllers gigantes
* Regras de negócio dentro de Controllers
* Queries complexas espalhadas
* Duplicidade de lógica

Utilizar:

* Services
* Actions
* Form Requests
* Policies
* DTOs quando necessário

---

# Banco de Dados

Seguir boas práticas:

* foreign keys
* índices
* normalization
* soft deletes quando necessário
* evitar duplicidade
* migrations organizadas

Pensar em:

* performance
* escalabilidade
* integridade dos dados

---

# Mobile First

O sistema será utilizado principalmente em celulares.

Portanto:

* toda interface deve ser pensada mobile-first
* evitar tabelas complexas
* evitar excesso de informação
* priorizar UX operacional
* botões grandes
* navegação rápida
* componentes responsivos
* carregamento rápido

A experiência mobile é prioridade máxima.

---

# API Ready

O projeto deve nascer preparado para:

* aplicativo mobile futuro
* integrações externas
* WhatsApp
* APIs terceiras

Portanto:

* separar backend da camada visual
* estruturar resources/responses adequadamente
* manter arquitetura desacoplada
* preparar autenticação compatível com API

---

# Funcionalidades Principais

## Clientes

* cadastro completo
* histórico
* geolocalização

## Técnicos

* agenda
* região
* status

## Ordens de Serviço

* status operacional
* anexos
* fotos
* assinatura
* histórico

## Roteirização

* cálculo de rotas
* agrupamento geográfico
* mapa visual

## Fiscal

* resumo para emissão manual de NF

---

# UI/UX

A interface deve parecer um SaaS moderno.

Priorizar:

* clareza
* velocidade
* simplicidade
* responsividade
* experiência operacional

Evitar:

* excesso de modais
* telas poluídas
* excesso de campos
* fluxos longos
* uso de emojis (são estritamente proibidos em toda a interface do ERP empresarial)
* exibição de dois asteriscos (`**`) literais no texto para indicar negrito na interface; use tags HTML apropriadas como `<strong>` ou `<b>` ou estilização CSS.

---

# Estrutura Esperada

Utilizar estrutura organizada:

* app/Services
* app/Actions
* app/DTOs
* app/Policies
* app/Http/Requests

Criar:

* Seeders
* Factories
* Migrations organizadas
* CRUDs completos
* Dashboard inicial

---

# Qualidade

O código deve:

* ser fácil de manter
* ser fácil de expandir
* parecer um produto SaaS profissional
* evitar arquitetura improvisada

Sempre priorizar:

1. usabilidade
2. organização
3. escalabilidade
4. performance
5. experiência mobile

---

# Acompanhamento de Desenvolvimento (Roadmap)

Para detalhes sobre o andamento de cada módulo, consulte o arquivo **ROADMAP.md** na raiz do projeto.

Este chat concluiu com sucesso a implementação do **Módulo A**, **Módulo B** e **Módulo C**, incluindo:
* Cadastro/Edição de Equipamentos diretamente no cliente.
* Equipamentos opcionais em Orçamentos e passagem automática de equipamento para OS durante a conversão.
* Visualização inline (via stream) de PDFs no navegador com nomenclatura padronizada por cliente e tipo de documento.
* Autocompletar automático de CNPJ ao digitar 14 dígitos nos formulários de cliente.
* Status Customizados para Ordens de Serviço com transições permitidas gerenciadas por administrador e regras rígidas de fluxo.
* Histórico dedicado de permanência em status para auditoria operacional e motor de SLA via console command agendado.

O próximo passo planejado é o **Módulo D** (Ordens de Serviço, Anexos e Checklists).