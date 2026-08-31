@echo off
title Neksa ERP - parar
cd /d "%~dp0"
docker compose stop
pause
