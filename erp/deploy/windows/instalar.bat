@echo off
setlocal enabledelayedexpansion
title Neksa ERP - Instalacao
cd /d "%~dp0"

echo ==========================================
echo   Neksa ERP - instalacao (Windows)
echo ==========================================
echo.

docker version >nul 2>&1
if errorlevel 1 (
  echo [ERRO] Docker Desktop nao encontrado ou nao esta rodando.
  echo Instale em https://www.docker.com/products/docker-desktop/ e abra o Docker Desktop.
  pause & exit /b 1
)

if not exist ".env" (
  copy /y ".env.example" ".env" >nul
  echo [OK] Arquivo .env criado. Revise a senha do banco em deploy\windows\.env se quiser.
)

echo.
echo [1/4] Construindo a imagem (pode demorar alguns minutos na primeira vez)...
docker compose build || (echo [ERRO] Falha no build & pause & exit /b 1)

findstr /b /c:"APP_KEY=base64:" ".env" >nul 2>&1
if errorlevel 1 (
  echo [2/4] Gerando APP_KEY...
  for /f "delims=" %%K in ('docker run --rm neksa-erp:latest php artisan key:generate --show --no-ansi') do set "APPKEY=%%K"
  powershell -NoProfile -Command "(Get-Content '.env') -replace '^APP_KEY=.*', 'APP_KEY=!APPKEY!' | Set-Content -Encoding ASCII '.env'"
) else (
  echo [2/4] APP_KEY ja existe, mantendo.
)

echo [3/4] Subindo os servicos...
docker compose up -d || (echo [ERRO] Falha ao subir & pause & exit /b 1)

echo [4/4] Aguardando as migracoes...
timeout /t 20 /nobreak >nul

echo.
echo ==========================================
echo   Pronto! Acesse: http://localhost:8000
echo ==========================================
echo.
echo Os servicos sobem sozinhos sempre que o Windows iniciar,
echo desde que o Docker Desktop esteja configurado para iniciar com o sistema
echo (Docker Desktop ^> Settings ^> General ^> "Start Docker Desktop when you log in").
echo.
echo Rode "criar-usuario.bat" para criar o primeiro usuario administrador.
echo.
pause
