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
VERSION="${1:-$(date +%Y.%m).1}"
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
cp "$RAIZ/instalador/instalar.ps1" "$RAIZ/instalador/actualizar.ps1" "$RAIZ/instalador/comun.ps1" "$DIST/"
cp -R "$RAIZ/instalador/sql" "$DIST/sql"
[ -f "$RAIZ/docs/INSTALACION.md" ] && cp "$RAIZ/docs/INSTALACION.md" "$DIST/LEEME-INSTALACION.md"
{
  echo "paquete   $VERSION"
  echo "gre-uni   $(git -C "$RAIZ" rev-parse HEAD)"
  echo "api-gre   $(git -C "$API_GRE" rev-parse HEAD)"
  echo "armado    $(date -u +%Y-%m-%dT%H:%M:%SZ)"
} > "$DIST/VERSION.txt"

paso "Zip"
( cd "$RAIZ/dist" && rm -f "$NOMBRE.zip" && zip -qr "$NOMBRE.zip" "$NOMBRE" )
echo "  $RAIZ/dist/$NOMBRE.zip ($(du -h "$RAIZ/dist/$NOMBRE.zip" | cut -f1))"
