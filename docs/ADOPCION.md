# Adopción — clientes que ya tienen el sistema

Para los clientes que instalaron Guías Electrónicas antes de que existiera
`instalar.ps1`. Son alrededor de cien y cada uno está montado como quiso quien
lo instaló: Laragon, XAMPP, WampServer, IIS, un Apache suelto; MySQL o MariaDB
como servicio en el puerto que sea; y la aplicación en `C:\laragon\www\algo`, en
`D:\wamp64\www`, en `C:\inetpub\wwwroot` o en una carpeta cualquiera.

**ADOPTAR.cmd** los pone al día sin moverlos de sitio y sin cambiarles el
servidor web ni el motor de base de datos. Después de correrlo, ese cliente
recibe actualizaciones con `ACTUALIZAR.cmd` como cualquier otro.

Esto reemplaza al procedimiento manual que estaba en la sección 7 de
[INSTALACION.md](INSTALACION.md), que asumía Laragon y exigía instalar todo de
nuevo, importar el dump a mano y copiar el logo.

## 1. El problema que resuelve

Estos clientes tienen la base creada importando un dump, no corriendo
migraciones. Su tabla `migrations` está vacía o incompleta, así que
`artisan migrate` intenta crear tablas que ya existen y aborta:

```
SQLSTATE[42S01]: Table 'users' already exists
```

No se pierden datos, pero la actualización no entra nunca. `gre:baseline` marca
como ya aplicadas las migraciones cuyo efecto ya está en la base —sin ejecutar
una sola sentencia DDL— y entonces `migrate` corre únicamente lo nuevo.

`ADOPTAR.cmd` es todo ese camino hecho solo: descubrir dónde está todo,
comprobarlo, respaldar, actualizar y verificar.

## 2. No asume nada

Ni la ruta, ni el puerto 3306, ni que exista Laragon, ni que haya un `mysql.exe`
en el PATH. Todo se descubre y todo se comprueba de verdad antes de tocar nada:

- **El motor de base de datos.** Busca servicios de Windows cuyo binario sea
  `mysqld` o `mariadbd`, procesos sueltos (así los arrancan Laragon y XAMPP desde
  su panel) y puertos habituales. A cada candidato le habla el protocolo de MySQL
  y se queda con los que contestan de verdad. **El puerto sale del `my.ini` del
  servicio, no se da por hecho que sea 3306.**
- **Las instalaciones del GRE.** Recorre discos y carpetas web habituales
  buscando un `artisan` con un `.env` que tenga `APP_KEY`, y que además parezca
  del GRE. Las muestra en una lista numerada con su base y su número de guías.
  Si no aparece ninguna, ofrece buscar en todo el disco o escribir la ruta a mano.
- **El PHP.** Busca uno de 7.4 en adelante que traiga `pdo_mysql`, junto a la
  aplicación, en el PATH y en las carpetas de cada stack.

## 3. La conexión se prueba de verdad

Igual que el instalador hace con el SQL Server del ERP. Comprobar que el puerto
responde no dice nada: el usuario puede estar mal, la clave puede estar mal o la
base puede no existir. Se distinguen los tres casos:

| Mensaje | Qué pasa |
|---|---|
| `no se llega al servidor` | Puerto, servicio detenido o firewall |
| `el usuario o la clave no son correctos` | Credenciales |
| `la base de datos no existe` | Nombre de base equivocado |

Las credenciales salen del `.env` del cliente. Si no sirven, pregunta, y muestra
la lista de bases que existen de verdad para elegir por número.

**Sobre el `.env`:** el instalador escribe la clave entre comillas simples
(`DB_PASSWORD='...'`). Un lector ingenuo que solo quita comillas dobles manda los
apóstrofes dentro de la clave y MySQL responde *Access denied (using password:
YES)*, lo que hace pensar que la credencial del cliente está mal cuando está
bien. La lectura de `adoptar.ps1` tolera lo mismo que phpdotenv: valores sin
comillas, con dobles, con simples, con espacios alrededor del `=`, y claves que
contienen `#` (que dentro de comillas no abre comentario).

Si hay dos motores, `mysql.exe` se toma de la carpeta del motor que se va a usar,
para que sea de la misma versión.

## 4. Cómo se corre

1. Descomprima el paquete **nuevo** en el servidor del cliente.
2. Doble clic en **ADOPTAR.cmd**.
3. Acepte el aviso de Windows que pide permisos de administrador.
4. Elija por número la instalación de la lista y confirme.

Antes de confirmar, **asegúrese de que nadie esté usando el sistema.**

### Ver qué haría, sin tocar nada

```powershell
powershell -ExecutionPolicy Bypass -File .\adoptar.ps1 -SoloRevisar
```

Descubre, prueba la conexión, cuenta las guías y muestra el resumen. No escribe
absolutamente nada. Es lo que conviene correr la primera vez en un cliente que
no se conoce.

## 5. Qué hace, en orden

1. Busca el motor de base de datos y un cliente `mysql` con que hablarle.
2. Busca las instalaciones del GRE y las lista con su base y su número de guías.
3. Prueba la conexión de verdad y confirma que la base tiene `guia_ingresos` y
   `guia_salidas`. Si no las tiene, se niega: no es una base del GRE.
4. Dice quién ocupa el puerto web y el de MySQL, sea el programa que sea, y con
   qué PHP va a correr `artisan`.
5. **Respalda**: volcado completo de la base y copia de la carpeta de la
   aplicación. La ruta se imprime en pantalla y queda en el log.
