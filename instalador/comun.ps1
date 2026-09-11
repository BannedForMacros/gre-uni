# Funciones compartidas por instalar.ps1 y actualizar.ps1.
# Solo ASCII a proposito: PowerShell 5.1 lee los .ps1 sin BOM como ANSI y las
# tildes se corrompen. Los grupos se nombran por SID: en un Windows en espanol
# "Administrators" se llama "Administradores" y icacls fallaria.

$script:numeroPaso = 0
$SID_ADMINISTRADORES = '*S-1-5-32-544'
$SID_SYSTEM = '*S-1-5-18'

function Exigir-Administrador {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $principal = New-Object Security.Principal.WindowsPrincipal $id
  if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Ejecute PowerShell como Administrador.'
  }
}

function Paso([string]$texto) {
  $script:numeroPaso++
  Write-Host "`n[$script:numeroPaso] $texto" -ForegroundColor Cyan
}

function Buscar-Unico([string]$patron) {
  $f = @(Get-ChildItem $patron -ErrorAction SilentlyContinue)
  if ($f.Count -ne 1) { throw "Paquete incompleto: se esperaba un archivo $patron y hay $($f.Count)" }
  $f[0].FullName
}

function Escribir-Texto([string]$ruta, [string]$texto) {
  # UTF-8 SIN BOM: con BOM, Laravel lee la primera clave del .env como
  # "\uFEFFAPP_NAME" y la aplicacion arranca sin nombre ni entorno.
  $normalizado = $texto -replace "`r?`n", "`r`n"
  [IO.File]::WriteAllText($ruta, $normalizado, (New-Object Text.UTF8Encoding $false))
}

function Restringir-Acceso([string]$ruta) {
  $ErrorActionPreference = 'Continue'
  icacls $ruta /inheritance:r /grant:r "$($SID_ADMINISTRADORES):F" "$($SID_SYSTEM):F" | Out-Null
  if ($LASTEXITCODE -ne 0) { throw "No se pudo restringir el acceso a $ruta" }
}

function Nueva-Clave([int]$n = 20) {
  $c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
  $b = New-Object byte[] $n
  (New-Object Security.Cryptography.RNGCryptoServiceProvider).GetBytes($b)
  -join ($b | ForEach-Object { $c[$_ % $c.Length] })
}

function Leer-Secretos([string]$destino) {
  $ruta = "$destino\config\secretos.json"
  if (Test-Path $ruta) { return (Get-Content $ruta -Raw | ConvertFrom-Json) }
  if (Test-Path "$destino\datos\mysql\mysql") {
    throw "Existe una base MySQL pero falta $ruta. Sin ese archivo no se conoce la clave de root; restaurelo desde un respaldo."
  }
  $s = [pscustomobject]@{ MysqlRoot = (Nueva-Clave); MysqlApp = (Nueva-Clave) }
  Escribir-Texto $ruta ($s | ConvertTo-Json)
  Restringir-Acceso $ruta
  $s
}

function Puerto-Ocupado([int]$p) {
  [bool](netstat -ano | Select-String "^\s*TCP\s+\S+:$p\s+\S+\s+LISTENING")
}

function Servicio-Existe([string]$n) {
  [bool](Get-Service -Name $n -ErrorAction SilentlyContinue)
}

function Ejecutar {
  param([string]$Exe, [string]$Argumentos, [string]$Nombre, [string]$Directorio = '', [switch]$Devolver, [switch]$PermitirFallo)
  $psi = New-Object Diagnostics.ProcessStartInfo $Exe, $Argumentos
  $psi.UseShellExecute = $false
  $psi.RedirectStandardOutput = $true
  $psi.RedirectStandardError = $true
  $psi.CreateNoWindow = $true
  if ($Directorio) { $psi.WorkingDirectory = $Directorio }
  $p = [Diagnostics.Process]::Start($psi)
  $out = $p.StandardOutput.ReadToEndAsync()
  $err = $p.StandardError.ReadToEnd()
  $p.WaitForExit()
  $texto = ($out.Result + $err).TrimEnd()
  if ($texto) { Write-Host $texto }
  if ($p.ExitCode -ne 0 -and -not $PermitirFallo) { throw "$Nombre fallo (codigo $($p.ExitCode))" }
  if ($Devolver) { return $texto }
}

