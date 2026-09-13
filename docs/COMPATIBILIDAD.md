# Compatibilidad del paquete con las versiones de Windows

Revisado el 13 de septiembre de 2026 contra la documentacion de cada proveedor.
Este documento existe porque el proyecto venia prometiendo **Windows 7 SP1 como
minimo soportado** sin haberlo instalado nunca ahi. Hay alrededor de 100
clientes reales: la promesa tenia que verificarse o retirarse.

Las versiones que se revisan son exactamente las de `instalador/runtime.lock`,
que es lo unico que se instala en el servidor del cliente.

Se distinguen tres cosas, y no se mezclan:

- **PROBADO**: se instalo y funciono, con fecha y evidencia.
- **DECLARADO**: el proveedor dice en su documentacion que soporta ese sistema.
  No es lo mismo que funcionar.
- **NO CONFIRMADO**: no se encontro fuente primaria. Aqui se marca asi en lugar
  de afirmarlo.

---

## 1. Componente por componente

| Componente | Version instalada | Windows minimo segun el proveedor | Fuente | Estado |
|---|---|---|---|---|
| PHP | 7.4.33 Win32 VC15 x64 | Windows 7 / Server 2008 R2 | Manual de PHP, requisitos en Windows | DECLARADO |
| Apache httpd | 2.4.66 Win64 VS17 (build 251206) | Windows 7 SP1 / Server 2008 R2 SP1 | Apache Lounge, pagina VS17 | DECLARADO, pero se contradice (ver 2.2) |
| Visual C++ Redistributable | 14.44.35211 x64 | Windows 10 / Server 2016 para la version actual | Microsoft Learn, "Latest supported VC++ Redistributable downloads" | El corte exacto de version NO CONFIRMADO |
| MySQL | 5.7.44 winx64 | No publicado ya para 5.7 | mysql.com, pagina de plataformas soportadas | NO CONFIRMADO |
| Java (JRE) | Temurin 8u504-b01 | Adoptium no publica version minima de Windows | adoptium.net | NO CONFIRMADO |

### 1.1 PHP 7.4.33

- Requisito: *"PHP 5.5+ require at least Windows 2008/Vista, or 2008r2, 2012,
  2012r2, 2016 or 7, 8, 8.1, 10"*, y *"As of PHP 7.2.0 Windows 2008 and Vista
  are no longer supported."* El suelo efectivo para 7.4 es **Windows 7 /
  Server 2008 R2**.
- La pagina viva `php.net/manual/en/install.windows.requirements.php` devolvio
  **HTTP 404** el 13 sep 2026. El texto citado viene de un espejo del manual de
  la version 7.4. Marcado como no verificable hoy en la fuente original.
- PHP 7.4 llego a **fin de vida el 28 de noviembre de 2022** (`php.net/eol.php`).
  No recibe parches de seguridad desde entonces.
- La afirmacion de STACK.md de que "PHP 8.x exige Win8+" **NO ESTA CONFIRMADA**:
  el texto de requisitos que se pudo recuperar para PHP 8.3 dice lo mismo que el
  de 7.4. Es decir, el motivo real para quedarse en PHP 7.4 no esta demostrado.

### 1.2 Apache httpd 2.4.66 VS17

- Apache Lounge, pagina VS17: *"Runs on: 7 SP1, Vista SP2, 8/8.1, 10, 11 Server
  2008 SP2 / R2 SP1, Server 2012 / R2, Server 2016/2019/2022/2025."* y *"The
  binaries do not run on XP and 2003."*
- La compilacion VS18, que es la corriente, dice lo contrario: *"do not run on
  XP, 7 SP1 and 2003."* Por eso el paquete se quedo en VS17. Esa decision esta
  bien fundada.
- **El problema**: esa misma pagina VS17 pide *"Be sure you installed latest
  14.50.35719 Visual C++ Redistributable Visual Studio 2017-2026"*. Y el
  redistribuible 14.50 es de los que Microsoft ya solo soporta en Windows 10 y
  11 (ver 1.3). El proveedor declara Windows 7 SP1 y a la vez exige un runtime
  que Microsoft no soporta en Windows 7. Las dos cosas no pueden ser ciertas a
  la vez sin que alguien lo pruebe.
