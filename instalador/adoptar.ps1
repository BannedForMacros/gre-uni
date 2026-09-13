<#
  Adopcion de una instalacion EXISTENTE de Guias Electronicas (DBPeru)

  PARA QUE SIRVE
    Hay alrededor de cien clientes con el sistema funcionando desde antes de que
    existiera instalar.ps1. Cada uno esta montado como quiso quien lo instalo:
    Laragon, XAMPP, WampServer, IIS, un Apache suelto, MySQL o MariaDB como
    servicio en el puerto que sea, y la aplicacion en C:\laragon\www\algo, en
    D:\wamp64\www, en C:\inetpub\wwwroot o en una carpeta cualquiera.

    Este script los pone al dia SIN moverlos de sitio y SIN cambiarles el
    servidor web ni el motor de base de datos. Despues de correrlo, ese cliente
    recibe actualizaciones como cualquier otro.

  NO ASUME NADA
    Ni la ruta, ni el puerto 3306, ni que exista Laragon, ni que haya un
    mysql.exe en el PATH. Todo se descubre y todo se comprueba de verdad antes
    de tocar nada. Si algo no cuadra, lo dice y no sigue.

  QUE HACE, EN ORDEN
    1. Busca el motor de base de datos y un cliente mysql con que hablarle.
    2. Busca las instalaciones del GRE que haya en el equipo y las lista.
    3. Prueba la conexion a la base DE VERDAD (servidor / usuario / base).
    4. Mira quien ocupa los puertos y con que PHP se puede correr artisan.
    5. RESPALDA la base y la carpeta de la aplicacion.
    6. Copia el codigo nuevo conservando el .env del cliente.
    7. gre:baseline --dry-run, gre:baseline, migrate --force, gre:inicializar.
    8. Verifica que no falte ni sobre una guia, que la web responda y que el
       login funcione. Si algo falla, REVIERTE con el respaldo.

  LO QUE NO HACE
    No emite nada a SUNAT y no llama al facturador. No detiene el servidor web
    del cliente: no sabemos como volver a levantarlo -en Laragon se arranca
    desde su panel- y dejarlo caido seria peor que la actualizacion.
    Tampoco corre config:cache: si el codigo del cliente llama a env() fuera de
    config/, cachear la configuracion le apagaria esos valores. Solo se limpia.

  Uso (doble clic en ADOPTAR.cmd, o desde PowerShell como Administrador):
    powershell -ExecutionPolicy Bypass -File .\adoptar.ps1
    powershell -ExecutionPolicy Bypass -File .\adoptar.ps1 -SoloRevisar
#>
[CmdletBinding()]
param(
  # Todo esto es opcional: si no se indica, se descubre o se pregunta.
  [string]$Ruta = '',
  [string]$Servidor = '',
  [int]$Puerto = 0,
  [string]$Base = '',
  [string]$Usuario = '',
  [string]$Clave = '',
  [string]$Php = '',
  [string]$Url = '',
  [string]$Respaldos = '',
  [int]$PuertoWeb = 80,
  # Descubre, prueba y muestra que haria. No escribe absolutamente nada.
  [switch]$SoloRevisar,
  # Para tecnicos: no pregunta nada y falla si falta un dato.
  [switch]$SinPreguntas
)

$ErrorActionPreference = 'Stop'
$Paquete = $PSScriptRoot
. (Join-Path $Paquete 'comun.ps1')
. (Join-Path $Paquete 'mysql.ps1')

Exigir-Administrador

# Carpetas donde los distintos servidores web dejan los sitios. Ninguna es un
# caso especial: es solo una lista de sitios donde mirar primero.
$CARPETAS_WEB = @(
  'laragon\www', 'xampp\htdocs', 'wamp64\www', 'wamp\www', 'inetpub\wwwroot',
  'Apache24\htdocs', 'Apache2.4\htdocs', 'Apache\htdocs', 'nginx\html',
  'www', 'htdocs', 'web', 'sitios', 'proyectos', 'DBPeru\GRE', 'DBPeru',
  'UwAmp\www', 'EasyPHP\data\localweb', 'AppServ\www', 'Bitnami', 'sites'
)

# Donde suele estar el PHP con el que ya corre la aplicacion del cliente.
$CARPETAS_PHP = @(
  'laragon\bin\php', 'xampp\php', 'wamp64\bin\php', 'wamp\bin\php',
  'Program Files\PHP', 'Program Files (x86)\PHP', 'php', 'PHP',
  'DBPeru\GRE\php', 'UwAmp\bin\php', 'EasyPHP\binaries\php', 'AppServ\php'
)

# Claves que las versiones nuevas esperan y que un .env viejo no tiene. Solo se
# AGREGAN las que falten: no se toca ni una linea de las que ya estan.
$CLAVES_QUE_FALTAN = [ordered]@{
  'GRE_API_URL'         = 'http://127.0.0.1:8181'
  'GRE_API_TIMEOUT'     = '30'
  'GRE_IGV_TASA'        = '0.18'
  'GRE_VALIDAR_STOCK'   = 'false'
  'CONSIGNADOS_ENABLED' = 'false'
}

# ---------------------------------------------------------------------------
# Leer el .env del cliente
# ---------------------------------------------------------------------------

