# Guías de Remisión Electrónicas

Registro y envío de guías de remisión (ingreso y salida) al DataMart del ERP y,
cuando corresponde, a SUNAT a través del facturador.

Se instala **en el servidor del propio cliente**, junto a su base de datos. No
hay nube: todo —Laravel, MySQL, la ApiGRE y el SQL Server del ERP— vive en esa
máquina. Eso condiciona casi todas las decisiones del proyecto:

- **Sin paso de compilación en el front.** Alpine.js va vendorizado en
  `public/js/vendor/`. No hay npm que ejecutar en el servidor del cliente.
- **Sin recursos remotos.** Ni fuentes de Google ni CDNs: muchos clientes no
  tienen salida a internet, y una hoja de estilo remota bloquea el render de la
  pantalla hasta que la petición expira.
- **PHP 7.4 como mínimo.** Es la última versión que soporta Windows 7, que es el
  suelo del parque instalado.

---

## Arrancar en desarrollo

```bash
./dev.sh          # puerto 8000
./dev.sh 8080     # otro puerto
```

**Usa `dev.sh`, no `php artisan serve` a secas.** `artisan serve` levanta el
servidor que trae PHP, que atiende **una petición a la vez**. Al abrir un PDF el
navegador abre conexiones extra para su visor y, con un solo hilo, la petición
se bloquea: el PDF no termina y la aplicación entera deja de responder. El
síntoma es que después de abrir un PDF, recargar cualquier pantalla se queda
colgada.

Medido en este proyecto: con un hilo el PDF no terminaba en 45 s y dejaba el
servidor sin responder; con cuatro, **174 ms** la primera vez y **95 ms** las
siguientes.

`PHP_CLI_SERVER_WORKERS` solo funciona en Linux y macOS. En Windows se ignora,
pero allí no hace falta: el despliegue en el cliente va sobre Apache, que ya
atiende varias peticiones a la vez.

La ApiGRE tiene que estar levantada aparte (ver su propio README).

---

## Pruebas

```bash
php vendor/bin/phpunit          # PHP: dominio y controladores
node --test tests/js/           # JavaScript de las pantallas
bash tests/js/paridad-php-js.sh # que PHP y JS calculen el MISMO total
```

Las pruebas de PHP usan `RefreshDatabase`, que **vacía la base entera**. Por eso
`phpunit.xml` apunta a `gre_uni_test`, separada de la de trabajo. No cambies eso
sin saber lo que haces: apuntarlo a la base de un cliente le borra los datos.

La prueba de paridad existe porque ya pasó una vez: PHP redondeaba el IGV por
línea y luego sumaba, y el JavaScript sumaba primero y redondeaba al final. Un
céntimo de diferencia entre lo que el usuario veía y lo que se guardaba.

---

## Configuración

Lo que cambia entre clientes vive en la tabla `parametros` y se administra desde
**Configuraciones → Configuración de Empresa**: razón social, RUC, dirección,
logo, y si la empresa trabaja con productos consignados.

En el `.env` van la conexión a MySQL y la dirección de la ApiGRE:

```
GRE_API_URL=http://localhost:8181     # RAIZ, sin ruta: de ahi salen /GREDMK y /api/v1
```

`GRE_API_URL` es la raíz y nada más. Las dos bases se derivan de ella
(`config('gre.api.legacy')` y `config('gre.api.url')`); escribir la ruta en el
`.env` fue un error que dejaba una instalación nueva apuntando a un sitio que no
existe.

### Datos de demostración

```bash
php artisan db:seed --class=GuiaSalidaDemoSeeder
```

Se niega a correr si `APP_ENV` es production o si el nombre de la base no
contiene «demo».

---

## Mantenimiento

```bash
php artisan gre:baseline          # marca como aplicadas las migraciones previas
php artisan gre:guias-huerfanas   # lista guias con cabecera pero sin detalle
```

`gre:guias-huerfanas` solo informa; con `--purgar` borra, preguntando antes.
Existen porque hasta hace poco la cabecera se escribía antes que el detalle y
sin transacción: si una línea fallaba, quedaba una guía con total y cero líneas
que el DataMart rechazaba después con «Documento incompleto». Ya no pueden
nacer, pero en instalaciones antiguas las hay.

---

## Despliegue

El objetivo es Windows 7 SP1 x64 / Windows Server 2008 R2 como mínimo, sobre
Apache 2.4 y PHP 7.4.33. El detalle del paquete está en
`apidmk-v2/deploy/STACK.md`.