- Lo que si esta probado: la 2.4.66 VS17 **funciona con el redistribuible 14.44
  que lleva el paquete**, no hace falta el 14.50. Comprobado en Windows 11 ARM64
  el 11 sep 2026. Para Windows 7 no hay ninguna prueba.
- Apache 2.4.66-251206 se publico el 10 de diciembre de 2025.

### 1.3 Visual C++ Redistributable 14.44.35211

- Microsoft, pagina de descargas del redistribuible: *"The latest version of the
  Visual C++ v14 Redistributable included with Visual Studio 2026 supports only
  the following operating systems: Windows 10 and 11; Windows Server 2016, 2019,
  2022, and 2025."*
- Microsoft, requisitos de sistema de Visual Studio 2022, sobre el
  redistribuible 2015-2022: *"Also installs on Windows 7 SP1 and Windows Server
  2008 R2 SP1 to support applications built using the Visual C++ 2017, and
  Visual C++ 2015 tools."*
- Las dos frases son de Microsoft y apuntan a cosas distintas. **La version
  exacta en la que el redistribuible dejo de instalarse en Windows 7 NO ESTA
  CONFIRMADA**: Microsoft no numera la version en esa pagina. Por tanto **no se
  puede afirmar ni que 14.44 instale en Windows 7 ni que no instale.**
- Dato secundario, no primario: en el foro de Apache Lounge un moderador afirma
  que *"recent VS18 vc_redist installers (>= V14.50) are the problem"* en
  Windows 7. Si el corte estuviera en 14.50, el 14.44 del paquete quedaria del
  lado bueno. Es foro, no documentacion: **no sirve como garantia**.
- En Windows 7 el instalador del redistribuible depende ademas del soporte de
  firmas SHA-2 (ver 3.2).

### 1.4 MySQL 5.7.44

- `mysql.com/support/supportedplatforms/database.html` **ya no lista MySQL 5.7**:
  solo las ramas vivas. Que versiones de Windows soportaba 5.7 es **NO
  CONFIRMADO**; el manual 5.7 remite precisamente a esa pagina que ya cambio.
- Requisito de runtime, del manual 5.7: *"MySQL 5.7.37 and below requires the
  Microsoft Visual C++ 2013 Redistributable Package, MySQL 5.7.38 and 5.7.39
  require both, and only the Microsoft Visual C++ 2019 Redistributable Package is
  required as of MySQL 5.7.40."* La 5.7.44 que lleva el paquete **necesita el
  redistribuible 2019**, que es de la familia 14.x que ya instala el paquete.
- 5.7.44 es **la ultima version de la rama**, publicada el 25 de octubre de 2023.
  Desde esa fecha 5.7 esta en Sustaining Support de Oracle: sin parches nuevos.

### 1.5 Temurin JRE 8u504-b01

- La tabla de plataformas soportadas de Adoptium se dibuja con JavaScript y no
  se pudo extraer su contenido: **NO CONFIRMADO** que version de Windows declara
  soportar. Lo unico textual que publica su pagina de instalacion es *"Windows
  installer packages are supported only on Windows x64 systems."*
- El binario es reciente: 8u504-b01 se publico el **25 de agosto de 2026**. Se
  compila con Visual Studio 2022 LTSC 17.7.3 (toolset 14.37.32822) y las DLL
  redistribuibles que acompanan salen de VS2022 17.10.3 (toolset 14.40.33807)
  con el SDK de Windows 11. Si esas DLL necesitan Windows 10 o superior, Java no
  arranca en Windows 7; **no se pudo confirmar el minimo de esas DLL**.
- Oracle, configuraciones certificadas de JDK 8, si lista **Windows 7 SP1**, pero
  bajo el epigrafe de no soportado: *"Previously supported Operating System. No
  longer supported by the Vendor."*
- Conclusion honesta: **ningun proveedor declara hoy soporte de Windows 7 para
  una compilacion de Java 8 de 2026.** STACK.md decia "Java 8u202, ultima con
  soporte real en Win7"; el paquete no instala 8u202, instala 8u504-b01.

