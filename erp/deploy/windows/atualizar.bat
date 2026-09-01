@echo off
setlocal
title Neksa ERP - atualizar
cd /d "%~dp0"

echo ==========================================
echo   Neksa ERP - atualizacao
echo ==========================================
echo.

docker version >nul 2>&1
if errorlevel 1 (
  echo [ERRO] Docker Desktop nao esta rodando. Abra-o e espere ficar verde.
  pause & exit /b 1
)

git --version >nul 2>&1
if errorlevel 1 (
  echo [AVISO] Git nao encontrado - vou apenas reconstruir com o codigo que ja esta na pasta.
  echo         Para baixar atualizacoes automaticamente, instale o Git:
  echo         https://git-scm.com/download/win
  echo.
  goto :build
)

echo [1/3] Baixando atualizacoes do repositorio...
git -C "%~dp0..\.." rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
  echo [AVISO] Esta pasta nao e um clone git; pulando o download.
  goto :build
)

for /f "delims=" %%B in ('git -C "%~dp0..\.." rev-parse --abbrev-ref HEAD') do set "BRANCH=%%B"
git -C "%~dp0..\.." pull --ff-only origin %BRANCH%
if errorlevel 1 (
  echo.
  echo [ERRO] Nao foi possivel atualizar o codigo.
  echo        Se houver alteracoes locais nesta maquina, desfaca-as com:
  echo          git -C "%~dp0..\.." checkout -- .
  echo        e rode este script de novo. O arquivo .env nao e afetado.
  pause & exit /b 1
)

:build
echo.
echo [2/3] Reconstruindo a aplicacao...
docker compose build || (echo [ERRO] Falha no build & pause & exit /b 1)

echo [3/3] Reiniciando os servicos (as migracoes rodam sozinhas)...
docker compose up -d --force-recreate || (echo [ERRO] Falha ao reiniciar & pause & exit /b 1)

echo.
echo ==========================================
echo   Atualizado! http://localhost:8000
echo ==========================================
echo.
echo Dica: rode "backup.bat" antes de atualizar se quiser um ponto de retorno.
pause
