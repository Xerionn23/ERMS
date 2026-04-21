@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM ERMS one-click setup for Windows (XAMPP + Composer)
REM Run this as a normal user; if permissions fail, re-run as Administrator.

echo.
echo === ERMS Setup (Windows / XAMPP / Composer) ===
echo.

set "ROOT=%~dp0"

REM Find PHP
set "PHP_EXE=php"
where php >nul 2>nul
if errorlevel 1 (
  if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
  ) else (
    echo [ERROR] PHP not found in PATH and not found at C:\xampp\php\php.exe
    echo Install XAMPP first, then try again.
    exit /b 1
  )
)

REM Find Composer
set "COMPOSER_EXE=composer"
where composer >nul 2>nul
if errorlevel 1 (
  REM Portable fallback: composer.phar in project root
  if exist "%ROOT%composer.phar" (
    set "COMPOSER_EXE=%PHP_EXE% "%ROOT%composer.phar""
    echo [INFO] Composer not installed; using portable composer.phar
  ) else (
    echo [WARN] Composer not found in PATH.
    echo.
    echo Offline / No-Install option:
    echo - Copy the whole vendor\ folder from a working ERMS installation to:
    echo   %ROOT%vendor\
    echo - After copying, this file must exist:
    echo   %ROOT%vendor\autoload.php
    echo.
    echo If you have internet, easiest options are:
    echo - Install Composer for Windows: https://getcomposer.org/download/
    echo - OR download composer.phar into %ROOT% then re-run this script.
    exit /b 1
  )
)

REM Basic checks
if not exist "%ROOT%composer.json" (
  echo [ERROR] composer.json not found in %ROOT%
  echo Make sure this .bat is inside the ERMS root folder.
  exit /b 1
)

if not exist "%ROOT%TEMPLATE.docx" (
  echo [WARN] TEMPLATE.docx is missing. DOCX generation may fail.
)

if not exist "%ROOT%TEMPLATE_DRUG_TEST.docx" (
  echo [WARN] TEMPLATE_DRUG_TEST.docx is missing. Drug Test generation may fail.
)

echo [INFO] Using PHP: %PHP_EXE%
%PHP_EXE% -v

echo.
echo [INFO] Checking required PHP extensions...
for %%E in (zip gd) do (
  %PHP_EXE% -m | findstr /I /R "^%%E$" >nul
  if errorlevel 1 (
    echo [ERROR] Missing PHP extension: %%E
    echo Enable it in XAMPP php.ini then restart Apache.
    exit /b 1
  ) else (
    echo [OK] %%E
  )
)

echo.
if exist "%ROOT%vendor\autoload.php" (
  echo [OK] vendor\autoload.php exists (Composer already installed)
) else (
  echo [INFO] Running composer install...
  pushd "%ROOT%"
  %COMPOSER_EXE% install
  if errorlevel 1 (
    popd
    echo [ERROR] composer install failed.
    exit /b 1
  )
  popd
)

echo.
echo [NEXT] Database setup:
echo - Open http://localhost/phpmyadmin
echo - Create/import database: erms
echo - Import schema: %ROOT%database\schema.sql
echo.
echo [NEXT] Folder permissions (Apache must write):
echo - %ROOT%export_nuero\
echo - %ROOT%uploads\
echo - %ROOT%storage\
echo - %ROOT%auth\*.txt

echo.
echo [DONE] Open: http://localhost/ERMS/login.php
pause