function Leer-Env([string]$ruta) {
  <#
    Laravel lee el .env con phpdotenv, que tolera muchas formas de escribir lo
    mismo. Esta lectura tiene que tolerarlas igual o se rechazaria a un cliente
    que esta perfectamente bien.

    El caso que mas duele, comprobado en campo: el instalador escribe la clave
    entre COMILLAS SIMPLES (DB_PASSWORD='...'). Quitando solo las comillas
    dobles, la clave se manda con los apostrofes dentro y MySQL responde
    "Access denied ... (using password: YES)". El mensaje hace pensar que la
    credencial del cliente esta mal cuando esta bien.

    Por eso aqui:
      - se recortan espacios alrededor del "=" y del valor
      - se quitan comillas SIMPLES y DOBLES
      - dentro de comillas dobles se deshacen los escapes \" y \\
      - dentro de comillas simples NO se toca nada, igual que phpdotenv
      - la almohadilla solo abre comentario si va fuera de comillas y despues
        de un espacio: una clave que contiene "#" es un valor, no un comentario
      - se acepta el prefijo "export" y se ignora el BOM
  #>
  $d = @{}
  if (-not $ruta -or -not (Test-Path $ruta)) { return $d }

  foreach ($linea in [IO.File]::ReadAllLines($ruta)) {
    $l = $linea -replace "^\uFEFF", ''
    $l = $l.Trim()
    if ($l -eq '' -or $l.StartsWith('#')) { continue }
    if ($l -notmatch '^(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_.]*)[ \t]*=[ \t]*(.*)$') { continue }

    $clave = $matches[1]
    $v = $matches[2].Trim()

    if ($v.Length -ge 2 -and $v.StartsWith('"') -and $v.EndsWith('"')) {
      $v = $v.Substring(1, $v.Length - 2)
      $v = $v -replace '\\"', '"'
      $v = $v -replace '\\\\', '\'
    }
    elseif ($v.Length -ge 2 -and $v.StartsWith("'") -and $v.EndsWith("'")) {
      # phpdotenv no interpreta escapes dentro de comillas simples.
      $v = $v.Substring(1, $v.Length - 2)
    }
    else {
      # Sin comillas: el "#" precedido de espacio si abre comentario.
      if ($v -match '^(.*?)[ \t]+#') { $v = $matches[1] }
      $v = $v.Trim()
    }

    $d[$clave] = $v
  }
  return $d
}

function Agregar-Claves-Que-Falten([string]$rutaEnv, [hashtable]$actuales) {
  # Se AGREGA al final, nunca se reescribe lo que ya hay. El .env del cliente
  # lleva su APP_KEY -si se pierde, no se puede descifrar nada de lo guardado-
  # sus credenciales y sus parametros.
  $faltan = @()
  foreach ($k in $CLAVES_QUE_FALTAN.Keys) {
    if (-not $actuales.ContainsKey($k)) { $faltan += $k }
  }
  if ($faltan.Count -eq 0) { Write-Host '  .env: no falta ninguna clave'; return @() }

  $texto = [IO.File]::ReadAllText($rutaEnv)
  if ($texto -notmatch "(`r?`n)$") { $texto += "`r`n" }
  $texto += "`r`n# Agregado por adoptar.ps1 el $(Get-Date -Format 'yyyy-MM-dd HH:mm').`r`n"
  $texto += "# Son claves que la version nueva espera. Los valores de arriba no se tocaron.`r`n"
  foreach ($k in $faltan) { $texto += "$k=$($CLAVES_QUE_FALTAN[$k])`r`n" }
  Escribir-Texto $rutaEnv $texto

  Write-Host "  .env: se agregaron $($faltan.Count) clave(s): $($faltan -join ', ')"
  return $faltan
}

# ---------------------------------------------------------------------------
# Descubrir instalaciones del GRE
# ---------------------------------------------------------------------------

function Parece-Gre([string]$dir) {
  # Una instalacion del GRE se reconoce por sus modelos de guias. Se miran
  # varias senales porque las versiones viejas no tienen todos los archivos.
  foreach ($f in @('app\Models\GuiaSalida.php', 'app\Models\GuiaIngreso.php',
                   'app\Http\Controllers\Guia\GuiaSalidaController.php')) {
    if (Test-Path (Join-Path $dir $f)) { return $true }
  }
  $rutas = Join-Path $dir 'routes\web.php'
  if (Test-Path $rutas) {
    if ((Get-Content $rutas -Raw -ErrorAction SilentlyContinue) -match 'guiasalida|guiaingreso') { return $true }
  }
  return $false
}