function Artisan([string]$php, [string]$comando) {
  $texto = Ejecutar -Exe $php -Argumentos "artisan $comando --no-interaction" -Nombre "artisan $comando" -Directorio (Get-Location).Path -Devolver
  $texto -split "`r?`n"
}

function Extraer-Si-Falta([string]$zip, [string]$destino, [string]$testigo) {
  if (Test-Path (Join-Path $destino $testigo)) { Write-Host "  $destino ya existe"; return }
  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $tmp = Join-Path $env:TEMP ('gre-' + [guid]::NewGuid())
  [IO.Compression.ZipFile]::ExtractToDirectory($zip, $tmp)
  # Cada zip trae su propia carpeta raiz (Apache24, mysql-5.7.44-winx64...).
  $hallado = Get-ChildItem $tmp -Recurse -Filter (Split-Path $testigo -Leaf) |
             Where-Object { $_.FullName.EndsWith('\' + $testigo) } | Select-Object -First 1
  if (-not $hallado) { throw "No se encontro $testigo dentro de $zip" }
  $origen = $hallado.FullName.Substring(0, $hallado.FullName.Length - $testigo.Length - 1)
  New-Item -ItemType Directory -Force (Split-Path $destino) | Out-Null
  if (Test-Path $destino) { Remove-Item $destino -Recurse -Force }
  Move-Item $origen $destino
  if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue }
  Write-Host "  $destino listo"
}

function Configurar-Php([string]$dir) {
  $ini = "$dir\php.ini"
  if (-not (Test-Path $ini)) { Copy-Item "$dir\php.ini-production" $ini }
  $t = [IO.File]::ReadAllText($ini)
  $d = $dir -replace '\\', '/'
  $t = $t -replace '(?m)^;?[ \t]*extension_dir[ \t]*=[ \t]*"ext"', "extension_dir = `"$d/ext`""
  # intl es obligatoria: la facturacion usa Normalizer para quitar tildes, y
  # sin ella el envio muere con "Class Normalizer not found".
  foreach ($e in 'curl', 'fileinfo', 'gd2', 'intl', 'mbstring', 'mysqli', 'openssl', 'pdo_mysql', 'zip') {
    if ($t -match "(?m)^extension=$e[ \t]*\r?$") { continue }
    if ($t -match "(?m)^;extension=$e[ \t]*\r?$") { $t = $t -replace "(?m)^;extension=$e([ \t]*\r?)$", "extension=$e`$1" }
    elseif (Test-Path "$dir\ext\php_$e.dll") { $t += "`r`nextension=$e" }
  }
  $valores = [ordered]@{ 'date.timezone' = 'America/Lima'; 'memory_limit' = '512M'; 'max_execution_time' = '120'; 'upload_max_filesize' = '20M'; 'post_max_size' = '25M' }
  foreach ($k in $valores.Keys) {
    $rx = New-Object regex ("(?m)^;?[ \t]*" + [regex]::Escape($k) + "[ \t]*=[^\r\n]*")
    if ($rx.IsMatch($t)) { $t = $rx.Replace($t, "$k = $($valores[$k])", 1) } else { $t += "`r`n$k = $($valores[$k])" }
  }
  Escribir-Texto $ini $t
  $modulos = Ejecutar -Exe "$dir\php.exe" -Argumentos '-m' -Nombre 'php -m' -Devolver
  if ($modulos -match 'Warning|Unable to load') { throw "PHP no pudo cargar alguna extension:`n$modulos" }
  foreach ($e in 'curl', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql') {
    if ($modulos -notmatch "(?m)^$e\r?$") { throw "PHP no cargo la extension $e" }
  }
}

