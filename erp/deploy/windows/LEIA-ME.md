# Neksa ERP — instalação no Windows

Roda em Docker Desktop, em modo produção, e sobe sozinho toda vez que o PC liga.

## 1. Pré-requisito (uma vez)

1. Instale o **Docker Desktop**: https://www.docker.com/products/docker-desktop/
   (ele instala o WSL2 sozinho; pode pedir um reboot)
2. Abra o Docker Desktop → **Settings → General** → marque
   **"Start Docker Desktop when you log in"** → *Apply & restart*.

## 2. Instalar o ERP

1. Copie a pasta do projeto para o PC (ex.: `C:\neksa-erp`).
2. (Opcional) edite `deploy\windows\.env.example` e troque `DB_PASSWORD`.
3. Dê **duplo clique em `instalar.bat`**.

O script constrói a imagem, gera a `APP_KEY`, sobe banco/redis/app e roda as
migrations. **Não roda seeders** — o banco começa vazio.

Ao terminar: **http://localhost:8000**

## 3. Primeiro usuário

Duplo clique em `criar-usuario.bat` (abre o Tinker dentro do container) e cole:

```php
$u = new App\Models\User(['name' => 'Admin', 'email' => 'admin@empresa.com']);
$u->password = Hash::make('SENHA_FORTE_AQUI');
$u->save();
```

Se o projeto usa papéis (spatie/permission), depois:

```php
$u->assignRole('admin');
```

Digite `exit` para sair.

## 4. Uso no dia a dia

| Arquivo | O que faz |
|---|---|
| `iniciar.bat` | Sobe (se estiver parado) e abre o navegador |
| `parar.bat` | Para os serviços |
| `atualizar.bat` | Baixa a versão nova (git pull) e reconstrói |
| `backup.bat` | Dump do Postgres em `backups\` |
| `instalar.bat` | Instalação inicial (pode rodar de novo com segurança) |

**Início automático:** os containers têm `restart: unless-stopped`. Com o Docker
Desktop iniciando junto com o login do Windows, o ERP volta ao ar sozinho depois
de qualquer reinício ou queda de energia. Nenhuma janela fica aberta.

### Como receber atualizações

Duplo clique em **`atualizar.bat`**. Ele faz `git pull` na branch atual,
reconstrói a imagem, reinicia os serviços e roda as migrations novas.
O `.env` e o banco de dados **não são tocados**.

Para isso funcionar, o projeto precisa ter sido **clonado** com git (não copiado
como .zip) e o **Git for Windows** instalado: https://git-scm.com/download/win

```
git clone <url-do-repositorio> C:\neksa-erp
```

Se o Git não estiver instalado, o `atualizar.bat` ainda funciona — mas só
reconstrói o código que já está na pasta; a atualização teria de ser copiada
à mão.

Recomendado: rodar `backup.bat` antes de uma atualização.

**Atalho na área de trabalho:** clique com o botão direito em `iniciar.bat` →
*Enviar para → Área de trabalho (criar atalho)*. Renomeie para "Neksa ERP".

## 5. Acesso de outros PCs da rede

Já funciona: `http://IP-DO-PC:8000`. Libere a porta 8000 no Firewall do Windows
(PowerShell como administrador):

```powershell
New-NetFirewallRule -DisplayName "Neksa ERP" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
```

E ajuste `APP_URL` no `.env` para o IP da máquina, depois rode `atualizar.bat`.

## 6. Backups

`backup.bat` gera um `.sql` em `deploy\windows\backups\`. Para automatizar,
crie uma tarefa no **Agendador de Tarefas** do Windows apontando para ele.
Os dados vivem em volumes Docker (`neksa-erp_pgsql-data`), não na pasta do projeto.

## 7. Problemas comuns

- **"Docker não encontrado"** → abra o Docker Desktop e espere ficar verde.
- **Página em branco / erro 500** → `docker compose logs -f app` nesta pasta.
- **Porta 8000 ocupada** → mude `APP_PORT` no `.env` e rode `atualizar.bat`.
