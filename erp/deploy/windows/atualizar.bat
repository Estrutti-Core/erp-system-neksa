@echo off
title Neksa ERP - atualizar
cd /d "%~dp0"
echo Reconstruindo a aplicacao com o codigo atual...
docker compose build || (echo [ERRO] Falha no build & pause & exit /b 1)
docker compose up -d --force-recreate
echo.
echo Atualizado. http://localhost:8000
pause