---

## 2. Veredicto por sistema operativo

### 2.1 PROBADO

**Windows 11 Pro ARM64, en espanol (ISO `Win11_25H2_Spanish_Mexico_Arm64`).**
Probado el **11 de septiembre de 2026**, en la maquina virtual de
`vm-windows/`, con PowerShell 5.1 y 4 GB de memoria, contra un SQL Server
alcanzado por red.

- Instalacion nueva `INSTALACION_OK`, repetible, actualizacion en 27 s con
  respaldo, y reversion automatica comprobada de verdad.
- Recorrido completo del sistema: 22 de 23 pasos. El que falla es por un
  procedimiento ausente en el SQL Server de pruebas, no por el sistema operativo.
- Evidencia: `vm-windows/pruebas/instalacion-173815.log` y
  `vm-windows/pruebas/e2e-vm.json`.
- Lo instalado coincide con `runtime.lock`: las huellas SHA-256 del Apache VS17
  2.4.66 y del Visual C++ 14.44.35211 del paquete son las fijadas.
- Los cinco runtimes son x64 y corrieron **emulados**, porque el sistema es
  ARM64. Microsoft: *"Windows 11 on Arm supports emulation of both x86 and x64
  apps."* Que funcione emulado es una senal buena, no una garantia para x64
  nativo, aunque el caso emulado suele ser el mas exigente.

### 2.2 PLAUSIBLE, pero nadie lo ha verificado

**Windows 10 x64, Windows 11 x64, Windows Server 2016 / 2019 / 2022 / 2025.**

Los cinco componentes declaran soportar estos sistemas, y ademas son el terreno
comun de todos los proveedores: es donde Apache, Microsoft, Oracle y Adoptium
coinciden. No hay ninguna contradiccion entre fuentes. Falta, simplemente,
instalarlo una vez.

Riesgo estimado: bajo. Pero sigue siendo "no probado".

### 2.3 NO VA A FUNCIONAR

**Windows XP, Windows Vista, Windows Server 2003, Windows Server 2008 anterior
a R2, y cualquier Windows de 32 bits.**

- Apache Lounge: *"The binaries do not run on XP and 2003."*
- PHP desde 7.2.0 no soporta Windows 2008 ni Vista.
- Los cinco runtimes del paquete son x64: en un Windows de 32 bits no hay nada
  que instalar.

El instalador ahora se detiene en estos casos (ver seccion 4).

### 2.4 Windows 7 SP1 y Server 2008 R2 SP1: la promesa no se sostiene

**No se puede seguir declarando Windows 7 SP1 como minimo soportado.** No por
una prueba que haya fallado, sino porque no hay ninguna prueba y la
documentacion de los proveedores ya no lo respalda:

| Componente | Situacion en Windows 7 |
|---|---|
| PHP 7.4.33 | DECLARADO. Es el unico que lo sostiene con claridad |
| Apache 2.4.66 VS17 | Declara "7 SP1", pero exige un redistribuible que Microsoft solo soporta en Windows 10+ |
| Visual C++ 14.44 | Sin confirmar. Microsoft ya no declara Windows 7 en la version actual |
| MySQL 5.7.44 | Sin confirmar. Oracle retiro la informacion de 5.7 |
| Temurin 8u504 | Sin confirmar, y es un binario de 2026 compilado con herramientas de 2024 |

Tres de los cinco componentes estan en "no confirmado" y uno se contradice a si
mismo. Un solo componente, PHP, sostiene la promesa entera.

Ademas el sistema operativo llega con deuda propia:

- Windows 7 esta **fuera de soporte desde el 14 de enero de 2020**, y el
  programa ESU de pago **termino el 10 de enero de 2023**. Server 2008 R2 tuvo un
  cuarto ano de ESU solo en Azure, hasta enero de 2024.
- **TLS 1.2 no esta activo de fabrica**: *"As these protocol versions are not
  enabled by default in Windows 7, you must configure the registry settings."*
  (KB3140245). Afecta a cualquier salida HTTPS del servidor.
