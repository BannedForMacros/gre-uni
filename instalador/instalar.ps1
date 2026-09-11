<#
  Instalador on-premise de Guias Electronicas (DBPeru)

  Deja funcionando en un servidor Windows: Apache + PHP 7.4 (Laravel), MySQL 5.7
  y ApiGRE (Java 8), todo dentro de una sola carpeta y sin descargar nada.
  SQL Server es del cliente: solo se configura la conexion.

  Es IDEMPOTENTE: correrlo otra vez repara lo que falte y no borra datos. Las
  claves generadas se guardan en config\secretos.json y se reutilizan.

  Uso (PowerShell como Administrador, desde la carpeta del paquete):
    powershell -ExecutionPolicy Bypass -File .\instalar.ps1 `
      -Ruc 20100030838 -RazonSocial "EMPRESA SAC" -Direccion "AV. ..." `
      -SqlServidor "192.168.1.10,1433" -SqlBase db_cliente -SqlUsuario sa -SqlClave "..." `
      -FacturacionUrl "..." -FacturacionConsultasUrl "..." -FacturacionCredencial "..."

  Requisitos: Windows 7 SP1 x64 o superior con PowerShell 5.1 (en Windows 7
  instalar antes WMF 5.1) y .NET 4.5. Ejecutar como Administrador.
#>
[CmdletBinding()]
param(
  [string]$Destino = 'C:\DBPeru\GRE',
  [string]$Ruc = '',
  [string]$RazonSocial = '',
  [string]$Direccion = '',
  [string]$Telefonos = '',
  [string]$SqlServidor = '',
  [string]$SqlBase = '',
  [string]$SqlUsuario = '',
  [string]$SqlClave = '',
  [string]$FacturacionUrl = '',
  [string]$FacturacionConsultasUrl = '',
  [string]$FacturacionEstadoUrl = '',
  [string]$FacturacionCredencial = '',
  [int]$PuertoWeb = 0,
  [string]$AdminUsuario = 'admin',
  # Archivo KEY=VALOR con los datos del cliente. Por omision se busca datos.txt
  # junto a este script.
  [string]$Datos = '',
  # Para tecnicos: no pregunta nada y falla si falta un dato.
  [switch]$SinPreguntas
)

$ErrorActionPreference = 'Stop'
$Paquete = $PSScriptRoot
. (Join-Path $Paquete 'comun.ps1')

Exigir-Administrador

# ---------------------------------------------------------------- datos
# Cada dato se toma, en este orden, del parametro, del archivo datos.txt y, si
# sigue faltando, se pregunta. Asi el tecnico puede dejarlo todo preparado y el
# instalador no pregunta nada, o puede ejecutarlo con doble clic y contestar.
$archivoDatos = if ($Datos) { $Datos } else { Join-Path $Paquete 'datos.txt' }
$datosCliente = Leer-Datos $archivoDatos

function Dato([string]$clave, [string]$actual) {
  if ($actual) { return $actual }
  if ($datosCliente.ContainsKey($clave)) { return $datosCliente[$clave] }
  return ''
}

Write-Host ''
Write-Host '  ===============================================' -ForegroundColor Cyan
Write-Host '   Instalacion de Guias Electronicas' -ForegroundColor Cyan
Write-Host '  ===============================================' -ForegroundColor Cyan
if (Test-Path $archivoDatos) { Write-Host "  Datos tomados de $archivoDatos" }
else { Write-Host '  Responda las preguntas. Entre corchetes va el valor por omision.' }
Write-Host ''

$Ruc = Preguntar -Texto 'RUC de la empresa' -Valor (Dato 'ruc' $Ruc) -SinPreguntas:$SinPreguntas `
       -Validar { param($v) $v -match '^\d{11}$' } -Ayuda 'El RUC son 11 numeros, sin espacios.'
$RazonSocial = Preguntar -Texto 'Razon social' -Valor (Dato 'razon_social' $RazonSocial) -SinPreguntas:$SinPreguntas `
       -Validar { param($v) $v.Trim().Length -ge 3 } -Ayuda 'Escriba el nombre de la empresa.'
$Direccion = Preguntar -Texto 'Direccion' -Valor (Dato 'direccion' $Direccion) -PorDefecto '-' -SinPreguntas:$SinPreguntas
$Telefonos = Preguntar -Texto 'Telefono' -Valor (Dato 'telefonos' $Telefonos) -PorDefecto '-' -SinPreguntas:$SinPreguntas

Write-Host ''
Write-Host '  SQL Server del ERP:' -ForegroundColor Cyan
$SqlServidor = Preguntar -Texto 'Servidor (equipo, equipo,puerto o equipo\instancia)' -Valor (Dato 'sql_servidor' $SqlServidor) -SinPreguntas:$SinPreguntas `
       -Validar { param($v) $v.Trim().Length -ge 3 } -Ayuda 'Ejemplo: 192.168.1.10,1433'
