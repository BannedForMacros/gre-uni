# Comprobacion del sistema ANTES de instalar nada.
#
# Se carga igual que comun.ps1 y se llama una sola vez, antes de preguntar los
# datos del cliente:
#     . (Join-Path $Paquete 'requisitos.ps1')
#     Probar-Requisitos
#
# Solo ASCII a proposito, como comun.ps1: PowerShell 5.1 lee los .ps1 sin BOM
# como ANSI y las tildes se corrompen.
#
# CRITERIO, que es lo importante de este archivo:
#   - DETIENE solo lo que de verdad no puede funcionar: Windows de 32 bits,
#     anterior a 7 SP1, PowerShell sin ConvertFrom-Json o .NET sin ZipFile.
#     Son cosas que el instalador usa en el primer minuto y que no tienen
#     arreglo desde aqui.
#   - AVISA, y deja seguir, de todo lo demas: Windows 7 y Server 2008 R2, donde
#     el paquete NUNCA se probo, y donde los proveedores de tres de los cinco
#     componentes ya no declaran soporte. El aviso dice que revisar y donde.
#
# El detalle componente por componente, con las fuentes, esta en
# docs/COMPATIBILIDAD.md.

# Version minima de .NET que necesita el instalador: 4.5 (378389) por
# System.IO.Compression.ZipFile, que es como se descomprimen los runtimes.
$DOTNET_45 = 378389
# 4.5.2 (379893) es lo que pide WMF 5.1, o sea PowerShell 5.1 en Windows 7.
$DOTNET_452 = 379893
# SHA-2: sin esta actualizacion Windows 7 no carga binarios ni instaladores
# firmados solo con SHA-2, y devuelve 0xc0000428.
$KB_SHA2 = 'KB4474419'

function Datos-Del-Sistema {
  # Get-WmiObject y no Get-CimInstance: en Windows 7 con WMF 5.1 el que esta
  # garantizado es el primero, y comun.ps1 ya lo usa.
  $so = Get-WmiObject Win32_OperatingSystem
  $v = [Version]$so.Version
  $arq = $env:PROCESSOR_ARCHITEW6432
  if (-not $arq) { $arq = $env:PROCESSOR_ARCHITECTURE }
  [pscustomobject]@{
    Nombre        = ($so.Caption -replace '^Microsoft ', '').Trim()
    Version       = $v
    Build         = $v.Build
    ServicePack   = [int]$so.ServicePackMajorVersion
    Arquitectura  = $arq
    So64          = [Environment]::Is64BitOperatingSystem
    Proceso64     = [Environment]::Is64BitProcess
  }
}

function Version-Dotnet {
  # El numero de "Release" es como Microsoft distingue 4.5, 4.5.2, 4.6...
  # Si la clave no existe, en esta maquina no hay .NET 4.5 o posterior.
  $k = 'HKLM:\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full'
  if (-not (Test-Path $k)) { return 0 }
  $r = (Get-ItemProperty $k -Name Release -ErrorAction SilentlyContinue).Release
  if ($r) { [int]$r } else { 0 }
}

function Tiene-Actualizacion([string]$kb) {
  # OJO: Get-HotFix lee Win32_QuickFixEngineering, que solo lista lo instalado
  # por CBS. Una actualizacion puesta por otra via no aparece. Por eso un "no
  # esta" de aqui es un AVISO y nunca una razon para detener la instalacion.
  [bool](Get-HotFix -Id $kb -ErrorAction SilentlyContinue)
}

