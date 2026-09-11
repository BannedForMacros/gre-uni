# Instalación y actualización — Guías Electrónicas (on-premise)

Este documento va dentro del paquete como `LEEME-INSTALACION.md`. Es para el
técnico que instala o actualiza el sistema en el servidor del cliente.

## 1. Qué lleva el paquete

`dbperu-guias-<AÑO>.<MES>.<N>.zip` trae todo lo necesario. Nada se descarga
durante la instalación.

| Carpeta / archivo | Contenido |
|---|---|
| `app/` | Aplicación web (Laravel 8), sin dependencias de desarrollo |
| `api-gre.jar` | ApiGRE (Spring Boot, Java 8): puente con el SQL Server del ERP |
| `runtime/` | PHP 7.4.33, Apache 2.4.66 (VS17), MySQL 5.7.44, Java 8 (Temurin), Visual C++ 14.44 |
| `instalar.ps1`, `actualizar.ps1`, `comun.ps1` | Scripts de instalación y actualización |
| `sql/` | Procedimientos del DataMart que usa el sistema (SP01, SP02) |
| `VERSION.txt` | Commit exacto de la aplicación y de ApiGRE |

Las versiones están fijadas en `instalador/runtime.lock` y se verifican por SHA-256
al armar el paquete.

## 2. Requisitos del servidor

- **Windows 7 SP1 x64 o superior**, o Windows Server 2008 R2 SP1 o superior.
- **PowerShell 5.1.** Windows 10, 11 y Server 2016 en adelante ya lo traen. En
  Windows 7 y Server 2008 R2 hay que instalar antes WMF 5.1 y .NET Framework 4.5.
- **Puertos libres:** 80 (web) y 3306 (MySQL). Si el servidor tiene Laragon,
  XAMPP o IIS usándolos, hay que detenerlos primero: el instalador se niega a
  seguir si los encuentra ocupados.
- **Acceso al SQL Server del ERP** desde el servidor: host, puerto, base, usuario y
  clave.
- **Procedimientos del DataMart instalados.** ApiGRE los llama pero no los crea.
  Si falta alguno, esa búsqueda sale vacía sin ningún error en pantalla; por eso
  el instalador los revisa y avisa (ver 5).

## 3. Armar el paquete (en la máquina de desarrollo)

```bash
instalador/construir-paquete.sh 2026.09.1
```

Usa el commit actual (HEAD) de este repositorio y del repositorio `api-gre`.
Si hay cambios sin commit, avisa y los deja fuera. El resultado queda en
`dist/dbperu-guias-2026.09.1.zip`.

## 4. Instalación nueva

1. Copie el zip al servidor y descomprímalo, por ejemplo en `C:\Instaladores`.
2. Abra **PowerShell como Administrador** en esa carpeta.
3. Ejecute:

```powershell
powershell -ExecutionPolicy Bypass -File .\instalar.ps1 `
  -Ruc 20100030838 -RazonSocial "EMPRESA SAC" -Direccion "AV. ..." `
  -SqlServidor "192.168.1.10,1433" -SqlBase db_cliente -SqlUsuario usuario -SqlClave "..." `
  -FacturacionUrl "..." -FacturacionConsultasUrl "..." -FacturacionCredencial "..."
```

`-SqlServidor` acepta `host`, `host,puerto` o `host\instancia`.

**Qué hace, en orden:**

1. Comprueba que el paquete esté completo y que los puertos estén libres.
2. Instala Visual C++ 14.44 si falta.
3. Descomprime PHP, Apache, MySQL y Java en `C:\DBPeru\GRE`.
4. Configura PHP con zona horaria America/Lima y las extensiones que usa el
   sistema. `intl` es obligatoria porque la facturación la usa para quitar tildes.
5. Inicializa MySQL como servicio **GRE-MySQL**, escuchando solo en 127.0.0.1.
   Genera claves aleatorias y las guarda en `C:\DBPeru\GRE\config\secretos.json`,
   con acceso solo para administradores.
6. Copia la aplicación y crea el `.env` si no existe. Corre las migraciones y
   `gre:inicializar`, que crea el usuario administrador.
7. Configura Apache como servicio **GRE-Apache** y abre el puerto web en el firewall.
8. Registra ApiGRE como tarea programada **GRE-ApiGRE**. Arranca con Windows, no
   tiene límite de tiempo y se reinicia sola si Java se cae.
9. Verifica que ApiGRE y la web respondan.

Al terminar muestra `INSTALACION_OK` y escribe
`C:\DBPeru\GRE\LEEME-INSTALACION.txt` con la dirección, el usuario `admin` y su
**clave inicial**. Cámbiela en el primer ingreso.

**Es repetible.** Volver a correrlo repara lo que falte, conserva el `.env`, las
claves y los datos, y no crea otro administrador.

## 5. Verificar la instalación

**ApiGRE:** abra `http://127.0.0.1:8181/api/v1/health` en el servidor.

```json
{"status":"UP","sqlServer":"UP","procedimientos":"COMPLETOS","procedimientosFaltantes":[]}
```