function Con-CnfMysql([string]$claveRoot, [scriptblock]$bloque) {
  $cnf = Join-Path $env:TEMP ('gre-' + [guid]::NewGuid() + '.cnf')
  Escribir-Texto $cnf "[client]`nuser=root`npassword=$claveRoot`nhost=127.0.0.1`nport=3306"
  try { & $bloque $cnf } finally { Remove-Item $cnf -Force -ErrorAction SilentlyContinue }
}

function Mysql-Sql([string]$destino, [string]$claveRoot, [string]$sql, [string]$base = '') {
  $archivo = Join-Path $env:TEMP ('gre-' + [guid]::NewGuid() + '.sql')
  Escribir-Texto $archivo $sql
  try {
    Con-CnfMysql $claveRoot {
      param($cnf)
      Ejecutar -Exe "$destino\mysql\bin\mysql.exe" -Argumentos "--defaults-extra-file=`"$cnf`" -N -B $base -e `"source $($archivo -replace '\\', '/')`"" -Nombre 'mysql' -Devolver
    }
  } finally { Remove-Item $archivo -Force -ErrorAction SilentlyContinue }
}

function Mysql-Volcar([string]$destino, [string]$claveRoot, [string]$archivo) {
  Con-CnfMysql $claveRoot {
    param($cnf)
    Ejecutar -Exe "$destino\mysql\bin\mysqldump.exe" -Argumentos "--defaults-extra-file=`"$cnf`" --single-transaction --routines --triggers --result-file=`"$archivo`" guia_electronica" -Nombre 'mysqldump' | Out-Null
  }
  if (-not (Test-Path $archivo) -or (Get-Item $archivo).Length -lt 1000) { throw "El respaldo de la base salio vacio: $archivo" }
  Write-Host "  respaldo: $archivo ($([int]((Get-Item $archivo).Length / 1KB)) KB)"
}

function Esperar-Puerto([int]$p, [int]$segundos, [string]$que) {
  for ($i = 0; $i -lt $segundos; $i++) {
    if (Tcp-Responde '127.0.0.1' $p 1000) { Write-Host "  $que escucha en el puerto $p"; return }
    Start-Sleep 1
  }
  throw "$que no abrio el puerto $p en $segundos segundos"
}

function Tcp-Responde([string]$h, [int]$p, [int]$ms) {
  $c = New-Object Net.Sockets.TcpClient
  try { $iar = $c.BeginConnect($h, $p, $null, $null); ($iar.AsyncWaitHandle.WaitOne($ms) -and $c.Connected) }
  catch { $false }
  finally { $c.Close() }
}

function Esperar-Http([string]$url, [int]$segundos, [string]$que) {
  $fin = (Get-Date).AddSeconds($segundos); $ultimo = ''
  while ((Get-Date) -lt $fin) {
    try {
      $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 15
      Write-Host "  $que responde (HTTP $($r.StatusCode))"
      return
    } catch { $ultimo = $_.Exception.Message; Start-Sleep 3 }
  }
  throw "$que no respondio en $segundos segundos: $url ($ultimo)"
}

function Copiar-App([string]$origen, [string]$destino) {
  # /MIR deja el codigo identico al paquete, pero NUNCA toca lo del cliente:
  # .env, archivos subidos y logs (storage) ni el enlace public\storage.
  $ErrorActionPreference = 'Continue'
  New-Item -ItemType Directory -Force $destino | Out-Null
  robocopy $origen $destino /MIR /NFL /NDL /NJH /NJS /NP /R:2 /W:2 /XF .env /XD "$origen\storage" "$destino\storage" "$destino\public\storage" | Out-Null
  if ($LASTEXITCODE -ge 8) { throw "La copia de la aplicacion fallo (robocopy $LASTEXITCODE)" }
  # storage: solo se agregan las carpetas y archivos que falten.
  robocopy "$origen\storage" "$destino\storage" /E /XC /XN /XO /NFL /NDL /NJH /NJS /NP | Out-Null
  if ($LASTEXITCODE -ge 8) { throw "La copia de storage fallo (robocopy $LASTEXITCODE)" }
  $global:LASTEXITCODE = 0
}

