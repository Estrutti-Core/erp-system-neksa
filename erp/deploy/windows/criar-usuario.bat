@echo off
title Neksa ERP - criar usuario
cd /d "%~dp0"
docker compose exec app php artisan tinker
pause
