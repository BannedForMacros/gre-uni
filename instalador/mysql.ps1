# Hablar con el MySQL / MariaDB que YA tiene el cliente.
#
# Solo ASCII a proposito, igual que comun.ps1: PowerShell 5.1 lee los .ps1 sin
# BOM como ANSI y las tildes se corrompen.
#
# POR QUE EXISTE ESTE ARCHIVO
#   instalar.ps1 trabaja siempre contra el MySQL que el propio paquete instala:
#   sabe donde esta mysql.exe, sabe que el puerto es el 3306 y sabe la clave de
#   root porque la genero el. En un cliente que YA tiene el sistema montado no
#   se sabe nada de eso. El motor puede ser MySQL o MariaDB, puede venir de
#   Laragon, de XAMPP, de WampServer o de un servicio suelto, puede escuchar en
#   cualquier puerto, y puede que no haya ningun cliente de linea de comandos
#   instalado.
#
#   Por eso aqui hay DOS caminos para hablar con la base:
#
#     1. mysql.exe del propio cliente, cuando aparece alguno. Es el camino
#        normal y el unico que sirve para volcar y restaurar.
#
#     2. El protocolo de MySQL hablado directo por TCP, sin cliente y sin
#        librerias. .NET trae proveedor de SQL Server -por eso Probar-SqlServer
#        no necesita nada- pero NO trae proveedor de MySQL. La unica forma de no
#        depender de algo que el cliente quiza no tenga es implementar el saludo
#        del protocolo, que es corto y esta estable desde 2010.
#
#   Los dos caminos devuelven lo mismo y distinguen los mismos tres casos que
#   Probar-SqlServer distingue para el ERP:
#       - no se llega al servidor
#       - el usuario o la clave no son correctos
#       - la base de datos no existe
#
# DOS DETALLES QUE NO SE VEN Y QUE ROMPEN EL RESPALDO SI SE HACEN MAL
#   - La clave NUNCA viaja en la linea de comandos (-p<clave>): va en un
#     archivo temporal que se borra siempre (--defaults-extra-file), igual que
#     hace Con-CnfMysql en comun.ps1. Con -p la clave del cliente queda a la
#     vista de cualquiera con tasklist, y ademas mysql escribe un aviso por el
#     canal de errores que, con ErrorActionPreference = Stop, PowerShell
#     convierte en excepcion y aborta la adopcion sin motivo real.
#   - No se redirige NADA con el operador ">" de PowerShell. PowerShell 5.1
#     escribe UTF-16 al redirigir, y un volcado SQL en UTF-16 no se puede
#     reimportar: el respaldo parece correcto y no sirve, que es lo peor que
#     puede pasar. Por eso el volcado lo escribe mysqldump con --result-file y
#     la restauracion entra por "source", sin redireccion de ningun tipo.
#
# NO reemplaza a comun.ps1. comun.ps1 tiene Mysql-Sql y Mysql-Volcar, que
# hablan con el MySQL instalado por el paquete en una ruta conocida. Las de
# aqui se llaman distinto a proposito (Mysql-Consulta, Mysql-Respaldar,
# Mysql-Restaurar) para que dot-sourcear los dos archivos no pise nada.

# Puertos donde suele escuchar un motor cuando no se declara en el servicio.
# 3306 es el normal; 3307 y 3308 aparecen cuando conviven dos motores, que es
# justo lo que pasa en los equipos con Laragon y XAMPP a la vez.
$MYSQL_PUERTOS_HABITUALES = @(3306, 3307, 3308, 3309, 3310, 3316, 33066)

# Carpetas donde los instaladores mas comunes dejan el cliente. No se codifica
# ninguna como caso especial: es solo una lista de sitios donde mirar.
$MYSQL_RAICES_HABITUALES = @(
  'laragon\bin\mysql', 'laragon\bin\mariadb',
  'xampp\mysql', 'wamp64\bin\mysql', 'wamp64\bin\mariadb', 'wamp\bin\mysql',
  'Program Files\MySQL', 'Program Files\MariaDB', 'Program Files (x86)\MySQL',
  'MySQL', 'MariaDB', 'mysql', 'DBPeru\GRE\mysql', 'Bitnami', 'UwAmp\bin\mysql',
  'EasyPHP', 'usbwebserver', 'AppServ\MySQL', 'wnmp\mysql', 'nginx\mysql'
)

# ---------------------------------------------------------------------------
# Utilidades internas
# ---------------------------------------------------------------------------

function Mysql-EscribirTextoPlano([string]$ruta, [string]$texto) {
  # Sin BOM: mysql.exe no entiende un my.cnf que empiece por BOM y responde
  # "unknown variable", que no dice nada sobre la causa real.
  [IO.File]::WriteAllText($ruta, ($texto -replace "`r?`n", "`r`n"), (New-Object Text.UTF8Encoding $false))
}

