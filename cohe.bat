@echo off
setlocal

:: ================= CONFIG =================
set API_URL="http://localhost/command"

:: API_TOKEN must exist as an environment variable.
:: Example:
:: setx API_TOKEN "your_token"

if "%COHE_TOKEN%"=="" (
    echo ERROR Environment variable API_TOKEN is not defined.
    echo.
    echo Run:
    echo     setx API_TOKEN "your_token"
    exit /b 1
)

:: ================= ARGUMENTS =================

shift
set MESSAGE=%*

if "%MESSAGE%"=="" (
        echo ERROR Please provide a command request.
        echo.
        echo Usage:
        echo     cohe your question here
        exit /b 1
    )

    powershell.exe ^
        -NoProfile ^
        -ExecutionPolicy Bypass ^
        -Command ^
        "$headers = @{ Authorization = 'Bearer %COHE_TOKEN%' };" ^
        "$body = @{ message = '%MESSAGE%' } | ConvertTo-Json -Compress;" ^
        "try {" ^
            "$response = Invoke-RestMethod -Uri '%API_URL%' -Method Post -Headers $headers -ContentType 'application/json' -Body $body;" ^
            "if ($response.response) {" ^
                "foreach ($line in ($response.response -split \"`r?`n\")) {" ^
                    "if ($line -match '^\s*#') {" ^
                       " Write-Host $line -ForegroundColor Yellow" ^
                    "} elseif ($line.Trim() -ne '') {" ^
                        "Write-Host $line -ForegroundColor Cyan" ^
                    "} else {" ^
                       " Write-Host ''" ^
                   " }" ^
                "}" ^
            "} else {" ^
                "Write-Host 'ERROR No response received from API.' -ForegroundColor Red" ^
            "}" ^
        "} catch {" ^
            "Write-Host ('ERROR ' + $_.Exception.Message) -ForegroundColor Red" ^
        "}"

    exit /b %errorlevel%

echo Usage:
echo.
echo cohe your question here

exit /b 1