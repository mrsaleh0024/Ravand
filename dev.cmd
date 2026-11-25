@echo off
rem Ravand — wrapper for double-click / cmd usage
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0dev.ps1" %*