function Mysql-EjecutarProceso {
  # Como Ejecutar de comun.ps1, pero devuelve el codigo y la salida de error por
  # separado en vez de lanzar. Aqui el texto del error ES el resultado: de el se
  # deduce si fallo el servidor, la clave o la base.
  param([string]$Exe, [string]$Argumentos, [int]$Segundos = 60)

  $psi = New-Object Diagnostics.ProcessStartInfo $Exe, $Argumentos
  $psi.UseShellExecute = $false
  $psi.RedirectStandardOutput = $true
  $psi.RedirectStandardError = $true
  $psi.CreateNoWindow = $true
  $p = [Diagnostics.Process]::Start($psi)
  $salida = $p.StandardOutput.ReadToEndAsync()
  $error_ = $p.StandardError.ReadToEndAsync()
  if (-not $p.WaitForExit($Segundos * 1000)) {
    try { $p.Kill() } catch { }
    return [pscustomobject]@{ Codigo = -1; Salida = ''; Error = "el comando no termino en $Segundos segundos" }
  }
  [pscustomobject]@{
    Codigo = $p.ExitCode
    Salida = $salida.Result.TrimEnd()
    Error  = $error_.Result.TrimEnd()
  }
}

function Mysql-ConCnf {
  # Las credenciales NUNCA van en la linea de comandos: cualquier usuario del
  # equipo veria la clave con tasklist mientras corre. Van en un archivo
  # temporal que se borra siempre.
  param(
    [string]$Servidor, [int]$Puerto, [string]$Usuario, [string]$Clave, [scriptblock]$Bloque
  )
  $cnf = Join-Path $env:TEMP ('gre-adopcion-' + [guid]::NewGuid() + '.cnf')
  $texto = "[client]`nuser=$Usuario`nhost=$Servidor`nport=$Puerto"
  if ($Clave) { $texto += "`npassword=`"$($Clave -replace '"', '\"')`"" }
  Mysql-EscribirTextoPlano $cnf $texto
  try { & $Bloque $cnf } finally { Remove-Item $cnf -Force -ErrorAction SilentlyContinue }
}

function Mysql-Motivo([string]$texto) {
  # Traduce el error -del cliente o del protocolo- a los mismos tres casos que
  # Probar-SqlServer. Se miran codigos numericos primero porque no dependen del
  # idioma en que este el motor.
  if ($texto -match '\b1045\b|Access denied for user|Acceso denegado') {
    return 'el usuario o la clave no son correctos'
  }
  if ($texto -match '\b1049\b|Unknown database|Base de datos desconocida') {
    return 'la base de datos no existe'
  }
  if ($texto -match '\b1044\b') {
    return 'la base existe pero ese usuario no tiene permiso para abrirla'
  }
  if ($texto -match '\b1130\b|not allowed to connect') {
    return 'el servidor no acepta conexiones de este equipo para ese usuario'
  }
  if ($texto -match "\b2002\b|\b2003\b|\b2005\b|Can't connect|Unknown MySQL server host|no se llega|timed out|tiempo de espera") {
    return 'no se llega al servidor: revise el puerto, que el servicio este arrancado y el firewall'
  }
  return 'no se pudo conectar'
}

# ---------------------------------------------------------------------------
# Protocolo de MySQL por TCP, para cuando no hay ningun cliente instalado
# ---------------------------------------------------------------------------

function Mysql-LeerExacto($flujo, [int]$n) {
  $b = New-Object byte[] $n
  $leido = 0
  while ($leido -lt $n) {
    $r = $flujo.Read($b, $leido, $n - $leido)
    if ($r -le 0) { throw 'la conexion se corto antes de tiempo' }
    $leido += $r
  }
  , $b
}

function Mysql-LeerPaquete($flujo) {
  $cab = Mysql-LeerExacto $flujo 4
  $largo = $cab[0] + ($cab[1] -shl 8) + ($cab[2] -shl 16)
  $datos = if ($largo -gt 0) { Mysql-LeerExacto $flujo $largo } else { New-Object byte[] 0 }
  [pscustomobject]@{ Seq = [int]$cab[3]; Datos = $datos }
}

function Mysql-EnviarPaquete($flujo, [byte[]]$datos, [int]$seq) {
  $n = $datos.Length
  $cab = [byte[]]@(($n -band 0xFF), (($n -shr 8) -band 0xFF), (($n -shr 16) -band 0xFF), ($seq -band 0xFF))
  $flujo.Write($cab, 0, 4)
  if ($n -gt 0) { $flujo.Write($datos, 0, $n) }
  $flujo.Flush()
}

function Mysql-LeerError([byte[]]$d) {
  # 0xFF, codigo(2), '#', sqlstate(5), mensaje
  if ($d.Length -lt 3) { return [pscustomobject]@{ Codigo = 0; Mensaje = 'error sin detalle' } }
  $codigo = $d[1] + ($d[2] -shl 8)
  $ini = if ($d.Length -gt 3 -and $d[3] -eq 0x23) { 9 } else { 3 }
  if ($ini -gt $d.Length) { $ini = $d.Length }
  [pscustomobject]@{
    Codigo  = $codigo
    Mensaje = [Text.Encoding]::UTF8.GetString($d, $ini, $d.Length - $ini)
  }
}

function Mysql-Xor([byte[]]$a, [byte[]]$b) {
  $r = New-Object byte[] $a.Length
  for ($i = 0; $i -lt $a.Length; $i++) { $r[$i] = $a[$i] -bxor $b[$i % $b.Length] }
  , $r
}

function Mysql-TokenNativo([string]$clave, [byte[]]$sal) {
  # mysql_native_password: SHA1(clave) XOR SHA1(sal + SHA1(SHA1(clave)))
  if (-not $clave) { return (New-Object byte[] 0) }
  $sha = [Security.Cryptography.SHA1]::Create()
  $p1 = $sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($clave))
  $p2 = $sha.ComputeHash($p1)
  $p3 = $sha.ComputeHash([byte[]]($sal + $p2))
  Mysql-Xor $p1 $p3
}

function Mysql-TokenSha2([string]$clave, [byte[]]$sal) {
  # caching_sha2_password, camino rapido:
  #   SHA256(clave) XOR SHA256(SHA256(SHA256(clave)) + sal)
  if (-not $clave) { return (New-Object byte[] 0) }
  $sha = [Security.Cryptography.SHA256]::Create()
  $p1 = $sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($clave))
  $p2 = $sha.ComputeHash($p1)
  $p3 = $sha.ComputeHash([byte[]]($p2 + $sal))
  Mysql-Xor $p1 $p3
}

function Mysql-ParsearSaludo([byte[]]$d) {
  # Paquete de bienvenida del servidor. De aqui salen la version, la sal y el
  # metodo de autenticacion que pide.
  if ($d.Length -lt 2) { return $null }
  if ($d[0] -eq 0xFF) {
    $e = Mysql-LeerError $d
    return [pscustomobject]@{ Ok = $false; Version = ''; Plugin = ''; Sal = $null; Detalle = $e.Mensaje; Codigo = $e.Codigo }
  }
  if ($d[0] -ne 10) { return $null }   # solo protocolo 10; el 9 murio con MySQL 3

  $i = 1
  $fin = [Array]::IndexOf($d, [byte]0, $i)
  if ($fin -lt 0) { return $null }
  $version = [Text.Encoding]::ASCII.GetString($d, $i, $fin - $i)
  $i = $fin + 1
  $i += 4                                     # id de conexion
  $sal = [byte[]]($d[$i..($i + 7)]); $i += 8  # primera mitad de la sal
  $i += 1                                     # relleno
  $i += 2                                     # capacidades (mitad baja)
  $plugin = 'mysql_native_password'

  if ($d.Length -gt ($i + 15)) {
    $i += 1                                   # juego de caracteres
    $i += 2                                   # estado
    $i += 2                                   # capacidades (mitad alta)
    $largoAuth = [int]$d[$i]; $i += 1
    $i += 10                                  # reservado
    $n = [Math]::Max(13, $largoAuth - 8)
    if (($i + $n - 2) -lt $d.Length) {
      # La segunda mitad trae un 0 final que no forma parte de la sal.
      $sal = [byte[]]($sal + [byte[]]($d[$i..($i + $n - 2)]))
    }
    $i += $n
    if ($i -lt $d.Length) {
      $fin = [Array]::IndexOf($d, [byte]0, $i)
      if ($fin -lt 0) { $fin = $d.Length }
      if ($fin -gt $i) { $plugin = [Text.Encoding]::ASCII.GetString($d, $i, $fin - $i) }
    }
  }

  [pscustomobject]@{ Ok = $true; Version = $version; Plugin = $plugin; Sal = $sal; Detalle = ''; Codigo = 0 }
}

function Mysql-Conectar {
  # Abre el socket y lee el saludo. Sirve para dos cosas: descubrir si en un
  # puerto hay de verdad un motor MySQL -y no otra cosa escuchando- y para
  # empezar la autenticacion.
  param([string]$Servidor, [int]$Puerto, [int]$Milisegundos = 2500)

  $cliente = New-Object Net.Sockets.TcpClient
  try {
    $iar = $cliente.BeginConnect($Servidor, $Puerto, $null, $null)
    if (-not ($iar.AsyncWaitHandle.WaitOne($Milisegundos) -and $cliente.Connected)) {
      $cliente.Close()
      return [pscustomobject]@{ Ok = $false; Motivo = 'no se llega al servidor: revise el puerto, que el servicio este arrancado y el firewall'; Detalle = "sin respuesta en $Servidor`:$Puerto" }
    }
    $cliente.EndConnect($iar)
    $flujo = $cliente.GetStream()
    $flujo.ReadTimeout = $Milisegundos + 2500
    $flujo.WriteTimeout = $Milisegundos + 2500

    $paquete = Mysql-LeerPaquete $flujo
    $saludo = Mysql-ParsearSaludo $paquete.Datos
    if (-not $saludo) {
      $cliente.Close()
      return [pscustomobject]@{ Ok = $false; Motivo = 'en ese puerto responde algo que no es MySQL'; Detalle = '' }
    }
    if (-not $saludo.Ok) {
      $cliente.Close()
      return [pscustomobject]@{ Ok = $false; Motivo = (Mysql-Motivo $saludo.Detalle); Detalle = $saludo.Detalle }
    }

    return [pscustomobject]@{
      Ok = $true; Motivo = ''; Detalle = ''
      Version = $saludo.Version; Plugin = $saludo.Plugin; Sal = $saludo.Sal
      Cliente = $cliente; Flujo = $flujo; Seq = $paquete.Seq
    }
  } catch {
    try { $cliente.Close() } catch { }
    return [pscustomobject]@{ Ok = $false; Motivo = (Mysql-Motivo $_.Exception.Message); Detalle = $_.Exception.Message }
  }
}

