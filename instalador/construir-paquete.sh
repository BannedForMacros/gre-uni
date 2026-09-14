#!/usr/bin/env bash
# Arma el paquete de instalacion on-premise:  dist/dbperu-guias-<version>.zip
#
#   instalador/construir-paquete.sh            -> version AAAA.MM.1
#   instalador/construir-paquete.sh 2026.09.2
#
# Lleva: la aplicacion del commit actual (HEAD, sin dependencias de desarrollo),
# ApiGRE compilada, los runtimes de runtime.lock verificados por SHA-256, los
# scripts de instalacion y los procedimientos de SQL Server.
#
# Variables opcionales:
#   API_GRE        ruta del repo api-gre        (por defecto ../api-gre)
#   CACHE_RUNTIME  cache de binarios            (por defecto ~/.cache/dbperu-guias-runtime)
#   PHP_BIN        PHP 7.4-8.2 para composer    (por defecto php@8.1 de Homebrew o php)
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
API_GRE="${API_GRE:-$RAIZ/../api-gre}"
CACHE="${CACHE_RUNTIME:-$HOME/.cache/dbperu-guias-runtime}"
# El numero de entrega se calcula mirando lo ya construido. Antes valia
# siempre .1 salvo que se pasara a mano, asi que un paquete nuevo podia
# llamarse 2026.09.1 teniendo al lado un 2026.09.6 anterior: en el cliente,
# eso se instala mal y nadie se entera hasta despues.
if [ -n "${1:-}" ]; then
  VERSION="$1"
else
  MES="$(date +%Y.%m)"
  ULTIMO="$(ls -1 "$RAIZ/dist" 2>/dev/null | sed -n "s/^dbperu-guias-${MES}\\.\\([0-9][0-9]*\\)\\.zip$/\\1/p" | sort -n | tail -1)"
  VERSION="$MES.$(( ${ULTIMO:-0} + 1 ))"
fi
PHP_BIN="${PHP_BIN:-$( [ -x /opt/homebrew/opt/php@8.1/bin/php ] && echo /opt/homebrew/opt/php@8.1/bin/php || command -v php )}"
NOMBRE="dbperu-guias-$VERSION"
DIST="$RAIZ/dist/$NOMBRE"

paso() { printf '\n== %s\n' "$1"; }

paso "Runtimes (runtime.lock)"
mkdir -p "$CACHE"
rm -rf "$DIST"
mkdir -p "$DIST/runtime"
while read -r nombre tipo sha url; do
  [ -z "${nombre:-}" ] && continue
  case "$nombre" in \#*) continue ;; esac
  archivo="$CACHE/$nombre"
  if [ ! -f "$archivo" ]; then
    echo "  descargando $nombre"
    curl -fL --retry 3 -A "Mozilla/5.0 (dbperu-guias)" -o "$archivo.part" "$url"
    mv "$archivo.part" "$archivo"
  fi
  real="$(shasum -a 256 "$archivo" | cut -d' ' -f1)"
  if [ "$real" != "$sha" ]; then
    echo "  SHA-256 NO COINCIDE para $nombre"
    echo "    esperado $sha"
    echo "    obtenido $real"
    exit 1
  fi
  [ "$tipo" = "runtime" ] && cp "$archivo" "$DIST/runtime/"
  echo "  ok $nombre"
done < "$RAIZ/instalador/runtime.lock"

paso "Aplicacion (HEAD $(git -C "$RAIZ" rev-parse --short HEAD))"
if ! git -C "$RAIZ" diff --quiet HEAD --; then
  echo "  AVISO: hay cambios sin commit. El paquete usa HEAD, no la copia de trabajo."
fi
mkdir -p "$DIST/app"
git -C "$RAIZ" archive HEAD | tar -x -C "$DIST/app"
(
  cd "$DIST/app"
  rm -rf tests docker docker-compose.yml dev.sh phpunit.xml webpack.mix.js package.json package-lock.json instalador dist
  "$PHP_BIN" "$CACHE/composer.phar" install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet
)
echo "  ok ($(du -sh "$DIST/app" | cut -f1))"

paso "ApiGRE ($API_GRE)"
( cd "$API_GRE" && mvn -q -DskipTests package )
cp "$API_GRE/target/api-gre.jar" "$DIST/api-gre.jar"
echo "  ok api-gre.jar (HEAD $(git -C "$API_GRE" rev-parse --short HEAD))"

paso "Scripts, SQL y documentacion"
# Los .cmd son la puerta de entrada: el cliente instala con doble clic y el
# script pide permisos de administrador solo. datos.txt.ejemplo sirve para
# dejar todo preparado y que no pregunte nada.
# Todos los .ps1 y .cmd, sin lista a mano: enumerarlos uno por uno ya dejo
# fuera del paquete un archivo nuevo, y eso no falla al construir sino en el
# servidor del cliente.
cp "$RAIZ"/instalador/*.ps1 "$RAIZ"/instalador/*.cmd "$RAIZ/instalador/datos.txt.ejemplo" "$DIST/"
# construir-paquete.sh no va dentro del paquete: es de aqui.
rm -f "$DIST/construir-paquete.sh"
cp -R "$RAIZ/instalador/sql" "$DIST/sql"
[ -f "$RAIZ/docs/INSTALACION.md" ] && cp "$RAIZ/docs/INSTALACION.md" "$DIST/LEEME-INSTALACION.md"
# El manual de adopcion viaja con el paquete: quien adopta a un cliente viejo
# lo hace desde el mismo zip, y ahi es donde lo va a buscar.
[ -f "$RAIZ/docs/ADOPCION.md" ] && cp "$RAIZ/docs/ADOPCION.md" "$DIST/LEEME-ADOPCION.md"
# La guia de soporte va primero: es lo que abre quien recibe el zip, y desde ahi
# se llega a los demas. COMPATIBILIDAD viaja tambien, porque la guia la cita al
# hablar de los requisitos del servidor y el tecnico esta donde el cliente.
[ -f "$RAIZ/docs/SOPORTE.md" ] && cp "$RAIZ/docs/SOPORTE.md" "$DIST/LEEME-PRIMERO.md"
[ -f "$RAIZ/docs/COMPATIBILIDAD.md" ] && cp "$RAIZ/docs/COMPATIBILIDAD.md" "$DIST/LEEME-COMPATIBILIDAD.md"
{
  echo "paquete   $VERSION"
  echo "gre-uni   $(git -C "$RAIZ" rev-parse HEAD)"
  echo "api-gre   $(git -C "$API_GRE" rev-parse HEAD)"
  echo "armado    $(date -u +%Y-%m-%dT%H:%M:%SZ)"
} > "$DIST/VERSION.txt"

paso "Zip"
( cd "$RAIZ/dist" && rm -f "$NOMBRE.zip" && zip -qr "$NOMBRE.zip" "$NOMBRE" )
echo "  $RAIZ/dist/$NOMBRE.zip ($(du -h "$RAIZ/dist/$NOMBRE.zip" | cut -f1))"