function Buscar-InstalacionesGre {
  param([switch]$Profundo)

  $raices = @()
  $unidades = @(Get-PSDrive -PSProvider FileSystem -ErrorAction SilentlyContinue |
                Where-Object { $_.Root -match '^[A-Za-z]:\\$' })

  foreach ($u in $unidades) {
    foreach ($c in $CARPETAS_WEB) {
      $r = Join-Path $u.Root $c
      if (Test-Path $r) { $raices += [pscustomobject]@{ Ruta = $r; Fondo = 3 } }
    }
    # La raiz de la unidad, poco profundo: cubre D:\gre\artisan.
    $raices += [pscustomobject]@{ Ruta = $u.Root; Fondo = 2 }
  }

  if ($Profundo) {
    foreach ($u in $unidades) { $raices += [pscustomobject]@{ Ruta = $u.Root; Fondo = 6 } }
  }

  $candidatos = @()
  $vistos = @{}
  foreach ($r in $raices) {
    $encontrados = @()
    try {
      $encontrados = @(Get-ChildItem -Path $r.Ruta -Filter 'artisan' -File -Recurse -Depth $r.Fondo -Force -ErrorAction SilentlyContinue)
    } catch { continue }

    foreach ($a in $encontrados) {
      $dir = $a.DirectoryName
      if ($vistos.ContainsKey($dir.ToLower())) { continue }
      $vistos[$dir.ToLower()] = $true

      $env_ = Join-Path $dir '.env'
      if (-not (Test-Path $env_)) { continue }
      $valores = Leer-Env $env_
      # Sin APP_KEY no es una instalacion en uso: es una copia a medio hacer.
      if (-not $valores.ContainsKey('APP_KEY') -or -not $valores['APP_KEY']) { continue }
      if (-not (Parece-Gre $dir)) { continue }

      # Una carpeta de respaldo tiene artisan, .env y APP_KEY igual que la
      # instalacion viva: es una copia exacta de una. Si se adoptara el
      # respaldo en vez del sistema en uso, el cliente terminaria trabajando
      # sobre datos congelados y creyendo que migro bien. No se ofrecen.
      # Para adoptar una a proposito esta el parametro -Ruta.
      if ($dir -match '(?i)[\\/](respaldos?|backups?)[\\/]' -or
          $dir -match '(?i)[\\/][^\\/]*-respaldos[\\/]') { continue }

      $candidatos += [pscustomobject]@{
        Ruta       = $dir
        Env        = $valores
        Base       = [string]$valores['DB_DATABASE']
        Servidor   = $(if ($valores['DB_HOST']) { [string]$valores['DB_HOST'] } else { '127.0.0.1' })
        Puerto     = $(if ($valores['DB_PORT']) { [int]$valores['DB_PORT'] } else { 0 })
        Usuario    = [string]$valores['DB_USERNAME']
        Clave      = [string]$valores['DB_PASSWORD']
        Guias      = -1
        Nota       = ''
        TieneBaseline = (Test-Path (Join-Path $dir 'app\Console\Commands\GreBaseline.php'))
      }
    }
  }
  return $candidatos
}

# ---------------------------------------------------------------------------
# PHP con el que correr artisan
# ---------------------------------------------------------------------------

function Probar-Php([string]$exe) {
  # Tiene que ser una version que soporte la aplicacion y traer pdo_mysql. Un
  # PHP demasiado nuevo arranca pero llena la salida de avisos de obsolescencia
  # de Laravel 8, y uno sin pdo_mysql no puede ni abrir la base.
  if (-not $exe -or -not (Test-Path $exe)) { return $null }
  try {
    $v = Ejecutar -Exe $exe -Argumentos '-r "echo PHP_MAJOR_VERSION*100+PHP_MINOR_VERSION;"' -Nombre 'php -r' -Devolver -PermitirFallo -Silencioso
    $n = 0
    if ($v -match '(\d{3,4})') { $n = [int]$matches[1] }
    if ($n -lt 704) { return $null }
    $m = Ejecutar -Exe $exe -Argumentos '-m' -Nombre 'php -m' -Devolver -PermitirFallo -Silencioso
    if ($m -notmatch '(?m)^pdo_mysql\r?$') { return $null }
    return [pscustomobject]@{ Exe = $exe; Version = $n; Ideal = ($n -ge 704 -and $n -le 802) }
  } catch { return $null }
}

function Buscar-Php([string]$appDir) {
  $candidatos = @()
  if ($Php) { $candidatos += $Php }

  # 1. El PHP que este junto a la aplicacion o un nivel arriba.
  $padre = Split-Path $appDir -Parent
  foreach ($p in @($appDir, $padre, (Split-Path $padre -Parent))) {
    if ($p) { $candidatos += (Join-Path $p 'php\php.exe') }
  }

  # 2. El del PATH.
  $enPath = Get-Command 'php.exe' -ErrorAction SilentlyContinue
  if ($enPath) { $candidatos += $enPath.Source }

  # 3. Las carpetas habituales de cada stack.
  foreach ($u in (Get-PSDrive -PSProvider FileSystem -ErrorAction SilentlyContinue | Where-Object { $_.Root -match '^[A-Za-z]:\\$' })) {
    foreach ($c in $CARPETAS_PHP) {
      $base = Join-Path $u.Root $c
      if (-not (Test-Path $base)) { continue }
      $candidatos += (Join-Path $base 'php.exe')
      foreach ($sub in (Get-ChildItem $base -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 10)) {
        $candidatos += (Join-Path $sub.FullName 'php.exe')
      }
    }
  }

  $validos = @()
  foreach ($c in ($candidatos | Where-Object { $_ } | Select-Object -Unique)) {
    $r = Probar-Php $c
    if ($r) { $validos += $r }
  }
  # Primero los que estan en el rango que soporta la aplicacion.
  @($validos | Sort-Object @{ Expression = { -[int]$_.Ideal } }, @{ Expression = { $_.Version } })
}

# ---------------------------------------------------------------------------
# Puertos: quien los ocupa, sea quien sea
# ---------------------------------------------------------------------------