function Mysql-RespuestaSaludo {
  param([string]$Usuario, [byte[]]$Token, [string]$Plugin, [string]$Base)

  $cap = 0x00000001 -bor 0x00000004 -bor 0x00000200 -bor 0x00002000 -bor 0x00008000 -bor 0x00080000
  if ($Base) { $cap = $cap -bor 0x00000008 }

  $b = New-Object Collections.Generic.List[byte]
  foreach ($x in @(($cap -band 0xFF), (($cap -shr 8) -band 0xFF), (($cap -shr 16) -band 0xFF), (($cap -shr 24) -band 0xFF))) { $b.Add([byte]$x) }
  foreach ($x in @(0, 0, 0, 1)) { $b.Add([byte]$x) }     # paquete maximo: 16 MB
  $b.Add([byte]33)                                        # utf8
  for ($i = 0; $i -lt 23; $i++) { $b.Add([byte]0) }       # reservado
  $b.AddRange([Text.Encoding]::UTF8.GetBytes($Usuario)); $b.Add([byte]0)
  $b.Add([byte]$Token.Length)
  if ($Token.Length -gt 0) { $b.AddRange($Token) }
  if ($Base) { $b.AddRange([Text.Encoding]::UTF8.GetBytes($Base)); $b.Add([byte]0) }
  $b.AddRange([Text.Encoding]::ASCII.GetBytes($Plugin)); $b.Add([byte]0)
  , $b.ToArray()
}

