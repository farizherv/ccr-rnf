@echo off
REM ============================================
REM  CCR RNF — Desktop Shortcut
REM  Buka app CCR di Chrome tanpa address bar
REM ============================================
REM  Ganti URL di bawah dengan domain kamu.
REM ============================================

set CCR_URL=https://your-domain.com

REM Coba Chrome dulu
where chrome >nul 2>nul
if %errorlevel%==0 (
    start chrome --app=%CCR_URL%
    exit /b
)

REM Coba Chrome di lokasi default
if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --app=%CCR_URL%
    exit /b
)

if exist "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" --app=%CCR_URL%
    exit /b
)

REM Coba Edge
where msedge >nul 2>nul
if %errorlevel%==0 (
    start msedge --app=%CCR_URL%
    exit /b
)

if exist "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" (
    start "" "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --app=%CCR_URL%
    exit /b
)

REM Fallback: buka di browser default
start %CCR_URL%
