# =============================================================================
#  Túnel público para que el cliente revise el sistema
# =============================================================================
#
#  Levanta una URL pública de Cloudflare que apunta al Docker local
#  (localhost:8081). Sirve para que el cliente entre desde su casa sin montar
#  nada en el servidor.
#
#  CÓMO SE USA
#    Clic derecho sobre este archivo -> "Ejecutar con PowerShell"
#    (o desde una terminal:  .\tunel-para-el-cliente.ps1)
#
#  La URL cambia cada vez que se levanta. El script la deja en pantalla y
#  también la copia al portapapeles, lista para pegar en WhatsApp.
#
#  PARA APAGARLO
#    Cierra esta ventana, o Ctrl+C.
#
#  OJO
#    - El PC tiene que quedarse encendido y con Docker corriendo.
#    - Mientras esté arriba, cualquiera con la URL llega al login.
#      No lo dejes abierto indefinidamente.
# =============================================================================

$ErrorActionPreference = 'Stop'

$cloudflared = "C:\Program Files (x86)\cloudflared\cloudflared.exe"
$puerto      = 8081
$log         = Join-Path $env:TEMP "tunel-ica-paipa.log"

Write-Host ""
Write-Host "  Túnel público - ICA Paipa" -ForegroundColor Cyan
Write-Host "  ============================================================"
Write-Host ""

# --- 1. cloudflared instalado ---
if (-not (Test-Path $cloudflared)) {
    Write-Host "  cloudflared no está instalado." -ForegroundColor Yellow
    Write-Host "  Instálalo con:  winget install --id Cloudflare.cloudflared"
    Write-Host ""
    Read-Host "  Enter para salir"
    exit 1
}

# --- 2. el Docker respondiendo ---
Write-Host "  Comprobando que el sistema responda en localhost:$puerto ..." -NoNewline
try {
    $r = Invoke-WebRequest -Uri "http://localhost:$puerto/erpsoftsas/index.php" `
                           -UseBasicParsing -TimeoutSec 15
    Write-Host " ok (HTTP $($r.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host " NO RESPONDE" -ForegroundColor Red
    Write-Host ""
    Write-Host "  El contenedor no está arriba. Levántalo con:" -ForegroundColor Yellow
    Write-Host "      docker start erpsoftsas_web_completo erpsoftsascomalcaldiadepaipa-db-1"
    Write-Host ""
    Read-Host "  Enter para salir"
    exit 1
}

# --- 3. túnel ---
if (Test-Path $log) { Remove-Item $log -Force }

Write-Host "  Levantando el túnel ..." -NoNewline
$proc = Start-Process -FilePath $cloudflared `
    -ArgumentList "tunnel","--url","http://localhost:$puerto","--logfile","$log" `
    -WindowStyle Hidden -PassThru

# La URL tarda unos segundos en aparecer en el log.
$url = $null
foreach ($i in 1..30) {
    Start-Sleep -Seconds 1
    if (Test-Path $log) {
        $m = Select-String -Path $log -Pattern "https://[a-z0-9-]+\.trycloudflare\.com" -AllMatches
        if ($m) { $url = $m.Matches[0].Value; break }
    }
    Write-Host "." -NoNewline
}

if (-not $url) {
    Write-Host " no se pudo obtener la URL" -ForegroundColor Red
    Write-Host "  Revisa el log: $log"
    if ($proc -and -not $proc.HasExited) { Stop-Process -Id $proc.Id -Force }
    Read-Host "  Enter para salir"
    exit 1
}

Write-Host " listo" -ForegroundColor Green

$enlace = "$url/erpsoftsas/index.php"
try { Set-Clipboard -Value $enlace } catch { }

Write-Host ""
Write-Host "  ============================================================" -ForegroundColor Cyan
Write-Host "   ENLACE PARA EL CLIENTE  (ya copiado al portapapeles)" -ForegroundColor Cyan
Write-Host "  ============================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "   $enlace" -ForegroundColor White
Write-Host ""
Write-Host "   Usuarios:" -ForegroundColor Gray
Write-Host "     administrador / AdminPruebaLocal2026   (Alcaldía)" -ForegroundColor Gray
Write-Host "     pruebaica     / PruebaICA2026          (contribuyente)" -ForegroundColor Gray
Write-Host ""
Write-Host "  ------------------------------------------------------------"
Write-Host "   El túnel está ARRIBA. Deja esta ventana abierta."
Write-Host "   Para apagarlo: Ctrl+C o cierra la ventana."
Write-Host "  ------------------------------------------------------------"
Write-Host ""

# Se queda vigilando hasta que cierren la ventana.
try {
    while (-not $proc.HasExited) { Start-Sleep -Seconds 5 }
    Write-Host "  El túnel se cayó. Vuelve a ejecutar este script." -ForegroundColor Yellow
} finally {
    if ($proc -and -not $proc.HasExited) {
        Stop-Process -Id $proc.Id -Force
        Write-Host "  Túnel apagado." -ForegroundColor Yellow
    }
}