function Mysql-Token([string]$plugin, [string]$clave, [byte[]]$sal) {
  if ($plugin -eq 'caching_sha2_password') { return (Mysql-TokenSha2 $clave $sal) }
  return (Mysql-TokenNativo $clave $sal)
}

function Mysql-ProbarPorProtocolo {
  # Conexion de verdad sin cliente instalado: se autentica y, si se indico una
  # base, se abre. Devuelve los mismos campos que Probar-Mysql.
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$Base = '', [int]$Milisegundos = 4000
  )
  $r = [pscustomobject]@{ Ok = $false; Motivo = ''; Detalle = ''; Version = ''; Bases = @(); Via = 'protocolo' }

  $con = Mysql-Conectar -Servidor $Servidor -Puerto $Puerto -Milisegundos $Milisegundos
  if (-not $con.Ok) { $r.Motivo = $con.Motivo; $r.Detalle = $con.Detalle; return $r }
  $r.Version = $con.Version

  try {
    $plugin = $con.Plugin
    $token = Mysql-Token $plugin $Clave $con.Sal
    $cuerpo = Mysql-RespuestaSaludo -Usuario $Usuario -Token $token -Plugin $plugin -Base $Base
    Mysql-EnviarPaquete $con.Flujo $cuerpo ($con.Seq + 1)

    for ($vuelta = 0; $vuelta -lt 4; $vuelta++) {
      $p = Mysql-LeerPaquete $con.Flujo
      $d = $p.Datos
      if ($d.Length -eq 0) { break }

      switch ($d[0]) {
        0x00 { $r.Ok = $true; return $r }                 # OK: usuario, clave y base correctos
        0xFF {
          $e = Mysql-LeerError $d
          $r.Motivo = Mysql-Motivo ("$($e.Codigo) $($e.Mensaje)")
          $r.Detalle = "$($e.Codigo): $($e.Mensaje)"
          return $r
        }
        0xFE {
          # El servidor pide cambiar de metodo: viene el nombre y una sal nueva.
          $i = 1
          $fin = [Array]::IndexOf($d, [byte]0, $i)
          if ($fin -lt 0) { $r.Motivo = 'no se pudo conectar'; return $r }
          $plugin = [Text.Encoding]::ASCII.GetString($d, $i, $fin - $i)
          $i = $fin + 1
          $largo = [Math]::Min(20, [Math]::Max(0, $d.Length - $i))
          $salNueva = if ($largo -gt 0) { [byte[]]($d[$i..($i + $largo - 1)]) } else { $con.Sal }
          $token = Mysql-Token $plugin $Clave $salNueva
          Mysql-EnviarPaquete $con.Flujo $token ($p.Seq + 1)
        }
        0x01 {
          # caching_sha2_password. 0x03 = la clave estaba en cache y es correcta;
          # 0x04 = el servidor exige la ronda completa, que va cifrada con RSA o
          # por TLS y no se puede hacer sin un cliente.
          if ($d.Length -ge 2 -and $d[1] -eq 3) { continue }
          if ($d.Length -ge 2 -and $d[1] -eq 4) {
            $r.Motivo = 'este servidor exige autenticacion cifrada; hace falta un mysql.exe para comprobar la clave'
            $r.Detalle = 'caching_sha2_password pidio la ronda completa'
            return $r
          }
          continue
        }
        default { $r.Motivo = 'no se pudo conectar'; $r.Detalle = "respuesta inesperada 0x{0:X2}" -f $d[0]; return $r }
      }
    }
    $r.Motivo = 'no se pudo conectar'
    $r.Detalle = 'el servidor no termino la autenticacion'
  } catch {
    $r.Motivo = Mysql-Motivo $_.Exception.Message
    $r.Detalle = $_.Exception.Message
  } finally {
    try { $con.Cliente.Close() } catch { }
  }
  return $r
}