- `sqlServer: DOWN`: revise los datos de conexión en
  `C:\DBPeru\GRE\api\config\application.properties` y reinicie la tarea
  GRE-ApiGRE.
- `procedimientos: INCOMPLETOS`: instale los que aparecen en la lista. Mientras
  falten, esas búsquedas salen vacías. Ejemplo real: sin
  `pr_consultaProveedorlikeRazonsocial`, buscar proveedor por razón social no
  encuentra nada.

**Web:** entre desde otra PC a `http://<ip-del-servidor>` con el usuario `admin`.

**Prueba de punta a punta** (solo en entornos de prueba): `instalador/pruebas/punta-a-punta.php`
recorre login, pantallas, búsquedas, guardado, envío al DataMart, PDF y listados.
**Crea guías reales** marcadas "PRUEBA E2E - NO VALIDA", así que se niega a correr
sin `--entorno-de-pruebas`. Nunca llama a la facturación.

## 6. Actualizar una instalación

Descomprima el paquete **nuevo** y, como Administrador:

```powershell
powershell -ExecutionPolicy Bypass -File .\actualizar.ps1
```

1. Respalda la base con mysqldump y copia la aplicación en
   `C:\DBPeru\GRE\respaldos\<fecha>`.
2. Detiene Apache y ApiGRE.
3. Copia el código nuevo sin tocar `.env`, los archivos subidos ni los logs.
4. Corre las migraciones y `gre:inicializar`, que no pisa parámetros ya configurados.
5. Levanta todo y verifica.

**Si algo falla, revierte solo:** restaura código, ApiGRE y base desde el respaldo
recién hecho, y deja el sistema como estaba. El mensaje final dice `REVERTIDO`, o
`NO SE PUDO REVERTIR` con la ruta del respaldo intacto.

## 7. Clientes que vienen del sistema anterior (Laragon, base importada)

Estos clientes no se instalaron con `instalar.ps1`, así que su migración tiene
pasos manuales:

1. **Respalde** la base actual con mysqldump y la carpeta del sistema anterior.
2. Anote los parámetros actuales. Están en la tabla `parametros`: RUC, razón
   social, URLs y credencial de facturación.
3. **Detenga** Apache y MySQL de Laragon, para liberar los puertos 80 y 3306.
4. Corra `instalar.ps1` con los datos del cliente.
5. Importe el respaldo en la base nueva `guia_electronica`. La clave de root está
   en `config\secretos.json`.
6. Corra `actualizar.ps1`. Detecta que la tabla `migrations` está vacía, ejecuta
   `gre:baseline`, que marca como aplicado el esquema existente sin tocar datos,
   y luego aplica solo lo nuevo.
7. Copie el logo desde el sistema anterior a `app\storage\app\public\empresa\`.

## 8. Seguridad

- **La instalación no emite nada a SUNAT.** La facturación solo se usa cuando un
  usuario envía una guía de salida.
- `secretos.json`, `application.properties` y `LEEME-INSTALACION.txt` quedan
  legibles solo por administradores. Los grupos se asignan por SID, así que
  funciona igual en Windows en español.
- MySQL y ApiGRE escuchan solo en 127.0.0.1. Hacia la red solo se abre el puerto web.

## 9. Problemas conocidos

| Síntoma | Causa | Qué hacer |
|---|---|---|
| "El puerto 80 ya lo usa otro programa" | Laragon, XAMPP o IIS | Detenerlo, o instalar con `-PuertoWeb 8080` |
| "Existe una base MySQL pero falta secretos.json" | Se borró `config\secretos.json` | Restaurarlo desde un respaldo; sin él no se conoce la clave de root |
| "PHP no cargo la extension …" | Falta Visual C++ o el zip está dañado | Reinstalar Visual C++ 14.44 del paquete y volver a correr el instalador |
| Búsqueda de proveedor por razón social siempre vacía | Falta `pr_consultaProveedorlikeRazonsocial` | Ver `procedimientosFaltantes` en la salud de ApiGRE |
| ApiGRE no responde | Java caído o SQL Server inalcanzable | `C:\DBPeru\GRE\logs\api-gre.log` y `api-gre-consola.log` |

## 10. Estado de la validación

- **Validado (11 sep 2026), en macOS con la misma aplicación, ApiGRE y un SQL
  Server de pruebas:**
  - La prueba de punta a punta pasa 18 de 19 pasos: ingreso y salida completos,
    con DataMart, PDF y listados. El paso que falla es la búsqueda por razón
    social, porque falta ese procedimiento en la base de pruebas.
  - Las guías creadas se confirmaron en `GuiaRemision`.
  - Pasan 216 pruebas PHP, las pruebas JavaScript y las de ApiGRE.
- **Los scripts de PowerShell** pasan el verificador de sintaxis de PowerShell y
  son solo ASCII.
- **Pendiente: correr la instalación completa en Windows.** La máquina virtual
  Windows 11 ARM de pruebas está preparada, pero todavía no se ejecutó.
  Hasta entonces este procedimiento no está probado en un Windows real.
- **Windows 7 no está probado.** Apache y Visual C++ se eligieron porque declaran
  soportarlo. Java 8 Temurin y MySQL 5.7 no se verificaron en Windows 7.
