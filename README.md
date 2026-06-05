# Neksa ERP - Sistema de Gestão de Ordens de Serviço (Field Service)

O **Neksa ERP** é um sistema web robusto e moderno desenvolvido especificamente para o gerenciamento de Ordens de Serviço externas (serviços em campo). Focado na usabilidade, velocidade operacional e simplicidade, o sistema foi desenhado primariamente para técnicos de rua e operadores internos.

> **Nota:** Este sistema **não** é um ERP financeiro ou contábil. Seu foco exclusivo é a gestão operacional, logística de rotas e o dia a dia dos serviços prestados em campo.

---

## 🚀 Principais Funcionalidades

### 👥 Gestão de Clientes
- Cadastro completo de clientes (dados e múltiplos endereços).
- Histórico completo de serviços prestados.
- Geolocalização para facilitar as rotas de atendimento.

### 👷 Gestão de Técnicos
- Controle detalhado de agenda e horários.
- Definição e agrupamento por regiões de atendimento.
- Acompanhamento de status em tempo real (em rota, executando, concluído).

### 📋 Ordens de Serviço (OS)
- Controle de status operacional (Aguardando, Em Execução, Finalizado).
- Suporte para anexos e registro fotográfico do serviço executado.
- Coleta de assinatura digital do cliente ao finalizar a OS.
- Histórico completo das movimentações e interações da OS.

### 🗺️ Roteirização e Mapa
- Cálculo de rotas e otimização de deslocamento.
- Agrupamento geográfico dinâmico para facilitar a logística.
- Visualização de um mapa interativo para apoio operacional aos técnicos e gestores.

### 📄 Apoio Fiscal
- Geração de resumos detalhados da OS para facilitar a emissão manual de Nota Fiscal (NF).

---

## 🛠️ Tecnologias e Stack Obrigatória

O projeto foi construído sobre uma fundação robusta de tecnologias para garantir escalabilidade, performance e facilidade de manutenção:

- **Framework Backend:** Laravel
- **Frontend / UI:** Blade + Livewire OU Inertia.js (focado em reatividade)
- **Banco de Dados:** PostgreSQL
- **Ambiente e Deploy:** Docker com Laravel Sail
- **Arquitetura:** API-ready (preparado para futuras integrações com aplicativos mobile, WhatsApp e APIs de terceiros).

---

## 🏛️ Arquitetura e Diretrizes de Código

O código do Neksa ERP segue princípios modernos de engenharia de software:

- **Clean Code e SOLID:** Implementados onde agregam valor real, visando a legibilidade e fácil manutenção.
- **Baixo Acoplamento e Alta Coesão:** Os controllers são "magros". Toda a regra de negócio e lógica complexa fica isolada.
- **Padrões Adotados:**
  - **Services:** Para orquestração de regras de negócios que envolvem múltiplos domínios.
  - **Actions:** Para ações isoladas e de responsabilidade única (Single Responsibility Principle).
  - **Form Requests:** Para centralizar toda a validação de entrada de dados.
  - **Policies:** Para garantir a correta autorização de quem pode ver ou alterar o quê.
  - **DTOs (Data Transfer Objects):** Quando necessário o tráfego de dados estruturados e tipados entre as camadas.

O Banco de Dados é meticulosamente construído para garantir performance e integridade, utilizando *foreign keys*, índices adequados, *soft deletes* e normalização dos dados.

---

## 📱 Mobile-First e Experiência do Usuário (UI/UX)

Sendo uma aplicação cujo principal usuário estará no trânsito e na rua operando através de um celular:

- **Interface Mobile-First:** Todas as telas são desenhadas primeiro para dispositivos móveis, garantindo botões grandes e leitura clara, mesmo sob luz do sol.
- **Velocidade e Agilidade:** Carregamentos rápidos, navegação otimizada e o mínimo possível de cliques para chegar a uma ação.
- **Aparência "SaaS Premium":** A interface possui um visual moderno, priorizando clareza, elementos responsivos, micro-animações sutis e fontes legíveis. Evitamos o uso excessivo de tabelas complexas e telas sobrecarregadas de informações, optando por designs limpos.

---

## ⚙️ Como rodar o projeto localmente

Para rodar este projeto na sua máquina, você precisa ter o **Docker** e o **Docker Compose** instalados.

1. **Clone o repositório e acesse a pasta do app:**
   ```bash
   git clone <url-do-repositorio>
   cd erp-neksa/erp-system/erp
   ```

2. **Instale as dependências do Composer (usando um container temporário):**
   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php83-composer:latest \
       composer install --ignore-platform-reqs
   ```

3. **Configure o arquivo `.env`:**
   ```bash
   cp .env.example .env
   ```
   *(Lembre-se de configurar as variáveis de conexão com o banco de dados conforme o docker-compose.yml)*

4. **Inicie o ambiente com o Laravel Sail:**
   ```bash
   ./vendor/bin/sail up -d
   ```

5. **Gere a chave da aplicação e rode as migrations (com seeders para popular dados base):**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate --seed
   ```

6. **Instale e compile os assets do Frontend:**
   ```bash
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run dev
   ```

A aplicação agora deve estar disponível em `http://localhost`.

---

## 📁 Estrutura de Diretórios Principal

- `app/Actions/` - Classes de ação com responsabilidade única.
- `app/DTOs/` - Objetos para transferência e padronização de dados.
- `app/Services/` - Classes que contêm as lógicas de negócio core do sistema.
- `app/Models/` - Modelos Eloquent e definições de relacionamento/banco de dados.
- `app/Http/Controllers/` - Controladores magros (orquestram requisições e respostas).
- `app/Http/Requests/` - Regras de validação e autorização de formulários.
- `app/Policies/` - Regras de autorização por modelo.
- `resources/views/` - Páginas, layouts e componentes visuais do sistema.
- `routes/` - Definições das rotas (`web.php` e `api.php`).