# ---------------------------------------------------------------------------
# Descubrimiento
# ---------------------------------------------------------------------------

function Mysql-PuertoDeIni([string]$ini) {
  # El puerto real casi nunca esta en el servicio: esta en el my.ini que el
  # servicio recibe con --defaults-file. Se lee el de la seccion [mysqld].
  if (-not $ini -or -not (Test-Path $ini)) { return 0 }
  $seccion = ''
  foreach ($linea in (Get-Content $ini -ErrorAction SilentlyContinue)) {
    $l = $linea.Trim()
    if ($l -match '^\[(.+)\]$') { $seccion = $matches[1].ToLower(); continue }
    if ($seccion -in @('mysqld', 'mariadb', 'mysqld_safe') -and $l -match '^port\s*=\s*(\d+)') {
      return [int]$matches[1]
    }
  }
  return 0
}

function Buscar-MotoresMysql {
  <#
    Todos los motores MySQL o MariaDB que hay en este equipo, vengan de donde
    vengan. Se buscan por tres caminos, porque ninguno solo es suficiente:

      1. Servicios de Windows cuyo binario sea mysqld o mariadbd. Cubre las
         instalaciones "de verdad" y las de Laragon/XAMPP registradas como
         servicio, y da la carpeta bin, que es donde estan mysql.exe y
         mysqldump.exe de la MISMA version que el motor.
      2. Procesos mysqld/mariadbd que esten corriendo sin ser servicio, que es
         como los arrancan Laragon y XAMPP desde su panel.
      3. Puertos habituales, saludando por protocolo. Si algo contesta el
         saludo de MySQL, hay un motor ahi aunque no se sepa de donde salio.

    No se asume el 3306 en ningun momento.
  #>
  $motores = @()
  $vistos = @{}

  function Agregar([string]$exe, [int]$puerto, [string]$servicio, [string]$origen) {
    if ($puerto -le 0) { return }
    $clave = "$puerto"
    if ($vistos.ContainsKey($clave)) {
      # Ya se conocia el puerto; si ahora se sabe de donde sale el binario, se
      # completa, porque de ahi salen mysql.exe y mysqldump.exe.
      if ($exe -and -not $vistos[$clave].Binario) {
        $vistos[$clave].Binario = $exe
        $vistos[$clave].Bin = (Split-Path $exe -Parent)
        $vistos[$clave].Servicio = $servicio
      }
      return
    }
    $m = [pscustomobject]@{
      Puerto = $puerto; Servicio = $servicio; Binario = $exe
      Bin = $(if ($exe) { Split-Path $exe -Parent } else { '' })
      Origen = $origen; Version = ''; Responde = $false
    }
    $vistos[$clave] = $m
    $script:motoresHallados += $m
  }

  $script:motoresHallados = @()

  # --- 1. servicios -------------------------------------------------------
  $servicios = @()
  try { $servicios = @(Get-CimInstance Win32_Service -ErrorAction Stop) }
  catch { $servicios = @(Get-WmiObject Win32_Service -ErrorAction SilentlyContinue) }

  foreach ($s in $servicios) {
    $ruta = [string]$s.PathName
    if (-not $ruta) { continue }
    if ($ruta -notmatch '(?i)(mysqld|mariadbd|mysqld-nt)\.exe') { continue }

    $exe = ''
    if ($ruta -match '(?i)"([^"]+\\(?:mysqld|mariadbd|mysqld-nt)\.exe)"') { $exe = $matches[1] }
    elseif ($ruta -match '(?i)([A-Za-z]:\\[^"]*?\\(?:mysqld|mariadbd|mysqld-nt)\.exe)') { $exe = $matches[1] }

    $ini = ''
    if ($ruta -match '(?i)--defaults-file=(?:"([^"]+)"|([^\s]+))') {
      $ini = if ($matches[1]) { $matches[1] } else { $matches[2] }
    }
    $puerto = 0
    if ($ruta -match '(?i)--port[= ](\d+)') { $puerto = [int]$matches[1] }
    if ($puerto -le 0) { $puerto = Mysql-PuertoDeIni $ini }
    if ($puerto -le 0 -and $exe) { $puerto = Mysql-PuertoDeIni (Join-Path (Split-Path $exe -Parent) '..\my.ini') }
    if ($puerto -le 0) { $puerto = 3306 }

    Agregar $exe $puerto ([string]$s.Name) "servicio $($s.Name)"
  }

  # --- 2. procesos sueltos ------------------------------------------------
  try {
    $procesos = @(Get-CimInstance Win32_Process -Filter "Name='mysqld.exe' OR Name='mariadbd.exe'" -ErrorAction SilentlyContinue)
  } catch {
    $procesos = @(Get-WmiObject Win32_Process -Filter "Name='mysqld.exe' OR Name='mariadbd.exe'" -ErrorAction SilentlyContinue)
  }
  foreach ($p in $procesos) {
    $linea = [string]$p.CommandLine
    $exe = [string]$p.ExecutablePath
    $puerto = 0
    if ($linea -match '(?i)--port[= ](\d+)') { $puerto = [int]$matches[1] }
    if ($puerto -le 0 -and $linea -match '(?i)--defaults-file=(?:"([^"]+)"|([^\s]+))') {
      $puerto = Mysql-PuertoDeIni $(if ($matches[1]) { $matches[1] } else { $matches[2] })
    }
    if ($puerto -le 0) { $puerto = 3306 }
    Agregar $exe $puerto '' 'proceso en ejecucion'
  }

  # --- 3. puertos que contestan el saludo de MySQL ------------------------
  foreach ($p in $MYSQL_PUERTOS_HABITUALES) {
    Agregar '' $p '' "puerto $p"
  }

  # --- se confirma cual esta vivo de verdad -------------------------------
  foreach ($m in $script:motoresHallados) {
    $con = Mysql-Conectar -Servidor '127.0.0.1' -Puerto $m.Puerto -Milisegundos 1200
    if ($con.Ok) {
      $m.Responde = $true
      $m.Version = $con.Version
      try { $con.Cliente.Close() } catch { }
    }
  }

  @($script:motoresHallados | Where-Object { $_.Responde })
}