function Quien-Usa-Puerto([int]$p) {
  # Devuelve el proceso que escucha en ese puerto, venga de donde venga. No se
  # busca "Laragon" ni ningun programa concreto: se pregunta al sistema.
  $pid_ = 0
  try {
    $c = Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction Stop | Select-Object -First 1
    if ($c) { $pid_ = [int]$c.OwningProcess }
  } catch {
    # Windows 7 no tiene Get-NetTCPConnection.
    $linea = netstat -ano | Select-String "^\s*TCP\s+\S+:$p\s+\S+\s+LISTENING\s+(\d+)" | Select-Object -First 1
    if ($linea) { $pid_ = [int]$linea.Matches[0].Groups[1].Value }
  }
  if ($pid_ -le 0) { return $null }

  $nombre = ''; $ruta = ''; $servicio = ''
  try {
    $proc = Get-Process -Id $pid_ -ErrorAction Stop
    $nombre = $proc.ProcessName
    try { $ruta = $proc.Path } catch { }
  } catch { }
  try {
    $s = @(Get-CimInstance Win32_Service -Filter "ProcessId=$pid_" -ErrorAction SilentlyContinue | Select-Object -First 1)
    if ($s.Count -gt 0) { $servicio = [string]$s[0].Name }
  } catch { }

  [pscustomobject]@{ Puerto = $p; Pid = $pid_; Proceso = $nombre; Ruta = $ruta; Servicio = $servicio }
}

function Describir-Puerto([int]$p) {
  $q = Quien-Usa-Puerto $p
  if (-not $q) { return "  puerto $p : libre" }
  $quien = if ($q.Servicio) { "servicio $($q.Servicio) ($($q.Proceso), PID $($q.Pid))" } else { "$($q.Proceso) (PID $($q.Pid))" }
  $texto = "  puerto $p : lo usa $quien"
  if ($q.Ruta) { $texto += "`n             $($q.Ruta)" }
  return $texto
}

function Detener-Lo-Del-Puerto([int]$p) {
  $q = Quien-Usa-Puerto $p
  if (-not $q) { return $true }
  try {
    if ($q.Servicio) { Stop-Service -Name $q.Servicio -Force -ErrorAction Stop }
    else { Stop-Process -Id $q.Pid -Force -ErrorAction Stop }
    Start-Sleep 2
    return (-not (Quien-Usa-Puerto $p))
  } catch {
    Write-Warning "No se pudo detener lo que ocupa el puerto $p : $($_.Exception.Message)"
    return $false
  }
}

# ---------------------------------------------------------------------------
# Verificacion de la aplicacion
# ---------------------------------------------------------------------------

function Probar-Web([string]$url) {
  try {
    $r = Invoke-WebRequest -Uri "$url/login" -UseBasicParsing -TimeoutSec 20
    return [pscustomobject]@{ Ok = ([int]$r.StatusCode -eq 200); Codigo = [int]$r.StatusCode; Detalle = '' }
  } catch {
    $codigo = 0
    if ($_.Exception.Response) { try { $codigo = [int]$_.Exception.Response.StatusCode } catch { } }
    return [pscustomobject]@{ Ok = $false; Codigo = $codigo; Detalle = $_.Exception.Message }
  }
}