$SqlBase = Preguntar -Texto 'Base de datos' -Valor (Dato 'sql_base' $SqlBase) -SinPreguntas:$SinPreguntas `
       -Validar { param($v) $v.Trim().Length -ge 1 } -Ayuda 'Nombre de la base del ERP.'
$SqlUsuario = Preguntar -Texto 'Usuario' -Valor (Dato 'sql_usuario' $SqlUsuario) -PorDefecto 'sa' -SinPreguntas:$SinPreguntas
$SqlClave = Preguntar -Texto 'Clave' -Valor (Dato 'sql_clave' $SqlClave) -Oculto -SinPreguntas:$SinPreguntas `
       -Validar { param($v) $v.Length -ge 1 } -Ayuda 'La clave no puede quedar vacia.'

# Se comprueba ANTES de instalar: si el servidor no responde, es mejor saberlo
# ahora que despues de desplegar todo.
while ($true) {
  $sql = Parsear-SqlServidor $SqlServidor
  if (Tcp-Responde $sql.Host $sql.Puerto 5000) {
    Write-Host "  SQL Server responde en $($sql.Host):$($sql.Puerto)" -ForegroundColor Green
    break
  }
  Write-Warning "No se llega a $($sql.Host):$($sql.Puerto). Revise el nombre, el puerto y el firewall."
  if ($SinPreguntas) { throw "No se alcanza el SQL Server en $($sql.Host):$($sql.Puerto)" }
  $otro = Read-Host '  Escriba otro servidor, o Enter para instalar igual'
  if (-not $otro) { break }
  $SqlServidor = $otro
}

