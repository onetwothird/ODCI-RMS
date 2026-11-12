@echo off
REM ---------------------------------------------------------------------------
REM Starts the ODCI Laravel app on http://127.0.0.1:8000
REM
REM --no-reload is REQUIRED on Windows. Without it, Laravel's ServeCommand
REM blanks every $_ENV variable that is not in its $passthroughVariables list
REM (ServeCommand.php line 179). That list spells them "PATH" and "SYSTEMROOT"
REM in UPPERCASE, but Windows $_ENV holds "Path" and "SystemRoot"; the match is
REM case-sensitive, so both get wiped, Winsock fails to initialise, and PHP
REM reports "Failed to listen on 127.0.0.1:8000 (reason: ?)" on all 101 ports.
REM
REM Trade-off: the server will NOT auto-restart when .env changes.
REM After editing .env, stop with Ctrl+C and run this script again.
REM ---------------------------------------------------------------------------

setlocal

cd /d "%~dp0"

echo.
echo   ODCI Document - Laravel 11
echo   ---------------------------------------------
echo   URL:  http://127.0.0.1:8000/login
echo   Stop: Ctrl+C
echo.

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if errorlevel 1 (
    echo   [note] MySQL does not appear to be running.
    echo          Start it from the Laragon menu if login fails with a
    echo          "SQLSTATE[HY000] [2002] Connection refused" error.
    echo.
)

php artisan optimize:clear
php artisan serve --no-reload

endlocal