function Probar-Requisitos {
  Paso 'Comprobando el sistema'

  $s = Datos-Del-Sistema
  $net = Version-Dotnet
  $ps = $PSVersionTable.PSVersion

  Write-Host "  $($s.Nombre)$(if ($s.ServicePack -gt 0) { " SP$($s.ServicePack)" })  ($($s.Version), $($s.Arquitectura))"
  Write-Host "  PowerShell $ps"

  $impiden = New-Object Collections.Generic.List[string]
  $avisos  = New-Object Collections.Generic.List[string]

  # ---------------------------------------------------------- 64 bits
  # Los cinco runtimes del paquete son x64: no hay variante de 32 bits que
  # instalar, asi que en un Windows de 32 bits no hay nada que hacer.
  if (-not $s.So64) {
    $impiden.Add('Este Windows es de 32 bits y todo el paquete es de 64 bits (PHP, Apache, MySQL, Java y Visual C++). No hay forma de instalarlo aqui.')
  } elseif (-not $s.Proceso64) {
    $avisos.Add('Esta corriendo el PowerShell de 32 bits en un Windows de 64 bits. Cierre esta ventana y abra "Windows PowerShell" normal, no el que dice (x86).')
  }

  # ------------------------------------------------- version de Windows
  if ($s.Version -lt [Version]'6.1') {
    $impiden.Add("Windows $($s.Version) es anterior a Windows 7 SP1. PHP 7.4 pide Windows 7 o Server 2008 R2 como minimo, y Apache no corre en XP ni 2003.")
  } elseif ($s.Version -eq [Version]'6.1') {
    if ($s.ServicePack -lt 1) {
      $impiden.Add('Este Windows 7 / Server 2008 R2 no tiene el Service Pack 1. Ningun componente del paquete lo soporta sin SP1, y PowerShell 5.1 tampoco se instala sin el. Instale el SP1 y vuelva a ejecutar.')
    } else {
      # Aqui no se detiene: se avisa con todo el detalle, porque puede que
      # funcione y puede que no, y nadie lo ha comprobado todavia.
      $avisos.Add('WINDOWS 7 / SERVER 2008 R2: el paquete NUNCA se instalo en este sistema. Se probo en Windows 11. Apache declara soportarlo, pero Microsoft ya no soporta el Visual C++ que el propio Apache pide, y ni MySQL 5.7 ni Java 8 Temurin se verificaron aqui. Ver docs/COMPATIBILIDAD.md. Si esta instalacion es para un cliente, pruebe primero en una maquina igual que no este en produccion.')
      if (-not (Tiene-Actualizacion $KB_SHA2)) {
        $avisos.Add("No se ve la actualizacion $KB_SHA2 (firma SHA-2). Sin ella Windows 7 rechaza los programas firmados solo con SHA-2 y el instalador de Visual C++ puede fallar con el error 0xc0000428. Instalela desde el catalogo de Microsoft Update antes de seguir. Aviso y no error: Get-HotFix solo lista lo instalado por CBS, asi que puede estar puesta y no aparecer.")
      }
      if ($net -gt 0 -and $net -lt $DOTNET_452) {
        $avisos.Add('El .NET Framework de esta maquina es anterior a 4.5.2, que es lo que pide WMF 5.1 (PowerShell 5.1) en Windows 7.')
      }
    }
  }

  # ------------------------------------------------------- PowerShell
  # El instalador usa ConvertFrom-Json e Invoke-WebRequest -UseBasicParsing,
  # que no existen en PowerShell 2.0, el que trae Windows 7 de fabrica.
  if ($ps.Major -lt 3) {
    $impiden.Add("PowerShell $ps es demasiado antiguo. En Windows 7 instale WMF 5.1 (KB3191566), que necesita .NET Framework 4.5.2 o posterior.")
  } elseif ($ps.Major -lt 5) {
    $avisos.Add("PowerShell $ps funciona pero no es con el que se probo (5.1). En Windows 7 conviene instalar WMF 5.1 (KB3191566).")
  }

  # ------------------------------------------------------------- .NET
  # ZipFile.ExtractToDirectory es lo que descomprime PHP, Apache, MySQL y Java:
  # sin .NET 4.5 la instalacion muere en el tercer paso.
  if ($net -lt $DOTNET_45) {
    $impiden.Add('Falta el .NET Framework 4.5 o posterior. El instalador lo usa para descomprimir los runtimes, asi que sin el no puede avanzar.')
  }

  # ------------------------------------------------------------- ARM
  # Windows 11 ARM64 es donde SI se probo: los binarios x64 corren emulados.
  if ($s.Arquitectura -eq 'ARM64') {
    Write-Host '  Windows ARM64: los runtimes x64 corren emulados, que es como se probo.'
  }

  foreach ($a in $avisos) { Write-Warning $a }

  if ($impiden.Count -gt 0) {
    foreach ($e in $impiden) { Write-Host "  $e" -ForegroundColor Red }
    throw 'Este equipo no cumple los requisitos minimos. No se ha tocado nada.'
  }

  if ($avisos.Count -eq 0) { Write-Host '  El sistema cumple los requisitos.' -ForegroundColor Green }
  else { Write-Host '  Se puede continuar, pero lea los avisos de arriba.' -ForegroundColor Yellow }
}