function Valor-Env([string]$v) {
  if ($v -notmatch "'") { return "'$v'" }
  '"' + (($v -replace '\\', '\\') -replace '"', '\"') + '"'
}

function Plantilla-Env([hashtable]$v) {
  $lineas = @(
    'APP_NAME="Guias Electronicas"', 'APP_ENV=production', 'APP_KEY=', 'APP_DEBUG=false',
    "APP_URL=$($v.APP_URL)", 'LOG_CHANNEL=daily', 'LOG_LEVEL=warning',
    'DB_CONNECTION=mysql', 'DB_HOST=127.0.0.1', 'DB_PORT=3306', 'DB_DATABASE=guia_electronica', 'DB_USERNAME=gre',
    "DB_PASSWORD=$(Valor-Env $v.DB_PASSWORD)",
    'CACHE_DRIVER=file', 'SESSION_DRIVER=file', 'SESSION_LIFETIME=480', 'QUEUE_CONNECTION=sync',
    'GRE_API_URL=http://127.0.0.1:8181', 'GRE_API_TIMEOUT=30', 'GRE_IGV_TASA=0.18', 'GRE_VALIDAR_STOCK=false'
  )
  foreach ($k in 'GRE_RUC', 'GRE_RAZON_SOCIAL', 'GRE_DIRECCION', 'GRE_TELEFONOS', 'GRE_FACTURACION_URL',
                 'GRE_FACTURACION_CONSULTAS_URL', 'GRE_FACTURACION_ESTADO_URL', 'GRE_FACTURACION_CREDENCIAL') {
    $lineas += "$k=$(Valor-Env ([string]$v[$k]))"
  }
  ($lineas -join "`r`n") + "`r`n"
}