function Probar-Login([string]$url) {
  <#
    Comprueba que se puede entrar SIN usar las credenciales de nadie.

    Pide la pantalla de login, comprueba que trae el campo de usuario -que es
    donde fallo una entrega: el formulario mandaba "username" y Laravel validaba
    "email", asi que nadie podia entrar- y manda un intento con un usuario que
    no existe. Si la aplicacion responde "credenciales incorrectas" en vez de un
    error 500, entonces el formulario, la sesion, el token CSRF, la consulta a
    la base y el hash de claves funcionan de punta a punta.

    No toca ninguna cuenta real y no bloquea a nadie: es un solo intento con un
    usuario inventado.
  #>
  $r = [pscustomobject]@{ Ok = $false; Motivo = ''; CampoUsuario = $false }
  try {
    $pagina = Invoke-WebRequest -Uri "$url/login" -UseBasicParsing -SessionVariable sesion -TimeoutSec 20
    if ([int]$pagina.StatusCode -ne 200) { $r.Motivo = "la pantalla de login respondio $($pagina.StatusCode)"; return $r }

    $r.CampoUsuario = ($pagina.Content -match 'name="username"')
    if (-not $r.CampoUsuario) {
      $r.Motivo = 'la pantalla de login no tiene el campo de usuario'
      return $r
    }

    $token = ''
    if ($pagina.Content -match 'name="_token"[^>]*value="([^"]+)"') { $token = $matches[1] }
    elseif ($pagina.Content -match 'value="([^"]+)"[^>]*name="_token"') { $token = $matches[1] }

    $cuerpo = @{
      _token   = $token
      username = 'comprobacion-adopcion-' + (Get-Random)
      password = 'no-es-la-clave-de-nadie'
    }
    # Con credenciales incorrectas Laravel responde 302 de vuelta al login: esa
    # ES la respuesta sana. En PowerShell 5.1, -MaximumRedirection 0 convierte
    # ese 302 en error terminante, y leer .Response de esa excepcion lanza
    # "Operacion no valida dado el estado actual del objeto". La comprobacion
    # fallaba justo cuando el login funcionaba, y la adopcion se revertia sola
    # en un cliente sano. Se deja seguir la redireccion: vuelve al login y el
    # codigo final es 200.
    $codigo = 0
    try {
      $resp = Invoke-WebRequest -Uri "$url/login" -Method Post -Body $cuerpo -WebSession $sesion `
              -UseBasicParsing -MaximumRedirection 1 -TimeoutSec 20
      $codigo = [int]$resp.StatusCode
    } catch {
      # Leer el codigo de la excepcion tampoco es seguro: puede volver a lanzar.
      try { if ($_.Exception.Response) { $codigo = [int]$_.Exception.Response.StatusCode } } catch { $codigo = 0 }
      if ($codigo -eq 0) {
        $r.Motivo = "no se pudo interpretar la respuesta del login: $($_.Exception.Message)"
        return $r
      }
    }

    # 302 (vuelve al login con el error) o 200 son correctos. 500 no.
    if ($codigo -ge 500) { $r.Motivo = "el login respondio $codigo"; return $r }
    if ($codigo -eq 419) { $r.Motivo = 'el login respondio 419 (token CSRF): revise la sesion y el APP_KEY'; return $r }
    $r.Ok = $true
    return $r
  } catch {
    $r.Motivo = $_.Exception.Message
    return $r
  }
}

# ---------------------------------------------------------------------------
#  1. El motor de base de datos y con que hablarle
# ---------------------------------------------------------------------------

Write-Host ''
Write-Host '  ===============================================' -ForegroundColor Cyan
Write-Host '   Adopcion de una instalacion existente' -ForegroundColor Cyan
Write-Host '  ===============================================' -ForegroundColor Cyan
if ($SoloRevisar) { Write-Host '   Modo revision: no se escribe nada.' -ForegroundColor Yellow }
Write-Host ''

Paso 'Buscando el motor de base de datos'
$motores = @(Buscar-MotoresMysql)
if ($motores.Count -eq 0) {
  Write-Warning 'No respondio ningun MySQL ni MariaDB en este equipo.'
  Write-Host '  Puede que el servicio este detenido (en Laragon y XAMPP se arranca desde su panel),'
  Write-Host '  o que la base este en otro equipo.'
} else {
  foreach ($m in $motores) {
    $de = if ($m.Servicio) { "servicio $($m.Servicio)" } else { $m.Origen }
    Write-Host "  puerto $($m.Puerto)  $($m.Version)  ($de)"
  }
}

$binDelMotor = ''
if ($motores.Count -gt 0) { $binDelMotor = [string]($motores | Where-Object { $_.Bin } | Select-Object -First 1).Bin }
$cliente = Buscar-ClienteMysql -BinDelMotor $binDelMotor
if ($cliente.Mysql) { Write-Host "  cliente mysql: $($cliente.Mysql)" }
if ($cliente.Mysqldump) { Write-Host "  mysqldump    : $($cliente.Mysqldump)" }
if (-not $cliente.Mysqldump) {
  Write-Warning 'No se encontro mysqldump.exe. Sin el no se puede respaldar la base, y sin respaldo no se adopta nada.'
}

# ---------------------------------------------------------------------------
#  2. Las instalaciones del GRE que haya en el equipo
# ---------------------------------------------------------------------------

Paso 'Buscando instalaciones de Guias Electronicas'
$instalaciones = @()
if ($Ruta) {
  if (-not (Test-Path (Join-Path $Ruta 'artisan'))) { throw "En $Ruta no hay una aplicacion Laravel (falta artisan)." }
  $valores = Leer-Env (Join-Path $Ruta '.env')
  $instalaciones = @([pscustomobject]@{
    Ruta = $Ruta; Env = $valores; Base = [string]$valores['DB_DATABASE']
    Servidor = $(if ($valores['DB_HOST']) { [string]$valores['DB_HOST'] } else { '127.0.0.1' })
    Puerto = $(if ($valores['DB_PORT']) { [int]$valores['DB_PORT'] } else { 0 })
    Usuario = [string]$valores['DB_USERNAME']; Clave = [string]$valores['DB_PASSWORD']
    Guias = -1; Nota = ''
    TieneBaseline = (Test-Path (Join-Path $Ruta 'app\Console\Commands\GreBaseline.php'))
  })
} else {
  Write-Host '  Revisando discos y carpetas habituales...'
  $instalaciones = @(Buscar-InstalacionesGre)
  if ($instalaciones.Count -eq 0 -and -not $SinPreguntas) {
    Write-Host '  No aparecio ninguna en los sitios habituales.'
    if ((Read-Host '  Buscar en TODO el disco? Tarda varios minutos (S/N)') -match '^[SsYy]') {
      $instalaciones = @(Buscar-InstalacionesGre -Profundo)
    }
  }
}

# Se anota cuantas guias tiene cada una, para que elegir sea evidente.
foreach ($i in $instalaciones) {
  if (-not $cliente.Mysql -or -not $i.Base) { continue }
  $p = $(if ($i.Puerto -gt 0) { $i.Puerto } elseif ($motores.Count -gt 0) { $motores[0].Puerto } else { 0 })
  if ($p -le 0) { continue }
  try {
    if (Mysql-EsBaseGre -Servidor $i.Servidor -Puerto $p -Usuario $i.Usuario -Clave $i.Clave -Base $i.Base -MysqlExe $cliente.Mysql) {
      $n = Mysql-Escalar -Servidor $i.Servidor -Puerto $p -Usuario $i.Usuario -Clave $i.Clave -Base $i.Base -MysqlExe $cliente.Mysql `
           -Sql 'SELECT (SELECT COUNT(*) FROM guia_ingresos) + (SELECT COUNT(*) FROM guia_salidas);'
      $i.Guias = [int]$n
      $i.Puerto = $p
    } else {
      $i.Nota = 'la base no tiene las tablas de guias'
    }
  } catch {
    $i.Nota = 'no se pudo leer su base con las credenciales del .env'
  }
}

if ($instalaciones.Count -eq 0 -and $SinPreguntas) { throw 'No se encontro ninguna instalacion del GRE. Indique -Ruta.' }

