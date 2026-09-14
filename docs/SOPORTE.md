# Guía de soporte — Guías Electrónicas

Este documento es para quien va a instalar el sistema en el servidor de un
cliente. Está escrito para leerse de principio a fin la primera vez, y después
consultarse por secciones.

Los otros manuales son más profundos y explican **por qué** las cosas están
hechas así. Este explica **qué hacer**.

| Manual | En el repositorio | Dentro del zip |
|---|---|---|
| Esta guía | `docs/SOPORTE.md` | `LEEME-PRIMERO.md` |
| Instalación, en detalle | [INSTALACION.md](INSTALACION.md) | `LEEME-INSTALACION.md` |
| Adopción, en detalle | [ADOPCION.md](ADOPCION.md) | `LEEME-ADOPCION.md` |
| Qué Windows funcionan | [COMPATIBILIDAD.md](COMPATIBILIDAD.md) | `LEEME-COMPATIBILIDAD.md` |

---

## 1. Qué se le entrega al cliente

Un solo archivo:

```
dbperu-guias-<versión>.zip        unos 475 MB
```

Dentro va todo: PHP, Apache, MySQL, Java y la aplicación. **En el servidor del
cliente no hace falta descargar nada ni tener internet.**

> **Mande siempre la versión más alta.** Los paquetes se llaman por año, mes y
> número de entrega: `2026.09.9` es posterior a `2026.09.8`. Si en la carpeta
> hay varios, el bueno es el de número mayor de ese mes. Ante la duda, el
> archivo `VERSION.txt` que va dentro dice de qué código salió y cuándo se armó.

### Cómo descomprimirlo en Windows

Clic derecho → **Extraer todo**. O desde la consola:

```
tar -xf dbperu-guias-2026.09.9.zip
```

**No use `Expand-Archive` de PowerShell.** En algunos Windows dice que terminó
correctamente y no extrae nada. Es un fallo silencioso: parece que funcionó.

---

## 2. Antes de ir donde el cliente

Pida estos datos por adelantado. Sin ellos la instalación se queda a medias:

| Dato | Para qué | Si falta |
|---|---|---|
| RUC y razón social | Salen impresos en las guías | El instalador no continúa |
| Dirección y teléfono | Salen impresos en las guías | Puede quedar vacío |
| **Servidor SQL del ERP** | De ahí salen artículos, clientes y proveedores | El instalador no continúa |
| **Base de datos del ERP** | Idem | El instalador la busca y la ofrece en una lista |
| **Usuario y clave del SQL Server** | Idem | El instalador no continúa |
| URLs y credencial del facturador | Para enviar guías a SUNAT | Se instala igual; se completa después desde la pantalla |

También compruebe que el servidor cumple:

- **Windows 10 de 64 bits o Windows Server 2016 en adelante.** El instalador lo
  verifica antes de tocar el disco. Sobre versiones anteriores, lea
  [COMPATIBILIDAD.md](COMPATIBILIDAD.md): Windows 7 **no está probado** y hay
  componentes que declaran no soportarlo.
- **Puertos 80 y 3306 libres.** Si el servidor tiene Laragon, XAMPP o IIS
  usándolos, hay que detenerlos primero. Si el 80 no puede liberarse, se instala
  en otro puerto (sección 9).
- El servidor llega al SQL Server del ERP por red.

---

## 3. Cuál de los tres casos es

El paquete trae tres archivos para doble clic. Elegir bien es lo más importante
de todo este documento.

| El cliente… | Use | Qué hace |
|---|---|---|
| No tiene nada instalado | **INSTALAR.cmd** | Instala el sistema completo desde cero |
| **Ya tiene el sistema, pero se lo instalaron antes**, a mano o con Laragon | **ADOPTAR.cmd** | Conserva su base y sus guías, y lo pone al día |
| Ya fue instalado con este instalador | **ACTUALIZAR.cmd** | Actualiza a la versión nueva |

> Si no está seguro de en qué caso está, **corra `ADOPTAR.cmd` en modo revisión**
> (sección 5). Busca por su cuenta y le dice qué encontró sin escribir nada.

---

## 4. Caso A — Instalación nueva

1. Copie el zip al servidor y descomprímalo, por ejemplo en `C:\Instaladores`.
2. Doble clic en **INSTALAR.cmd**.
3. Acepte el aviso de Windows que pide permisos de administrador.
4. Responda las preguntas. Entre corchetes va el valor por omisión: basta pulsar
   Enter para aceptarlo.

Son nueve preguntas, de las que solo cinco hay que escribir de verdad: RUC,
razón social, servidor SQL, base y clave.

**El instalador se conecta de verdad al SQL Server antes de tocar el servidor.**
No comprueba solo que el puerto responda. Si algo está mal, lo dice con
precisión y vuelve a preguntar:

- *no se llega al servidor* → nombre, puerto o firewall
- *el usuario o la clave no son correctos*
- *la base no existe, o ese usuario no puede abrirla*

Además, si hay un SQL Server en el mismo equipo lo sugiere, y una vez conectado
**muestra la lista de bases que existen** para elegir por número, en vez de
escribir el nombre a ciegas.

