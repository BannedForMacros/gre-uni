@echo off
title Actualizar Guias Electronicas
cd /d "%~dp0"

rem Si no se abrio como administrador, se vuelve a lanzar pidiendo permiso.
net session >nul 2>&1
if errorlevel 1 (
  echo Pidiendo permisos de administrador...
  powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0actualizar.ps1"

echo.
pause