Write-Host ''
if ($instalaciones.Count -gt 0) {
  Write-Host '  Instalaciones encontradas:' -ForegroundColor Cyan
  for ($i = 0; $i -lt $instalaciones.Count; $i++) {
    $x = $instalaciones[$i]
    $detalle = "base $($x.Base)"
    if ($x.Guias -ge 0) { $detalle += ", $($x.Guias) guias" }
    if ($x.Nota) { $detalle += " - $($x.Nota)" }
    Write-Host ("    {0}. {1}" -f ($i + 1), $x.Ruta)
    Write-Host ("       {0}" -f $detalle)
  }
} else {
  Write-Host '  No se encontro ninguna instalacion.' -ForegroundColor Yellow
}

$elegida = $null
if ($instalaciones.Count -eq 1 -and ($SinPreguntas -or $Ruta)) {
  $elegida = $instalaciones[0]
} elseif ($instalaciones.Count -ge 1 -and $SinPreguntas) {
  $elegida = $instalaciones[0]
} else {
  while (-not $elegida) {
    $r = Read-Host '  Numero de la instalacion a adoptar, o escriba la ruta a mano'
    if ($r -match '^\d+$' -and [int]$r -ge 1 -and [int]$r -le $instalaciones.Count) {
      $elegida = $instalaciones[[int]$r - 1]
    } elseif ($r -and (Test-Path (Join-Path $r 'artisan'))) {
      $valores = Leer-Env (Join-Path $r '.env')
      $elegida = [pscustomobject]@{
        Ruta = $r; Env = $valores; Base = [string]$valores['DB_DATABASE']
        Servidor = $(if ($valores['DB_HOST']) { [string]$valores['DB_HOST'] } else { '127.0.0.1' })
        Puerto = $(if ($valores['DB_PORT']) { [int]$valores['DB_PORT'] } else { 0 })
        Usuario = [string]$valores['DB_USERNAME']; Clave = [string]$valores['DB_PASSWORD']
        Guias = -1; Nota = ''
        TieneBaseline = (Test-Path (Join-Path $r 'app\Console\Commands\GreBaseline.php'))
      }
    } elseif ($r) {
      Write-Host "  En '$r' no hay una aplicacion Laravel (falta artisan)." -ForegroundColor Yellow
    }
  }
}

$App = $elegida.Ruta
Write-Host ''
Write-Host "  Se adoptara: $App" -ForegroundColor Green

# ---------------------------------------------------------------------------
#  3. Probar la conexion DE VERDAD
# ---------------------------------------------------------------------------

Paso 'Probando la conexion a la base de datos'

$svr = if ($Servidor) { $Servidor } elseif ($elegida.Servidor) { $elegida.Servidor } else { '127.0.0.1' }
$prt = if ($Puerto -gt 0) { $Puerto } elseif ($elegida.Puerto -gt 0) { $elegida.Puerto } elseif ($motores.Count -gt 0) { $motores[0].Puerto } else { 3306 }
$bd  = if ($Base) { $Base } else { $elegida.Base }
$usr = if ($Usuario) { $Usuario } else { $elegida.Usuario }
$pwd_ = if ($Clave) { $Clave } else { $elegida.Clave }

$intento = 0
$prueba = $null
while ($true) {
  $intento++
  Write-Host "  Probando $usr@$svr`:$prt / $bd ..."
  $prueba = Probar-Mysql -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd -MysqlExe $cliente.Mysql
  if ($prueba.Ok) {
    Write-Host "  Conexion correcta ($($prueba.Version), via $($prueba.Via))" -ForegroundColor Green
    break
  }

  Write-Warning "No se pudo conectar: $($prueba.Motivo)"
  if ($prueba.Detalle) { Write-Host "    $($prueba.Detalle)" -ForegroundColor DarkGray }
  if ($SinPreguntas) { throw "No se pudo conectar a la base: $($prueba.Motivo)" }
  if ($intento -ge 5) { throw 'Demasiados intentos de conexion a la base.' }

  Write-Host '  Los datos del .env no sirvieron. Escribalos a mano.' -ForegroundColor Yellow
  if ($motores.Count -gt 1) {
    Write-Host '  Motores que respondieron:'
    foreach ($m in $motores) { Write-Host "    puerto $($m.Puerto)  $($m.Version)" }
  }
  $svr = Preguntar -Texto 'Servidor' -Valor '' -PorDefecto $svr
  $prt = [int](Preguntar -Texto 'Puerto' -Valor '' -PorDefecto "$prt" -Validar { param($v) ($v -as [int]) -gt 0 } -Ayuda 'Un numero de puerto.')
  $usr = Preguntar -Texto 'Usuario' -Valor '' -PorDefecto $usr
  $pwd_ = Preguntar -Texto 'Clave' -Valor '' -Oculto
  $bases = @(Mysql-Bases -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -MysqlExe $cliente.Mysql)
  if ($bases.Count -gt 0) {
    Write-Host '  Bases en ese servidor:'
    for ($i = 0; $i -lt $bases.Count; $i++) { Write-Host ("    {0}. {1}" -f ($i + 1), $bases[$i]) }
    $e = Read-Host "  Numero o nombre de la base [$bd]"
    if ($e -match '^\d+$' -and [int]$e -ge 1 -and [int]$e -le $bases.Count) { $bd = $bases[[int]$e - 1] }
    elseif ($e) { $bd = $e }
  } else {
    $bd = Preguntar -Texto 'Base de datos' -Valor '' -PorDefecto $bd
  }
}

