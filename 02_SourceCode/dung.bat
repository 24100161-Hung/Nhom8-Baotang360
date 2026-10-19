@echo off
rem BaoTang360 - Dung toan bo container, giu nguyen du lieu. Xoa sach: docker compose down -v
chcp 65001 >nul
cd /d "%~dp0"
title BaoTang360 - dung
docker compose down
echo  Da dung. Du lieu van duoc giu; chay chay.bat de khoi dong lai.
pause