function Configurar-Apache([string]$destino, [int]$puerto) {
  $d = $destino -replace '\\', '/'
  $conf = "$destino\apache\conf\httpd.conf"
  $t = [IO.File]::ReadAllText($conf)
  # Apache Lounge cambia de estilo entre compilaciones: unas definen SRVROOT y
  # otras escriben ServerRoot directo. La VS17 2.4.66 trae
  # ServerRoot "C:/Apache24-64" y ningun Define, asi que reemplazar solo la
  # variable dejaba el servidor apuntando a una carpeta inexistente y httpd
  # moria con "ServerRoot must be a valid directory". Se cubren los dos estilos.
  $t = $t -replace '(?m)^Define SRVROOT [^\r\n]*', "Define SRVROOT `"$d/apache`""
  if ($t -notmatch '(?m)^Define SRVROOT ') {
    $t = $t -replace '(?m)^ServerRoot [^\r\n]*', "Define SRVROOT `"$d/apache`"`r`nServerRoot `"$d/apache`""
  } else {
    $t = $t -replace '(?m)^ServerRoot [^\r\n]*', "ServerRoot `"$d/apache`""
  }
  $t = $t -replace '(?m)^Listen \d+', "Listen $puerto"
  $t = $t -replace '(?m)^#?ServerName [^\r\n]*', "ServerName localhost:$puerto"
  # Los modulos vienen comentados como "# LoadModule ..." con un espacio detras
  # del almohadilla en unas compilaciones y sin el en otras. El patron anterior
  # exigia "#LoadModule" pegado, no casaba, y Laravel se quedaba sin mod_rewrite:
  # con eso ninguna URL de la aplicacion funciona.
  $t = $t -replace '(?m)^#\s*LoadModule rewrite_module', 'LoadModule rewrite_module'
  $t = $t -replace '(?m)^DocumentRoot ', '#DocumentRoot '
  if ($t -notmatch '(?m)^Include conf/extra/gre\.conf') { $t += "`r`nInclude conf/extra/gre.conf`r`n" }
  Escribir-Texto $conf $t
  if ((Get-Content $conf | Select-String ('^ServerRoot "' + [regex]::Escape("$d/apache") + '"')).Count -eq 0) {
    throw "No se pudo apuntar ServerRoot a $d/apache en httpd.conf"
  }
  if ((Get-Content $conf | Select-String '^LoadModule rewrite_module').Count -eq 0) {
    throw "No se pudo activar mod_rewrite en httpd.conf"
  }

  # Apache corre como servicio y NO ve el PATH de PHP: las DLL de las que
  # dependen las extensiones (OpenSSL, ICU para intl, libssh2 para curl) se
  # cargan explicitamente y en orden de dependencia.
  $cargar = New-Object Collections.Generic.List[string]
  foreach ($prefijo in 'libcrypto', 'libssl', 'libssh2', 'nghttp2', 'icudt', 'icuuc', 'icuin', 'icuio', 'php7ts') {
    Get-ChildItem "$destino\php\$prefijo*.dll" -ErrorAction SilentlyContinue |
      ForEach-Object { if (-not $cargar.Contains($_.FullName)) { $cargar.Add($_.FullName) } }
  }
  $lineasLoad = ($cargar | ForEach-Object { 'LoadFile "' + ($_ -replace '\\', '/') + '"' }) -join "`r`n"
  Escribir-Texto "$destino\apache\conf\extra\gre.conf" @"
# Generado por instalar.ps1. Se reescribe en cada instalacion o actualizacion.
$lineasLoad
LoadModule php7_module "$d/php/php7apache2_4.dll"
PHPIniDir "$d/php"
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
DocumentRoot "$d/app/public"
<Directory "$d/app/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
DirectoryIndex index.php index.html
ErrorLog "$d/logs/apache-error.log"
"@
}

function Abrir-Firewall([string]$nombre, [int]$puerto) {
  $ErrorActionPreference = 'Continue'
  netsh advfirewall firewall show rule name="$nombre" | Out-Null
  if ($LASTEXITCODE -ne 0) {
    netsh advfirewall firewall add rule name="$nombre" dir=in action=allow protocol=TCP localport=$puerto | Out-Null
  }
  $global:LASTEXITCODE = 0
}

function Parsear-SqlServidor([string]$s) {
  $puerto = 1433; $instancia = $null; $h = $s
  if ($s -match '^(.+),(\d+)$') { $h = $matches[1]; $puerto = [int]$matches[2] }
  if ($h -match '^(.+)\\(.+)$') { $h = $matches[1]; $instancia = $matches[2] }
  $jdbc = if ($instancia -and $s -notmatch ',\d+$') { "$h;instanceName=$instancia" } else { "$($h):$puerto" }
  [pscustomobject]@{ Host = $h; Puerto = $puerto; Jdbc = $jdbc }
}

function Escapar-Properties([string]$v) { $v -replace '\\', '\\' }

function Detener-Api {
  $ErrorActionPreference = 'Continue'
  schtasks.exe /End /TN 'GRE-ApiGRE' 2>&1 | Out-Null
  Get-WmiObject Win32_Process -Filter "Name='java.exe'" |
    Where-Object { $_.CommandLine -like '*api-gre.jar*' } |
    ForEach-Object {
      # Terminate() lanza "No encontrado" si el proceso murio entre la consulta
      # y la llamada, y esa excepcion abortaba la actualizacion entera: se
      # respaldaba, se detenian los servicios y se revertia sin haber copiado
      # nada. Stop-Process no lanza y se ignora lo que ya no exista.
      try { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue } catch { }
    }
  Start-Sleep 2
  $global:LASTEXITCODE = 0
}

function Registrar-TareaApi([string]$destino) {
  $ErrorActionPreference = 'Continue'
  $cmd = "$destino\api\iniciar-api.cmd"
  Escribir-Texto $cmd ("@echo off`ncd /d `"$destino\api`"`n`"$destino\jre\bin\java.exe`" -Xms128m -Xmx512m -Dfile.encoding=UTF-8 -jar api-gre.jar --spring.config.additional-location=file:./config/ >> `"$destino\logs\api-gre-consola.log`" 2>&1`n")
  # Tarea por XML y no con /SC ONSTART: asi se quita el limite de 72 horas que
  # Windows pone por defecto, y se reinicia sola si Java se cae.
  $xml = @"
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <RegistrationInfo><Description>ApiGRE de Guias Electronicas (DBPeru)</Description></RegistrationInfo>
  <Triggers><BootTrigger><Enabled>true</Enabled></BootTrigger></Triggers>
  <Principals><Principal id="Author"><UserId>S-1-5-18</UserId><RunLevel>HighestAvailable</RunLevel></Principal></Principals>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <ExecutionTimeLimit>PT0S</ExecutionTimeLimit>
    <RestartOnFailure><Interval>PT1M</Interval><Count>999</Count></RestartOnFailure>
    <StartWhenAvailable>true</StartWhenAvailable>
    <Enabled>true</Enabled>
  </Settings>
  <Actions Context="Author"><Exec><Command>$cmd</Command></Exec></Actions>
</Task>
"@
  $archivo = Join-Path $env:TEMP 'gre-tarea-api.xml'
  [IO.File]::WriteAllText($archivo, $xml, [Text.Encoding]::Unicode)
  $salida = schtasks.exe /Create /TN 'GRE-ApiGRE' /XML $archivo /F 2>&1
  if ($LASTEXITCODE -ne 0) { throw "No se pudo registrar la tarea GRE-ApiGRE: $salida" }
  Remove-Item $archivo -Force
  $global:LASTEXITCODE = 0
}

function Revisar-ApiGRE([string]$url) {
  # La ApiGRE informa que procedimientos del ERP faltan en SQL Server. No se
  # detiene la instalacion -el SQL Server es del cliente-, pero queda escrito:
  # un procedimiento ausente no da errores, solo listas vacias en pantalla.
  try { $salud = Invoke-RestMethod -Uri $url -TimeoutSec 30 }
  catch { Write-Warning "No se pudo leer la salud de ApiGRE: $($_.Exception.Message)"; return @() }
  if ($salud.sqlServer -ne 'UP') {
    Write-Warning 'ApiGRE no alcanza SQL Server: revise servidor, usuario y clave en api\config\application.properties.'
  }
  $faltan = @($salud.procedimientosFaltantes | Where-Object { $_ })
  foreach ($p in $faltan) { Write-Warning "Falta en SQL Server el procedimiento $p" }
  return $faltan
}

function Leer-Datos([string]$ruta) {
  # Datos del cliente en un archivo KEY=VALOR, para que el instalador no
  # pregunte nada cuando el tecnico lo prepara antes de ir donde el cliente.
  $d = @{}
  if ($ruta -and (Test-Path $ruta)) {
    foreach ($l in Get-Content $ruta) {
      if ($l -match '^\s*#') { continue }
      if ($l -match '^\s*([A-Za-z_]+)\s*=\s*(.*)$') { $d[$matches[1].ToLower()] = $matches[2].Trim() }
    }
  }
  return $d
}

function Preguntar {
  # Una pregunta con valor por defecto y validacion. Si ya hay valor -por
  # parametro o por el archivo de datos- no pregunta nada.
  param(
    [string]$Texto, [string]$Valor = '', [string]$PorDefecto = '',
    [switch]$Oculto, [scriptblock]$Validar = $null, [string]$Ayuda = '', [switch]$SinPreguntas
  )
  if ($Valor) { return $Valor }
  if ($SinPreguntas) { throw "Falta un dato obligatorio: $Texto" }

  while ($true) {
    $sufijo = if ($PorDefecto) { " [$PorDefecto]" } else { '' }
    if ($Oculto) {
      $seguro = Read-Host "  $Texto$sufijo" -AsSecureString
      $r = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
             [Runtime.InteropServices.Marshal]::SecureStringToBSTR($seguro))
    } else {
      $r = Read-Host "  $Texto$sufijo"
    }
    if (-not $r) { $r = $PorDefecto }
    if (-not $Validar) { return $r }
    if (& $Validar $r) { return $r }
    Write-Host "  $Ayuda" -ForegroundColor Yellow
  }
}

function Ip-De-Este-Equipo {
  $ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' } |
        Select-Object -First 1
  if ($ip) { return $ip.IPAddress }
  return 'localhost'
}