- **PowerShell 5.1 no viene incluido**: hay que instalar WMF 5.1 (KB3191566), y
  *"WMF 5.1 requires the .NET Framework 4.5.2 (or above)."*
- Sin **KB4474419** el sistema no acepta binarios firmados solo con SHA-2 y
  devuelve el error `0xc0000428`. Es exactamente el escenario del instalador del
  Visual C++.

### 2.5 Un defecto propio en Windows 7, ya localizado

`comun.ps1:403` (`Ip-De-Este-Equipo`) usa **`Get-NetIPAddress`**, del modulo
NetTCPIP. Microsoft solo documenta ese cmdlet para Windows Server 2016 y
posteriores; el modulo aparecio con Windows 8 / Server 2012 (esto ultimo, fuente
secundaria: **NO CONFIRMADO** en documentacion primaria).

En Windows 7 ese cmdlet **no existe**. La llamada esta protegida con
`-ErrorAction SilentlyContinue`, asi que no rompe la instalacion: devuelve
`localhost`. El efecto es que el resumen final y `LEEME-INSTALACION.txt` le
darian al cliente `http://localhost` en lugar de la direccion real del servidor
en la red. No es fatal, pero es incorrecto, y aparecera el dia que alguien
instale en Windows 7. No se corrige aqui porque `comun.ps1` lo esta tocando otro
trabajo.

---

## 3. Que hacer con Windows 7

Hay dos caminos honestos. El que no vale es dejar la promesa escrita sin probarla.

### Opcion A, recomendada: retirar la promesa

Declarar **Windows 10 x64 / Server 2016 como minimo**, que es donde todos los
proveedores coinciden, y tratar Windows 7 como caso a evaluar cliente por
cliente. Hay que saber cuantos de los ~100 clientes siguen en Windows 7 antes de
decidir: ese dato no esta en el repositorio.

### Opcion B: sostener Windows 7 de verdad

Exige bajar versiones y, sobre todo, **probarlo en una maquina Windows 7 SP1
real**. Sin esa prueba se vuelve al punto de partida.

| Componente | Cambio | Que se pierde |
|---|---|---|
| Apache | Compilacion **VS16** (pide Visual C++ 14.36.32532, y declara "Runs on: 7 SP1...") | Apache Lounge avisa: *"This are the last VS16 downloads, not updated anymore."* Sin parches desde 2023 |
| Visual C++ | Fijar una version 14.3x que si instale en Windows 7 SP1 | El corte exacto no esta documentado: hay que probarlo, no deducirlo |
| MySQL | **5.7.37 o anterior**, que solo pide el redistribuible 2013 | Se pierden las correcciones de 5.7.38 a 5.7.44 |
| Java | Una compilacion mas antigua de Temurin 8, u Oracle JDK 8u202 | Sin parches de seguridad de Java desde 2019 |
| PHP | Se queda en 7.4.33 | Nada: ya es el techo |

Y en cada servidor Windows 7, antes: SP1, **KB4474419**, WMF 5.1 con .NET 4.5.2,
y TLS 1.2 habilitado por registro.

El resultado es un conjunto mas viejo y sin parchear en cuatro de cinco
componentes, para sostener un sistema operativo que Microsoft no parchea desde
enero de 2023. Merece una decision explicita del negocio, no una linea heredada
en un documento.

---

## 4. Lo que comprueba el instalador desde ahora

`instalador/requisitos.ps1` revisa el equipo **antes de tocar nada**:

**Detiene la instalacion** solo cuando de verdad no puede funcionar:

- Windows de 32 bits (todos los runtimes son x64).
- Windows anterior a 7 SP1.
- Windows 7 o Server 2008 R2 **sin SP1**.
- PowerShell anterior a 3.0 (el instalador usa `ConvertFrom-Json` y
  `-UseBasicParsing`).
- Sin .NET Framework 4.5 (se usa `ZipFile` para descomprimir los runtimes).