$FacturacionUrl = Dato 'facturacion_url' $FacturacionUrl
$FacturacionConsultasUrl = Dato 'facturacion_consultas_url' $FacturacionConsultasUrl
$FacturacionEstadoUrl = Dato 'facturacion_estado_url' $FacturacionEstadoUrl
$FacturacionCredencial = Dato 'facturacion_credencial' $FacturacionCredencial
if (-not $PuertoWeb) {
  $PuertoWeb = [int](Preguntar -Texto 'Puerto del sitio web' -Valor (Dato 'puerto_web' '') -PorDefecto '80' -SinPreguntas:$SinPreguntas `
       -Validar { param($v) ($v -as [int]) -gt 0 } -Ayuda 'Un numero de puerto, normalmente 80.')
}

Write-Host ''
Write-Host '  Resumen' -ForegroundColor Cyan
Write-Host "    Empresa     : $RazonSocial"
Write-Host "    RUC         : $Ruc"
Write-Host "    SQL Server  : $SqlServidor / $SqlBase / usuario $SqlUsuario"
Write-Host "    Se instala en: $Destino   y la web en el puerto $PuertoWeb"
if (-not $FacturacionUrl) { Write-Host '    Facturacion : sin configurar; se completa despues desde la pantalla' }
Write-Host ''
if (-not $SinPreguntas) {
  if ((Read-Host '  Continuar? (S/N)') -notmatch '^[SsYy]') { Write-Host '  Cancelado, no se toco nada.'; exit 1 }
}
Write-Host ''
New-Item -ItemType Directory -Force "$Destino\logs", "$Destino\config", "$Destino\datos" | Out-Null
$log = "$Destino\logs\instalacion-$(Get-Date -Format yyyyMMdd-HHmmss).log"
Start-Transcript -Path $log | Out-Null

try {
  # ------------------------------------------------------------------ 1
  Paso 'Comprobando el paquete'
  $zips = @{
    php    = Buscar-Unico "$Paquete\runtime\php-7.4*-x64.zip"
    apache = Buscar-Unico "$Paquete\runtime\httpd-2.4*-Win64-*.zip"
    mysql  = Buscar-Unico "$Paquete\runtime\mysql-5.7*-winx64.zip"
    jre    = Buscar-Unico "$Paquete\runtime\OpenJDK8U-jre_x64_windows_*.zip"
    vc     = Buscar-Unico "$Paquete\runtime\vc_redist*.x64.exe"
  }
  foreach ($f in @("$Paquete\app\artisan", "$Paquete\api-gre.jar")) {
    if (-not (Test-Path $f)) { throw "Paquete incompleto: falta $f" }
  }

  $secretos = Leer-Secretos $Destino
  $puertosLibres = @{ 3306 = 'GRE-MySQL'; $PuertoWeb = 'GRE-Apache' }
  foreach ($p in $puertosLibres.Keys) {
    if ((Puerto-Ocupado $p) -and -not (Servicio-Existe $puertosLibres[$p])) {
      throw "El puerto $p ya lo usa otro programa (Laragon, XAMPP, IIS...). Detengalo o use otro puerto."
    }
  }

  # ------------------------------------------------------------------ 2
  Paso 'Runtime de Visual C++'
  $vc = Start-Process -FilePath $zips.vc -ArgumentList '/install /quiet /norestart' -Wait -PassThru
  if (@(0, 1638, 3010) -notcontains $vc.ExitCode) { throw "Visual C++ devolvio el codigo $($vc.ExitCode)" }

  # ------------------------------------------------------------------ 3
  Paso 'Descomprimiendo PHP, Apache, MySQL y Java'
  Extraer-Si-Falta $zips.php    "$Destino\php"    'php.exe'
  Extraer-Si-Falta $zips.apache "$Destino\apache" 'bin\httpd.exe'
  Extraer-Si-Falta $zips.mysql  "$Destino\mysql"  'bin\mysqld.exe'
  Extraer-Si-Falta $zips.jre    "$Destino\jre"    'bin\java.exe'
  $php = "$Destino\php\php.exe"

  # ------------------------------------------------------------------ 4
  Paso 'Configurando PHP'
  Configurar-Php "$Destino\php"

  # ------------------------------------------------------------------ 5
  Paso 'MySQL'
  $myIni = "$Destino\config\my.ini"
  $dir = ($Destino -replace '\\', '/')
  Escribir-Texto $myIni @"
[mysqld]
basedir=$dir/mysql
datadir=$dir/datos/mysql
port=3306
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
default-time-zone='-05:00'
max_allowed_packet=64M
innodb_buffer_pool_size=256M
log-error=$dir/logs/mysql.err
[client]
port=3306
default-character-set=utf8mb4
"@
  $primeraVezMysql = -not (Test-Path "$Destino\datos\mysql\mysql")
  if ($primeraVezMysql) {
    Ejecutar "$Destino\mysql\bin\mysqld.exe" "--defaults-file=`"$myIni`" --initialize-insecure" 'mysql-init'
  }
  if (-not (Servicio-Existe 'GRE-MySQL')) {
    Ejecutar "$Destino\mysql\bin\mysqld.exe" "--install GRE-MySQL --defaults-file=`"$myIni`"" 'mysql-servicio'
  }
  Start-Service GRE-MySQL
  Esperar-Puerto 3306 60 'MySQL'

  if ($primeraVezMysql) {
    Mysql-Sql $Destino '' "ALTER USER 'root'@'localhost' IDENTIFIED BY '$($secretos.MysqlRoot)';"
  }
  Mysql-Sql $Destino $secretos.MysqlRoot @"
CREATE DATABASE IF NOT EXISTS guia_electronica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'gre'@'localhost' IDENTIFIED BY '$($secretos.MysqlApp)';
ALTER USER 'gre'@'localhost' IDENTIFIED BY '$($secretos.MysqlApp)';
GRANT ALL PRIVILEGES ON guia_electronica.* TO 'gre'@'localhost';
FLUSH PRIVILEGES;
"@

  # ------------------------------------------------------------------ 6
  Paso 'Aplicacion web (Laravel)'
  Copiar-App "$Paquete\app" "$Destino\app"
  $envPath = "$Destino\app\.env"
  if (-not (Test-Path $envPath)) {
    Escribir-Texto $envPath (Plantilla-Env @{
      DB_PASSWORD = $secretos.MysqlApp; GRE_RUC = $Ruc; GRE_RAZON_SOCIAL = $RazonSocial
      GRE_DIRECCION = $Direccion; GRE_TELEFONOS = $Telefonos
      GRE_FACTURACION_URL = $FacturacionUrl; GRE_FACTURACION_CONSULTAS_URL = $FacturacionConsultasUrl
      GRE_FACTURACION_ESTADO_URL = $FacturacionEstadoUrl; GRE_FACTURACION_CREDENCIAL = $FacturacionCredencial
      APP_URL = "http://localhost:$PuertoWeb"
    })
    Write-Host '  .env creado'
  } else {
    Write-Host '  .env existente: se conserva'
  }
  Push-Location "$Destino\app"
  try {
    if ((Get-Content $envPath) -match '^APP_KEY=\s*$') { Artisan $php 'key:generate --force' }
    Artisan $php 'config:clear'
    Artisan $php 'migrate --force'
    $salida = Artisan $php "gre:inicializar --usuario=$AdminUsuario"
    $claveAdmin = ($salida | Select-String '^ADMIN_CLAVE=(.+)$' | ForEach-Object { $_.Matches[0].Groups[1].Value }) | Select-Object -First 1
    if (-not (Test-Path "$Destino\app\public\storage")) {
      cmd /c mklink /J "$Destino\app\public\storage" "$Destino\app\storage\app\public" | Out-Null
    }
    Artisan $php 'config:cache'
    Artisan $php 'view:cache'
  } finally { Pop-Location }

  # ------------------------------------------------------------------ 7
  Paso 'Apache'
  Configurar-Apache $Destino $PuertoWeb
  Ejecutar "$Destino\apache\bin\httpd.exe" '-t' 'apache-sintaxis'
  if (-not (Servicio-Existe 'GRE-Apache')) {
    Ejecutar "$Destino\apache\bin\httpd.exe" '-k install -n GRE-Apache' 'apache-servicio'
  }
  Restart-Service GRE-Apache
  Abrir-Firewall 'GRE Web' $PuertoWeb

  # ------------------------------------------------------------------ 8
  Paso 'ApiGRE (Java)'
  $sql = Parsear-SqlServidor $SqlServidor
  if (-not (Tcp-Responde $sql.Host $sql.Puerto 5000)) {
    Write-Warning "No se alcanza SQL Server en $($sql.Host):$($sql.Puerto). ApiGRE arrancara pero no podra leer catalogos hasta que haya conexion."
  }
  New-Item -ItemType Directory -Force "$Destino\api\config" | Out-Null
  Detener-Api
  Copy-Item "$Paquete\api-gre.jar" "$Destino\api\api-gre.jar" -Force
  Escribir-Texto "$Destino\api\config\application.properties" @"
server.port=8181
server.address=127.0.0.1
spring.datasource.url=jdbc:sqlserver://$($sql.Jdbc);DatabaseName=$SqlBase;encrypt=false;trustServerCertificate=true
spring.datasource.username=$(Escapar-Properties $SqlUsuario)
spring.datasource.password=$(Escapar-Properties $SqlClave)
logging.file.name=$dir/logs/api-gre.log
"@
  Restringir-Acceso "$Destino\api\config\application.properties"
  Registrar-TareaApi $Destino
  schtasks /Run /TN 'GRE-ApiGRE' | Out-Null
  Esperar-Http 'http://127.0.0.1:8181/api/v1/health' 150 'ApiGRE'
  $procedimientosFaltantes = @(Revisar-ApiGRE 'http://127.0.0.1:8181/api/v1/health')

  # ------------------------------------------------------------------ 9
  Paso 'Verificacion final'
  Esperar-Http "http://127.0.0.1:$PuertoWeb/login" 60 'Aplicacion web'

  $resumen = @"
Guias Electronicas instaladas en $Destino
  Web:      http://$(Ip-De-Este-Equipo):$PuertoWeb
  Servicios: GRE-Apache, GRE-MySQL (Windows) y tarea programada GRE-ApiGRE
  Log de esta instalacion: $log
"@
  if ($claveAdmin) {
    $resumen += "`n  Usuario administrador: $AdminUsuario`n  Clave inicial: $claveAdmin   (cambiela al entrar)"
  }
  if ($procedimientosFaltantes.Count -gt 0) {
    $resumen += "`n`n  ATENCION: faltan procedimientos en SQL Server ($($procedimientosFaltantes -join ', ')).`n  Esas busquedas saldran vacias hasta instalarlos."
  }
  Escribir-Texto "$Destino\LEEME-INSTALACION.txt" $resumen
  Restringir-Acceso "$Destino\LEEME-INSTALACION.txt"
  Write-Host "`n$resumen" -ForegroundColor Green
  Write-Host "`nINSTALACION_OK"
}
catch {
  Write-Host "`nINSTALACION_FALLIDA: $($_.Exception.Message)" -ForegroundColor Red
  Write-Host "Revise el log: $log"
  Stop-Transcript | Out-Null
  exit 1
}
Stop-Transcript | Out-Null