if (-not $cliente.Mysql) {
  throw 'La conexion funciona, pero no hay ningun mysql.exe en este equipo con el que consultar y respaldar la base. Instale las herramientas de linea de comandos de MySQL o MariaDB y vuelva a ejecutar.'
}
if (-not (Mysql-EsBaseGre -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd -MysqlExe $cliente.Mysql)) {
  throw "La base '$bd' no tiene las tablas guia_ingresos y guia_salidas: no es una base de Guias Electronicas. No se toca nada."
}

$guiasIngresoAntes = [int](Mysql-Escalar -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd -MysqlExe $cliente.Mysql -Sql 'SELECT COUNT(*) FROM guia_ingresos;')
$guiasSalidaAntes  = [int](Mysql-Escalar -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd -MysqlExe $cliente.Mysql -Sql 'SELECT COUNT(*) FROM guia_salidas;')
Write-Host "  Guias de ingreso: $guiasIngresoAntes    Guias de salida: $guiasSalidaAntes"

# ---------------------------------------------------------------------------
#  4. Puertos y PHP
# ---------------------------------------------------------------------------

Paso 'Revisando puertos y PHP'
Write-Host (Describir-Puerto $PuertoWeb)
Write-Host (Describir-Puerto $prt)

$phps = @(Buscar-Php $App)
if ($phps.Count -eq 0) {
  throw "No se encontro un PHP 7.4 o superior con pdo_mysql para correr artisan. Indiquelo con -Php C:\ruta\php.exe"
}
$phpExe = $phps[0].Exe
Write-Host "  PHP: $phpExe"
if (-not $phps[0].Ideal) {
  Write-Warning 'Ese PHP esta fuera del rango 7.4-8.2 que soporta la aplicacion. Puede llenar la salida de avisos.'
}

$urlApp = $Url
if (-not $urlApp) { $urlApp = [string]$elegida.Env['APP_URL'] }
if (-not $urlApp) { $urlApp = "http://127.0.0.1:$PuertoWeb" }
$urlApp = $urlApp.TrimEnd('/')

# Lectura ANTES de tocar nada: si la web ya estaba caida, que no responda
# despues no es culpa de la adopcion y no debe disparar una reversion.
$webAntes = Probar-Web $urlApp
if ($webAntes.Ok) { Write-Host "  La web responde en $urlApp" }
else { Write-Warning "La web no responde en $urlApp ($($webAntes.Detalle)). Se adoptara igual, pero no se podra verificar por ahi." }

# ---------------------------------------------------------------------------
#  Resumen y confirmacion
# ---------------------------------------------------------------------------

Write-Host ''
Write-Host '  Resumen' -ForegroundColor Cyan
Write-Host "    Aplicacion  : $App"
Write-Host "    Base        : $bd en $svr`:$prt (usuario $usr)"
Write-Host "    Guias       : $guiasIngresoAntes de ingreso y $guiasSalidaAntes de salida"
Write-Host "    PHP         : $phpExe"
Write-Host "    Web         : $urlApp"

$raizRespaldo = $Respaldos
if (-not $raizRespaldo) {
  $unidad = [IO.Path]::GetPathRoot($App)
  $raizRespaldo = Join-Path $unidad 'DBPeru-respaldos'
}
$fecha = Get-Date -Format yyyyMMdd-HHmmss
$respaldo = Join-Path $raizRespaldo $fecha
Write-Host "    Respaldo en : $respaldo"
Write-Host ''

if ($SoloRevisar) {
  Write-Host '  Modo revision: hasta aqui llega. No se escribio nada.' -ForegroundColor Yellow
  exit 0
}
if (-not $SinPreguntas) {
  Write-Host '  Antes de continuar, asegurese de que nadie este usando el sistema.' -ForegroundColor Yellow
  if ((Read-Host '  Continuar? (S/N)') -notmatch '^[SsYy]') { Write-Host '  Cancelado, no se toco nada.'; exit 1 }
}

New-Item -ItemType Directory -Force $respaldo | Out-Null
$log = Join-Path $respaldo 'adopcion.log'
Start-Transcript -Path $log | Out-Null
Write-Host "Log de esta adopcion: $log"
Write-Host "Respaldo: $respaldo"

$revertible = $false
$sqlRespaldo = Join-Path $respaldo 'base.sql'
$appRespaldo = Join-Path $respaldo 'app'