**Avisa y deja continuar** en todo lo demas, que es el caso de Windows 7 SP1:
dice que ahi nunca se probo, que revise este documento, y comprueba si falta
KB4474419. Ese aviso nunca detiene la instalacion, porque `Get-HotFix` solo
lista lo instalado por CBS: *"Updates supplied by Microsoft Windows Installer
(MSI) or the Windows Update site aren't returned by Win32_QuickFixEngineering."*
Una actualizacion puede estar puesta y no aparecer.

---

## 5. Lo que quedo sin poder verificarse

Marcado aqui para que nadie lo de por cierto mas adelante:

1. Si el Visual C++ **14.44.35211** instala o no en Windows 7 SP1. Microsoft no
   publica el numero de version del corte.
2. Que versiones de Windows soportaba **MySQL 5.7**. Oracle retiro la pagina.
3. Que version minima de Windows declara **Adoptium** para Temurin 8, y si las
   DLL de runtime que acompanan al JRE funcionan en Windows 7.
4. El texto actual de la pagina de requisitos de **PHP en Windows**: devuelve 404.
   Se cito un espejo.
5. Si **PHP 8.x** exige de verdad Windows 8 o superior, que es como se justifico
   quedarse en PHP 7.4.
6. Que el modulo **NetTCPIP** aparecio en Windows 8 / Server 2012 (solo fuente
   secundaria).

Nada de esto se resuelve leyendo mas documentacion: se resuelve instalando el
paquete en un Windows 7 SP1 x64 real. Es la unica prueba que falta, y es la que
convertiria este documento en una respuesta en lugar de un inventario de dudas.

---

## Fuentes

- Apache Lounge, binarios VS17: https://www.apachelounge.com/download/VS17/
- Apache Lounge, binarios VS18 y VS16: https://www.apachelounge.com/download/
- Microsoft, redistribuibles VC++ soportados: https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist
- Microsoft, requisitos de Visual Studio 2022: https://learn.microsoft.com/en-us/visualstudio/releases/2022/system-requirements
- Microsoft, KB4474419 (firma SHA-2): https://support.microsoft.com/en-us/topic/sha-2-code-signing-support-update-for-windows-server-2008-r2-windows-7-and-windows-server-2008-september-23-2019-84a8aad5-d8d9-2d5c-6d78-34f9aa5f8339
- Microsoft, KB3140245 (TLS 1.1/1.2 en Windows 7): https://support.microsoft.com/en-us/topic/update-to-enable-tls-1-1-and-tls-1-2-as-default-secure-protocols-in-winhttp-in-windows-c4bd73d2-31d7-761e-0178-11268bb10392
- Microsoft, WMF 5.1: https://learn.microsoft.com/en-us/previous-versions/powershell/scripting/windows-powershell/wmf/setup/install-configure
- Microsoft, ciclo de vida de Windows 7: https://learn.microsoft.com/en-us/lifecycle/products/windows-7
- Microsoft, emulacion x64 en Arm: https://learn.microsoft.com/en-us/windows/arm/apps-on-arm-x86-emulation
- Microsoft, `Get-HotFix` y `Win32_QuickFixEngineering`: https://learn.microsoft.com/en-us/windows/win32/cimwin32prov/win32-quickfixengineering
- Microsoft, `Get-NetIPAddress`: https://learn.microsoft.com/en-us/powershell/module/nettcpip/get-netipaddress
- PHP, fin de vida: https://www.php.net/eol.php
- MySQL 5.7, instalacion en Windows: https://docs.oracle.com/cd/E17952_01/mysql-5.7-en/windows-installation.html
- MySQL, notas de la 5.7.44: https://docs.oracle.com/cd/E17952_01/mysql-5.7-relnotes-en/news-5-7-44.html
- MySQL, aviso de fin de soporte: https://www.mysql.com/support/eol-notice.html
- Adoptium, instalacion en Windows: https://adoptium.net/installation/windows/
- Adoptium, herramientas de compilacion en Windows: https://github.com/adoptium/temurin-build/wiki/Eclipse-Temurin-Windows-Visual-Studio,-Toolset,-SDK-and-ReDist-versions
- Oracle, configuraciones certificadas de JDK 8: https://www.oracle.com/java/technologies/javase/products-doc-jdk8-jre8-certconfig.html
