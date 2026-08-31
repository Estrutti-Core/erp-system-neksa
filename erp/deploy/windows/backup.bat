@echo off
title Neksa ERP - backup do banco
cd /d "%~dp0"
if not exist "backups" mkdir "backups"
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set LDT=%%I
set STAMP=%LDT:~0,8%-%LDT:~8,6%
docker compose exec -T pgsql pg_dump -U neksa neksa_erp > "backups\neksa_erp-%STAMP%.sql"
echo Backup salvo em backups\neksa_erp-%STAMP%.sql
pause