function Buscar-ClienteMysql {
  <#
    mysql.exe y mysqldump.exe. Se busca, en este orden:
      1. junto al motor que se va a usar (misma version, es lo mejor)
      2. en el PATH
      3. en las carpetas donde suelen quedar los instaladores conocidos
    Devuelve rutas vacias si no aparece ninguno; quien llama decide que hacer.
  #>
  param([string]$BinDelMotor = '')

  $resultado = [pscustomobject]@{ Mysql = ''; Mysqldump = ''; Origen = '' }

  $candidatos = @()
  if ($BinDelMotor) { $candidatos += $BinDelMotor }

  foreach ($n in @('mysql.exe', 'mariadb.exe')) {
    $enPath = Get-Command $n -ErrorAction SilentlyContinue
    if ($enPath) { $candidatos += (Split-Path $enPath.Source -Parent) }
  }

  foreach ($unidad in (Get-PSDrive -PSProvider FileSystem -ErrorAction SilentlyContinue | Where-Object { $_.Root -match '^[A-Za-z]:\\$' })) {
    foreach ($raiz in $MYSQL_RAICES_HABITUALES) {
      $base = Join-Path $unidad.Root $raiz
      if (-not (Test-Path $base)) { continue }
      $candidatos += $base
      $candidatos += (Join-Path $base 'bin')
      # Laragon y MySQL Server guardan cada version en su propia subcarpeta.
      foreach ($sub in (Get-ChildItem $base -Directory -ErrorAction SilentlyContinue | Select-Object -First 12)) {
        $candidatos += (Join-Path $sub.FullName 'bin')
      }
    }
  }

  foreach ($c in ($candidatos | Where-Object { $_ } | Select-Object -Unique)) {
    foreach ($nombre in @('mysql.exe', 'mariadb.exe')) {
      $exe = Join-Path $c $nombre
      if (-not (Test-Path $exe)) { continue }
      $volcado = ''
      foreach ($nv in @('mysqldump.exe', 'mariadb-dump.exe')) {
        if (Test-Path (Join-Path $c $nv)) { $volcado = Join-Path $c $nv; break }
      }
      # Solo sirve si tambien hay con que volcar: sin respaldo no se adopta nada.
      if ($volcado) {
        $resultado.Mysql = $exe
        $resultado.Mysqldump = $volcado
        $resultado.Origen = $c
        return $resultado
      }
      if (-not $resultado.Mysql) { $resultado.Mysql = $exe; $resultado.Origen = $c }
    }
  }
  return $resultado
}

