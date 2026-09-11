<#
  Actualizador de Guias Electronicas (DBPeru)

  Para instalaciones hechas con instalar.ps1. Antes de tocar nada respalda la
  base (mysqldump) y la aplicacion. Si algo falla -copia, migracion o
  arranque- REVIERTE solo: restaura codigo, ApiGRE y base, y vuelve a
  levantar los servicios.

  Uso (PowerShell como Administrador, desde la carpeta del paquete NUEVO):
    powershell -ExecutionPolicy Bypass -File .\actualizar.ps1
#>
[CmdletBinding()]
param(
  [string]$Destino = 'C:\DBPeru\GRE',
  [int]$PuertoWeb = 80
)

$ErrorActionPreference = 'Stop'
$Paquete = $PSScriptRoot
. (Join-Path $Paquete 'comun.ps1')

Exigir-Administrador
foreach ($f in "$Destino\app\.env", "$Destino\config\secretos.json", "$Destino\php\php.exe") {
  if (-not (Test-Path $f)) { Write-Host "No parece una instalacion GRE: falta $f. Para instalar use instalar.ps1"; exit 1 }
}

$fecha = Get-Date -Format yyyyMMdd-HHmmss
$log = "$Destino\logs\actualizacion-$fecha.log"
Start-Transcript -Path $log | Out-Null
$respaldo = "$Destino\respaldos\$fecha"
$secretos = Leer-Secretos $Destino
$php = "$Destino\php\php.exe"
$revertible = $false

try {
  Paso 'Respaldo de la base de datos'
  New-Item -ItemType Directory -Force $respaldo | Out-Null
  Start-Service GRE-MySQL
  Esperar-Puerto 3306 60 'MySQL'
  Mysql-Volcar $Destino $secretos.MysqlRoot "$respaldo\guia_electronica.sql"

  Paso 'Respaldo de la aplicacion'
  $ErrorActionPreference = 'Continue'
  robocopy "$Destino\app" "$respaldo\app" /E /NFL /NDL /NJH /NJS /NP /XD "$Destino\app\public\storage" | Out-Null
  $codigo = $LASTEXITCODE
  $ErrorActionPreference = 'Stop'
  if ($codigo -ge 8) { throw "No se pudo respaldar la aplicacion (robocopy $codigo)" }
  Copy-Item "$Destino\api\api-gre.jar" "$respaldo\api-gre.jar"
  $revertible = $true

  Paso 'Deteniendo servicios'
  Stop-Service GRE-Apache
  Detener-Api

  Paso 'Copiando la version nueva'
  Copiar-App "$Paquete\app" "$Destino\app"
  Copy-Item "$Paquete\api-gre.jar" "$Destino\api\api-gre.jar" -Force

  Paso 'Actualizando la base'
  Push-Location "$Destino\app"
  try {
    Artisan $php 'config:clear' | Out-Null
    # Clientes que llegaron con la base importada de un dump: su tabla
    # migrations esta vacia y migrate intentaria crear tablas que ya existen.
    $conMigraciones = Mysql-Sql $Destino $secretos.MysqlRoot "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='guia_electronica' AND table_name='migrations';"
    $filas = if ([int]$conMigraciones -gt 0) { Mysql-Sql $Destino $secretos.MysqlRoot 'SELECT COUNT(*) FROM guia_electronica.migrations;' } else { 0 }
    if ([int]$filas -eq 0) { Artisan $php 'gre:baseline' | Out-Null }
    Artisan $php 'migrate --force' | Out-Null
    Artisan $php 'gre:inicializar' | Out-Null
    Artisan $php 'config:cache' | Out-Null
    Artisan $php 'view:cache' | Out-Null
  } finally { Pop-Location }

  Paso 'Arrancando servicios'
  Configurar-Apache $Destino $PuertoWeb
  Start-Service GRE-Apache
  schtasks.exe /Run /TN 'GRE-ApiGRE' | Out-Null
  Esperar-Http 'http://127.0.0.1:8181/api/v1/health' 150 'ApiGRE'
  Revisar-ApiGRE 'http://127.0.0.1:8181/api/v1/health' | Out-Null
  Esperar-Http "http://127.0.0.1:$PuertoWeb/login" 60 'Aplicacion web'

  Write-Host "`nACTUALIZACION_OK   Respaldo en $respaldo" -ForegroundColor Green
}
catch {
  Write-Host "`nACTUALIZACION_FALLIDA: $($_.Exception.Message)" -ForegroundColor Red
  if ($revertible) {
    Write-Host 'Revirtiendo a la version anterior...' -ForegroundColor Yellow
    try {
      $ErrorActionPreference = 'Continue'
      Stop-Service GRE-Apache -ErrorAction SilentlyContinue
      Detener-Api
      robocopy "$respaldo\app" "$Destino\app" /MIR /NFL /NDL /NJH /NJS /NP /XD "$Destino\app\public\storage" "$Destino\app\storage" | Out-Null
      Copy-Item "$respaldo\api-gre.jar" "$Destino\api\api-gre.jar" -Force
      $ErrorActionPreference = 'Stop'
      Mysql-Sql $Destino $secretos.MysqlRoot "source $("$respaldo\guia_electronica.sql" -replace '\\', '/')" 'guia_electronica' | Out-Null
      Start-Service GRE-Apache
      schtasks.exe /Run /TN 'GRE-ApiGRE' | Out-Null
      Write-Host 'REVERTIDO: el sistema quedo como estaba antes de actualizar.' -ForegroundColor Yellow
    } catch {
      Write-Host "NO SE PUDO REVERTIR: $($_.Exception.Message). El respaldo sigue intacto en $respaldo" -ForegroundColor Red
    }
  }
  Write-Host "Log: $log"
  Stop-Transcript | Out-Null
  exit 1
}
Stop-Transcript | Out-Null
