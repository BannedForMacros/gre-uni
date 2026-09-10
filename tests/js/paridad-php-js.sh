#!/usr/bin/env bash
# Verifica que el calculo de totales de PHP y el de JavaScript den el MISMO
# numero. Si divergen, el total que ve el usuario no cuadra con el que se
# guarda. Ya paso una vez: un centimo de diferencia por el orden del redondeo.
#
# Uso:  bash tests/js/paridad-php-js.sh
set -e
cd "$(dirname "$0")/../.."
# -d error_reporting=... : en PHP 8.4 las dependencias de Laravel 8 emiten
# avisos de obsolescencia por stdout y ensucian el JSON que se compara. El
# calculo no cambia; solo se callan los avisos para poder leer la salida.
PHP="${PHP_BIN:-php} -d error_reporting=E_ALL&~E_DEPRECATED&~E_USER_DEPRECATED -d display_errors=stderr"
CASOS=tests/js/casos-paridad.json

$PHP -r '
require "vendor/autoload.php";
use App\Domain\Guia\Services\CalculadoraGuia;
use App\Domain\Guia\ValueObjects\LineaGuia;
use App\Domain\Shared\ValueObjects\Igv;
$casos = json_decode(file_get_contents($argv[1]), true);
$out = [];
foreach ($casos as $caso) {
    $lineas = []; $i = 1;
    foreach ($caso["l"] as $l) {
        $desc = round($l["c"] * $l["p"] * ($l["d"]/100), 2);
        $lineas[] = new LineaGuia($i++, "X", "ART", $l["c"], $l["p"], 9, $l["a"], $desc);
    }
    $t = (new CalculadoraGuia(new Igv($caso["t"])))->totales($lineas);
    $out[$caso["n"]] = [round($t->valorVenta(),2), round($t->igv(),2), round($t->total(),2)];
}
echo json_encode($out);' "$CASOS" > /tmp/gre_php.json

node -e '
global.window = global;
require("./public/js/gre/guia-detalle.js");
const casos = require(process.argv[1]);
const out = {};
for (const caso of casos) {
  const lineas = caso.l.map(l => ({cantidad:l.c,precioSinIgv:l.p,porcentajeDescuento:l.d,afectoIgv:l.a,bonificacion:false}));
  const c = window.greDetalleGuia({ lineas, tasaIgv: caso.t });
  out[caso.n] = [c.valorVenta, c.montoIgv, c.totalVenta];
}
console.log(JSON.stringify(out));' "$PWD/$CASOS" > /tmp/gre_js.json

node -e '
const a = require("/tmp/gre_php.json"), b = require("/tmp/gre_js.json");
let fallos = 0;
for (const k of Object.keys(a)) {
  const ok = JSON.stringify(a[k]) === JSON.stringify(b[k]);
  if (!ok) { fallos++; console.log("  DIVERGEN  " + k + "  PHP=" + JSON.stringify(a[k]) + "  JS=" + JSON.stringify(b[k])); }
  else console.log("  OK        " + k);
}
console.log(fallos === 0 ? "\nPHP y JS coinciden (" + Object.keys(a).length + " casos)" : "\n" + fallos + " CASOS DIVERGEN");
process.exit(fallos === 0 ? 0 : 1);'