6. Copia el código nuevo **conservando el `.env` del cliente** —su `APP_KEY`, sus
   credenciales y sus parámetros— y solo le **agrega** las claves nuevas que
   falten, al final y con un comentario con la fecha. No reescribe ninguna línea
   existente.
7. `gre:baseline --dry-run` (lo muestra en pantalla), `gre:baseline`,
   `migrate --force` y `gre:inicializar`.
8. Verifica.

El respaldo queda en `<unidad de la aplicación>\DBPeru-respaldos\<fecha>`, en el
mismo disco que la aplicación, con `base.sql`, la carpeta `app` y `adopcion.log`.

## 6. Qué verifica al final

- **Que no falte ni sobre una guía.** Cuenta las de ingreso y las de salida antes
  de empezar y las vuelve a contar al terminar. Si el número cambió, revierte.
- **Que la web responda.**
- **Que el login funcione.** Sin usar las credenciales de nadie: pide la pantalla
  de login, comprueba que trae el campo de usuario —ahí falló una entrega, el
  formulario mandaba `username` y Laravel validaba `email`, así que nadie podía
  entrar— y manda un intento con un usuario inventado. Si responde "credenciales
  incorrectas" en vez de un error 500, entonces el formulario, la sesión, el
  token CSRF, la consulta a la base y el hash de claves funcionan de punta a
  punta. No toca ninguna cuenta real.

La web y el login se miden **antes** de empezar. Si ya estaban caídos, que no
respondan después no es culpa de la adopción y no dispara una reversión: lo avisa
y dice quién ocupa el puerto.

## 7. Si algo falla, revierte solo

Restaura la carpeta de la aplicación y la base desde el respaldo recién hecho, y
deja el sistema como estaba. El mensaje final dice:

- `ADOPCION_OK` con la ruta del respaldo.
- `ADOPCION_FALLIDA` seguido de `REVERTIDO`.
- `NO SE PUDO REVERTIR`, con las rutas del respaldo intacto para hacerlo a mano.

Si falla antes de tocar nada, lo dice: *no se alcanzó a tocar nada.*

## 8. Lo que NO hace, a propósito

- **No emite nada a SUNAT y no llama al facturador.** Nunca.
- **No detiene el servidor web del cliente.** No sabemos cómo volver a
  levantarlo —en Laragon se arranca desde su panel— y dejarlo caído sería peor
  que la actualización. Por eso se pide que nadie esté usando el sistema.
- **No corre `config:cache`.** Si el código del cliente llama a `env()` fuera de
  `config/`, cachear la configuración le apagaría esos valores. Solo se limpia
  con `config:clear`, `cache:clear` y `view:clear`.
- **No mueve la instalación** ni cambia su servidor web o su motor de base.

## 9. Para técnicos

```powershell
powershell -ExecutionPolicy Bypass -File .\adoptar.ps1 `
  -Ruta "D:\wamp64\www\guias" -Servidor 127.0.0.1 -Puerto 3307 `
  -Base guia_electronica -Usuario gre -Clave "..." `
  -Php "C:\laragon\bin\php\php-7.4.33\php.exe" -Url "http://127.0.0.1:8080" `
  -Respaldos "E:\respaldos" -SinPreguntas
```

Todos los parámetros son opcionales: lo que no se indica, se descubre o se
pregunta. Con `-SinPreguntas` no pregunta nada y se detiene si falta un dato.
`-SoloRevisar` no escribe nada.

## 10. Problemas conocidos

| Síntoma | Causa | Qué hacer |
|---|---|---|
| "No respondió ningún MySQL ni MariaDB" | El servicio está detenido | Arránquelo desde el panel de Laragon/XAMPP o desde Servicios de Windows |
| "No se encontró mysqldump.exe" | El stack del cliente no trae las herramientas de línea de comandos | Instálelas; sin respaldo no se adopta nada |
| "No se pudo conectar: el usuario o la clave no son correctos" | El `.env` tiene credenciales viejas | Escríbalas a mano cuando las pida |
| "La base no tiene las tablas guia_ingresos y guia_salidas" | Se eligió otra base del mismo servidor | Vuelva a correr y elija la correcta de la lista |
| "No se encontró un PHP 7.4 o superior con pdo_mysql" | El PHP del cliente es muy viejo, o no se halló | Indíquelo con `-Php C:\ruta\php.exe` |
| "este servidor exige autenticación cifrada" | MySQL 8 con `caching_sha2_password` y sin cliente instalado | Instale el cliente `mysql` de esa misma versión |
| No aparece la instalación en la lista | Está en una carpeta poco habitual | Conteste que sí a buscar en todo el disco, o escriba la ruta a mano |

## 11. Estado de la validación

**Lo que está probado de verdad:** `gre:baseline`, con
`tests/Feature/GreBaselineTest.php` (7 pruebas). Cubre base importada con
`migrations` vacía, `migrations` a medias, base ya migrada, idempotencia,
esquema ajeno rechazado sin escribir, base vacía y `--dry-run`. Se comprueba
además que no ejecuta DDL y que no pierde ni una guía cuando después corre
`migrate`.

**Lo que NO se ha probado todavía:** `adoptar.ps1` y `mysql.ps1` no se han
ejecutado en un Windows real. Están escritos, pero ni el descubrimiento de
motores e instalaciones, ni el respaldo, ni la reversión, ni el diálogo del
protocolo de MySQL se han visto funcionar contra un cliente de verdad. Hasta
que eso ocurra, **córralo primero con `-SoloRevisar`** en cada cliente, y
guarde el respaldo antes de confirmar.