# ---------------------------------------------------------------------------
# Probar, consultar, respaldar y restaurar
# ---------------------------------------------------------------------------

function Probar-Mysql {
  <#
    Conexion de verdad al MySQL del cliente, igual que Probar-SqlServer hace con
    el SQL Server del ERP. Comprobar solo el puerto no dice nada: el usuario
    puede estar mal, la clave puede estar mal o la base puede no existir, y eso
    se descubriria recien despues de haber tocado la instalacion.

    Usa el mysql.exe del cliente si se le indica uno; si no, habla el protocolo.
  #>
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$Base = '',
    [string]$MysqlExe = '', [int]$Segundos = 10
  )

  if (-not $MysqlExe -or -not (Test-Path $MysqlExe)) {
    return (Mysql-ProbarPorProtocolo -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -Base $Base -Milisegundos ($Segundos * 1000))
  }

  $r = [pscustomobject]@{ Ok = $false; Motivo = ''; Detalle = ''; Version = ''; Bases = @(); Via = 'mysql.exe' }

  $salida = Mysql-ConCnf -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -Bloque {
    param($cnf)
    $sql = if ($Base) { "USE ``$Base``; SELECT VERSION();" } else { 'SELECT VERSION();' }
    Mysql-EjecutarProceso -Exe $MysqlExe -Argumentos "--defaults-extra-file=`"$cnf`" --connect-timeout=$Segundos -N -B -e `"$sql`"" -Segundos ($Segundos + 20)
  }

  if ($salida.Codigo -eq 0) {
    $r.Ok = $true
    $r.Version = ($salida.Salida -split "`r?`n" | Select-Object -Last 1).Trim()
    $r.Bases = @(Mysql-Bases -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -MysqlExe $MysqlExe)
    return $r
  }

  $texto = ($salida.Error + ' ' + $salida.Salida).Trim()
  $r.Motivo = Mysql-Motivo $texto
  $r.Detalle = $texto
  return $r
}

function Mysql-Consulta {
  <#
    Una consulta que devuelve texto plano, sin cabeceras (-N -B), como hace
    Mysql-Sql de comun.ps1 pero contra un servidor cualquiera.
  #>
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$Base = '',
    [string]$MysqlExe, [string]$Sql, [int]$Segundos = 120
  )
  if (-not $MysqlExe -or -not (Test-Path $MysqlExe)) { throw 'No hay un mysql.exe con el que consultar la base.' }

  $r = Mysql-ConCnf -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -Bloque {
    param($cnf)
    $archivo = Join-Path $env:TEMP ('gre-adopcion-' + [guid]::NewGuid() + '.sql')
    Mysql-EscribirTextoPlano $archivo $Sql
    try {
      Mysql-EjecutarProceso -Exe $MysqlExe -Argumentos "--defaults-extra-file=`"$cnf`" -N -B $Base -e `"source $($archivo -replace '\\', '/')`"" -Segundos $Segundos
    } finally { Remove-Item $archivo -Force -ErrorAction SilentlyContinue }
  }
  if ($r.Codigo -ne 0) { throw "La consulta a MySQL fallo: $($r.Error)" }
  $r.Salida
}