try {
  # -------------------------------------------------------------------- 5
  Paso 'Respaldo de la base y de la aplicacion'
  Mysql-Respaldar -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd `
                  -MysqldumpExe $cliente.Mysqldump -Archivo $sqlRespaldo | Out-Null

  $ErrorActionPreference = 'Continue'
  robocopy $App $appRespaldo /E /NFL /NDL /NJH /NJS /NP /R:2 /W:2 `
           /XD (Join-Path $App 'public\storage') (Join-Path $App 'node_modules') (Join-Path $App '.git') | Out-Null
  $codigo = $LASTEXITCODE
  $ErrorActionPreference = 'Stop'
  if ($codigo -ge 8) { throw "No se pudo respaldar la aplicacion (robocopy $codigo)" }
  Write-Host "  respaldo de la aplicacion: $appRespaldo"
  $revertible = $true

  # -------------------------------------------------------------------- 6
  Paso 'Codigo nuevo, conservando el .env del cliente'
  if (Test-Path (Join-Path $Paquete 'app\artisan')) {
    Copiar-App (Join-Path $Paquete 'app') $App
    Write-Host '  codigo actualizado (no se toco .env, storage ni public\storage)'
  } elseif ($elegida.TieneBaseline) {
    Write-Warning 'No hay app\ en el paquete: se continua con el codigo que ya tiene el cliente.'
  } else {
    throw 'El paquete no trae app\ y el codigo del cliente no tiene gre:baseline. Ejecute adoptar.ps1 desde la carpeta del paquete.'
  }

  $envCliente = Join-Path $App '.env'
  $valoresEnv = Leer-Env $envCliente
  if (-not $valoresEnv['APP_KEY']) { throw 'El .env del cliente se quedo sin APP_KEY. Se aborta.' }
  Agregar-Claves-Que-Falten $envCliente $valoresEnv | Out-Null

  # -------------------------------------------------------------------- 7
  Paso 'Poniendo la base al dia'
  Push-Location $App
  try {
    Artisan $phpExe 'config:clear' | Out-Null
    Artisan $phpExe 'cache:clear'  | Out-Null

    Write-Host ''
    Write-Host '  Esto es lo que gre:baseline marcaria como ya aplicado:' -ForegroundColor Cyan
    # Ejecutar (comun.ps1) ya imprime la salida del comando: si aqui se
    # volviera a escribir, saldria todo duplicado en pantalla y en el log.
    Artisan $phpExe 'gre:baseline --dry-run' | Out-Null
    Write-Host ''

    Artisan $phpExe 'gre:baseline'    | Out-Null
    Artisan $phpExe 'migrate --force' | Out-Null
    Artisan $phpExe 'gre:inicializar' | Out-Null

    # Se limpia, pero NO se cachea la configuracion: si el codigo del cliente
    # llama a env() fuera de config/, config:cache apagaria esos valores.
    Artisan $phpExe 'view:clear' | Out-Null
  } finally { Pop-Location }

  # -------------------------------------------------------------------- 8
  Paso 'Verificacion'
  $guiasIngresoDespues = [int](Mysql-Escalar -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd -MysqlExe $cliente.Mysql -Sql 'SELECT COUNT(*) FROM guia_ingresos;')
  $guiasSalidaDespues  = [int](Mysql-Escalar -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd -MysqlExe $cliente.Mysql -Sql 'SELECT COUNT(*) FROM guia_salidas;')

  Write-Host "  Guias de ingreso: $guiasIngresoAntes antes, $guiasIngresoDespues ahora"
  Write-Host "  Guias de salida : $guiasSalidaAntes antes, $guiasSalidaDespues ahora"
  if ($guiasIngresoDespues -ne $guiasIngresoAntes -or $guiasSalidaDespues -ne $guiasSalidaAntes) {
    throw "El numero de guias cambio (ingreso $guiasIngresoAntes->$guiasIngresoDespues, salida $guiasSalidaAntes->$guiasSalidaDespues)"
  }
  Write-Host '  No falta ni sobra ninguna guia' -ForegroundColor Green

  if ($webAntes.Ok) {
    $webDespues = Probar-Web $urlApp
    if (-not $webDespues.Ok) {
      throw "La web respondia antes de adoptar y ahora no ($($webDespues.Detalle))"
    }
    Write-Host '  La web responde' -ForegroundColor Green

    $login = Probar-Login $urlApp
    if (-not $login.Ok) { throw "El login no funciona: $($login.Motivo)" }
    Write-Host '  El login funciona' -ForegroundColor Green
  } else {
    Write-Warning 'La web no respondia antes de empezar, asi que no se verifico por ahi.'
    Write-Host "  Arranque el servidor web del cliente y abra $urlApp/login para comprobarlo."
    Write-Host (Describir-Puerto $PuertoWeb)
  }

  Write-Host ''
  Write-Host "ADOPCION_OK   Respaldo en $respaldo" -ForegroundColor Green
  Write-Host "Log: $log"
  Write-Host 'A partir de ahora este cliente se actualiza con ACTUALIZAR.cmd como cualquier otro.'
}
catch {
  Write-Host ''
  Write-Host "ADOPCION_FALLIDA: $($_.Exception.Message)" -ForegroundColor Red

  if ($revertible) {
    Write-Host 'Revirtiendo al estado anterior...' -ForegroundColor Yellow
    try {
      $ErrorActionPreference = 'Continue'
      robocopy $appRespaldo $App /MIR /NFL /NDL /NJH /NJS /NP `
               /XD (Join-Path $App 'public\storage') (Join-Path $App 'storage') (Join-Path $App 'node_modules') | Out-Null
      $ErrorActionPreference = 'Stop'
      Mysql-Restaurar -Servidor $svr -Puerto $prt -Usuario $usr -Clave $pwd_ -Base $bd `
                      -MysqlExe $cliente.Mysql -Archivo $sqlRespaldo
      Write-Host 'REVERTIDO: el sistema quedo como estaba antes de adoptar.' -ForegroundColor Yellow
    } catch {
      Write-Host "NO SE PUDO REVERTIR: $($_.Exception.Message)" -ForegroundColor Red
      Write-Host "El respaldo sigue intacto en $respaldo" -ForegroundColor Red
      Write-Host "  base        : $sqlRespaldo"
      Write-Host "  aplicacion  : $appRespaldo"
    }
  } else {
    Write-Host 'No se alcanzo a tocar nada: el sistema esta como estaba.' -ForegroundColor Yellow
  }

  Write-Host "Log: $log"
  Stop-Transcript | Out-Null
  exit 1
}
Stop-Transcript | Out-Null