Al final muestra un resumen y pide confirmar. **Si algo está mal, no se instala
nada.**

### Cuando termina

Busque esta línea:

```
INSTALACION_OK
```

Y anote lo que muestra: la dirección web, el usuario `admin` y su clave inicial.
Quedan también escritos en:

```
C:\DBPeru\GRE\LEEME-INSTALACION.txt
```

Tarda menos de un minuto. Medido: 29 segundos.

### Sin preguntas, para instalaciones en serie

Copie `datos.txt.ejemplo` como `datos.txt` en la misma carpeta, rellénelo y
ejecute `INSTALAR.cmd`. No preguntará nada.

### Es repetible

Volver a ejecutarlo no rompe nada: repara lo que falte, conserva la
configuración y los datos, y no crea un segundo administrador. Si una
instalación quedó a medias, vuelva a correrlo.

---

## 5. Caso B — El cliente ya tenía el sistema

Este es el camino de los clientes antiguos. **No use `INSTALAR.cmd` aquí.** No
borra sus guías, pero deja un sistema aparte y vacío, y el cliente creería que
las perdió (sección 11).

### Primero, mire sin tocar

1. Abra PowerShell **como administrador**: menú Inicio, escriba *PowerShell*,
   clic derecho → **Ejecutar como administrador**. Sin esto, se detiene con
   *"Ejecute PowerShell como Administrador"*, incluso en modo revisión.
2. Vaya a la carpeta del paquete descomprimido, por ejemplo:

   ```powershell
   cd C:\Instaladores\dbperu-guias-2026.09.9
   ```

3. Ejecute:

   ```powershell
   powershell -ExecutionPolicy Bypass -File .\adoptar.ps1 -SoloRevisar
   ```

Recorre los discos, encuentra las instalaciones, prueba la conexión, cuenta las
guías y muestra un resumen. **No escribe absolutamente nada.** Córralo siempre
la primera vez en un cliente que no conoce.

No da por hecho nada del cliente: encuentra la aplicación esté donde esté —en
Laragon, en XAMPP, en WAMP, en IIS o en una carpeta cualquiera— y encuentra el
motor de base aunque no esté en el puerto 3306.

### Después, adopte

1. **Asegúrese de que nadie esté usando el sistema.**
2. Doble clic en **ADOPTAR.cmd**.
3. Acepte el aviso de permisos de administrador.
4. Elija por número la instalación de la lista y confirme.

Respalda la base y la aplicación, pone al día el esquema sin tocar los datos,
aplica solo lo nuevo, y al final comprueba que **no falte ni sobre ninguna
guía** y que el login funcione.

Busque esta línea:

```
ADOPCION_OK   Respaldo en C:\DBPeru-respaldos\<fecha>
```

Medido sobre el respaldo real de un cliente con 18.272 guías y 301.424 líneas de
detalle: **32 segundos**.

A partir de ese momento ese cliente se actualiza con `ACTUALIZAR.cmd` como
cualquier otro.

---

## 6. Caso C — Actualizar

1. Descomprima el paquete **nuevo**.
2. Doble clic en **ACTUALIZAR.cmd**.

Respalda base y aplicación, detiene los servicios, copia el código nuevo sin
tocar la configuración ni los archivos subidos, aplica las migraciones y vuelve
a levantar todo.

```
ACTUALIZACION_OK
```

Medido: 21 segundos.

---

## 7. Verificar que quedó bien

Tres comprobaciones, en este orden:

**1. El puente con el ERP.** En el servidor, abra:

```
http://127.0.0.1:8181/api/v1/health
```

Lo que debe ver:

```json
{"status":"UP","sqlServer":"UP","procedimientos":"COMPLETOS","procedimientosFaltantes":[]}
```

- `sqlServer: DOWN` → los datos de conexión al ERP están mal. Están en
  `C:\DBPeru\GRE\api\config\application.properties`. Corríjalos y reinicie la
  tarea programada **GRE-ApiGRE**.
- `procedimientos: INCOMPLETOS` → a la base del ERP le faltan procedimientos.
  El instalador aplica los suyos solo; si aun así falta alguno, es porque el
  usuario del ERP no tiene permiso para crearlos. Hay que aplicarlos con quien
  administra el ERP: son los archivos de la carpeta `sql\` del paquete.
  **Mientras falten, esas búsquedas salen vacías sin dar ningún error**, que es
  lo que más despista.

**2. La web.** Desde **otra PC de la red**, entre a `http://<ip-del-servidor>`
con el usuario `admin` y la clave inicial. Que funcione desde otra PC, no desde
el servidor: es como lo va a usar el cliente.

**3. Los servicios.** Deben existir y estar corriendo:

- Servicio **GRE-Apache**
- Servicio **GRE-MySQL**
- Tarea programada **GRE-ApiGRE**

---

## 8. Cuando algo falla

### Lo primero: el instalador no deja el sistema a medias

- Si la instalación falla, no llega a tocar el servidor.
- Si la actualización o la adopción fallan, **revierten solas** y dejan el
  sistema como estaba. El mensaje final lo dice:

| Mensaje | Qué significa |
|---|---|
| `REVERTIDO` | Falló, pero el sistema quedó como estaba. No hay nada que arreglar. |
| `NO SE PUDO REVERTIR` | Falló la reversión. **Llame a desarrollo.** El mensaje incluye la ruta del respaldo, que está intacto. |

### Dónde están los rastros

| Qué | Dónde |
|---|---|
| Log de la instalación | `C:\DBPeru\GRE\logs\instalacion-<fecha>.log` |
| Log de ApiGRE | `C:\DBPeru\GRE\logs\api-gre.log` y `api-gre-consola.log` |
| Respaldos de actualización | `C:\DBPeru\GRE\respaldos\<fecha>` |
| Respaldos de adopción | `C:\DBPeru-respaldos\<fecha>` |
| Usuario y clave inicial | `C:\DBPeru\GRE\LEEME-INSTALACION.txt` |
| Clave de root de MySQL | `C:\DBPeru\GRE\config\secretos.json` |

### Qué mandar a desarrollo

Si tiene que escalar un problema, mande siempre estas cuatro cosas. Sin ellas se
pierde tiempo preguntando:

1. El **log de la instalación** completo, no una captura de pantalla.
2. El **`VERSION.txt`** del paquete que usó.
3. Lo que devuelve **`/api/v1/health`**.
4. La **versión de Windows** del servidor y si es de 32 o 64 bits.

---

## 9. Problemas conocidos

| Síntoma | Causa | Qué hacer |
|---|---|---|
| Se descomprime y no aparece nada | Se usó `Expand-Archive` | Clic derecho → Extraer todo, o `tar -xf` |
| "El puerto 80 ya lo usa otro programa" | Laragon, XAMPP o IIS | Detenerlo, o instalar en otro puerto (sección 11) |
| "Ejecute PowerShell como Administrador" | Se abrió una consola normal | Clic derecho en PowerShell → Ejecutar como administrador. Con los `.cmd` no pasa: piden el permiso solos |
| "La ejecución de scripts está deshabilitada" | Directiva de Windows, es la de fábrica | Use los `.cmd` con doble clic, que ya lo resuelven |
| Búsqueda de proveedor por razón social vacía | Falta un procedimiento en el ERP | Ver `/api/v1/health`. El instalador lo crea solo si el usuario del ERP tiene permiso |
| Búsquedas de artículos o clientes vacías | Idem | Idem |
| ApiGRE no responde | Java caído o SQL Server inalcanzable | `C:\DBPeru\GRE\logs\api-gre.log` |
| "Existe una base MySQL pero falta secretos.json" | Se borró ese archivo | Restaurarlo de un respaldo. **Sin él no se conoce la clave de root** |
| El cliente dice que perdió guías tras adoptar | La adopción verifica que no falte ninguna y revierte si falla | Revise el log; el respaldo está en `C:\DBPeru-respaldos\<fecha>` |

---

## 10. Garantías que puede darle al cliente

Son ciertas y conviene decirlas, porque es lo que más preocupa:

- **Nada de esto emite documentos a SUNAT.** Ni la instalación, ni la
  actualización, ni la adopción llaman al facturador. Las guías se envían solo
  cuando una persona lo pide desde la pantalla.
- **Antes de tocar nada se respalda**, y si algo falla se revierte solo.
- **La adopción cuenta las guías antes y después**, y si el número no coincide,
  revierte.
- **La adopción no detiene el servidor web del cliente** ni mueve su
  instalación de sitio.

---

## 11. Preguntas rápidas

**¿Puedo correr el instalador dos veces?**
Sí. Repara lo que falte y no duplica nada.

**¿Y si el cliente ya tiene datos y corro INSTALAR.cmd por error?**
Sus datos no corren peligro, pero el resultado no sirve. Hay dos posibilidades:

- **El sistema viejo estaba encendido.** El instalador ve los puertos 80 o 3306
  ocupados y se detiene con *"El puerto … ya lo usa otro programa"*, **antes de
  instalar nada**. No pasó nada: cierre y use `ADOPTAR.cmd`.
- **El sistema viejo estaba apagado.** El instalador termina y deja un sistema
  **nuevo y vacío** en `C:\DBPeru\GRE`, con su propio MySQL. Las guías del
  cliente siguen intactas en el sistema viejo, pero el cliente entraría a uno
  vacío y creería que las perdió. Avise a desarrollo antes de seguir: hay que
  decidir cuál de los dos queda.

El camino correcto para un cliente con datos es siempre `ADOPTAR.cmd`.

**¿Hace falta internet en el servidor?**
No. El paquete trae todo dentro.

**¿Dónde queda instalado?**
En `C:\DBPeru\GRE`. Todo dentro de esa carpeta.

**¿Puedo instalar en otro puerto?**
Sí. Desde PowerShell abierto **como administrador**, en la carpeta del paquete:
`powershell -ExecutionPolicy Bypass -File .\instalar.ps1 -PuertoWeb 8080`

**¿Cómo sé qué versión tiene un cliente?**
El archivo `VERSION.txt` de la carpeta del paquete con que se instaló, o
pregunte a desarrollo con la fecha de instalación del log.