function Mysql-Escalar {
  # Un solo valor. Devuelve cadena vacia si no vino nada.
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$Base = '', [string]$MysqlExe, [string]$Sql
  )
  $t = Mysql-Consulta -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -Base $Base -MysqlExe $MysqlExe -Sql $Sql
  ($t -split "`r?`n" | Where-Object { $_ -ne '' } | Select-Object -First 1)
}

function Mysql-Bases {
  # Las bases que ese usuario ve de verdad, para elegir por numero en vez de
  # escribir el nombre a ciegas.
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$MysqlExe
  )
  if (-not $MysqlExe -or -not (Test-Path $MysqlExe)) { return @() }
  try {
    $t = Mysql-Consulta -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -MysqlExe $MysqlExe `
         -Sql "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('mysql','information_schema','performance_schema','sys') ORDER BY schema_name;"
    @($t -split "`r?`n" | Where-Object { $_.Trim() -ne '' } | ForEach-Object { $_.Trim() })
  } catch { @() }
}

function Mysql-EsBaseGre {
  # Una base del GRE se reconoce por sus dos tablas de guias. Es el mismo
  # criterio que usa gre:baseline para negarse a tocar un esquema ajeno.
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$Base, [string]$MysqlExe
  )
  try {
    $n = Mysql-Escalar -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -MysqlExe $MysqlExe `
         -Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$Base' AND table_name IN ('guia_ingresos','guia_salidas');"
    return ([int]$n -eq 2)
  } catch { return $false }
}

function Mysql-Respaldar {
  <#
    Volcado completo de una base. Antes de tocar nada, siempre.
    Se comprueba que el archivo tenga tamano: un mysqldump que falla a la mitad
    deja un .sql valido pero incompleto, y eso es peor que no tener respaldo.
  #>
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$Base,
    [string]$MysqldumpExe, [string]$Archivo, [int]$Segundos = 1800
  )
  if (-not $MysqldumpExe -or -not (Test-Path $MysqldumpExe)) {
    throw 'No hay mysqldump.exe: sin respaldo no se puede continuar.'
  }

  $r = Mysql-ConCnf -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -Bloque {
    param($cnf)
    Mysql-EjecutarProceso -Exe $MysqldumpExe -Segundos $Segundos `
      -Argumentos "--defaults-extra-file=`"$cnf`" --single-transaction --routines --triggers --events --default-character-set=utf8mb4 --result-file=`"$Archivo`" `"$Base`""
  }

  # --events no existe en algunos MariaDB antiguos: se reintenta sin el antes
  # de dar el respaldo por perdido.
  if ($r.Codigo -ne 0 -and $r.Error -match '(?i)unknown option|events') {
    $r = Mysql-ConCnf -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -Bloque {
      param($cnf)
      Mysql-EjecutarProceso -Exe $MysqldumpExe -Segundos $Segundos `
        -Argumentos "--defaults-extra-file=`"$cnf`" --single-transaction --routines --triggers --default-character-set=utf8mb4 --result-file=`"$Archivo`" `"$Base`""
    }
  }

  if ($r.Codigo -ne 0) { throw "mysqldump fallo: $($r.Error)" }
  if (-not (Test-Path $Archivo) -or (Get-Item $Archivo).Length -lt 1000) {
    throw "El respaldo de la base salio vacio: $Archivo"
  }
  Write-Host "  respaldo de la base: $Archivo ($([int]((Get-Item $Archivo).Length / 1KB)) KB)"
  $Archivo
}

function Mysql-Restaurar {
  # Devuelve la base al estado del volcado. Se usa solo al revertir.
  param(
    [string]$Servidor = '127.0.0.1', [int]$Puerto = 3306,
    [string]$Usuario, [string]$Clave, [string]$Base,
    [string]$MysqlExe, [string]$Archivo, [int]$Segundos = 1800
  )
  if (-not (Test-Path $Archivo)) { throw "No existe el respaldo $Archivo" }

  $r = Mysql-ConCnf -Servidor $Servidor -Puerto $Puerto -Usuario $Usuario -Clave $Clave -Bloque {
    param($cnf)
    $sql = "SET FOREIGN_KEY_CHECKS=0; DROP DATABASE IF EXISTS ``$Base``; CREATE DATABASE ``$Base`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; USE ``$Base``; source $($Archivo -replace '\\', '/');"
    $tmp = Join-Path $env:TEMP ('gre-restaurar-' + [guid]::NewGuid() + '.sql')
    Mysql-EscribirTextoPlano $tmp $sql
    try {
      Mysql-EjecutarProceso -Exe $MysqlExe -Argumentos "--defaults-extra-file=`"$cnf`" -e `"source $($tmp -replace '\\', '/')`"" -Segundos $Segundos
    } finally { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
  }
  if ($r.Codigo -ne 0) { throw "No se pudo restaurar la base: $($r.Error)" }
}
