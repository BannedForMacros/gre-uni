#!/usr/bin/env bash
#
# Arranca el proyecto para desarrollo.
#
# HACE DOS COSAS QUE "php artisan serve" A SECAS NO HACE
#
# 1. Levanta varios procesos.
#
#    artisan serve usa el servidor que trae PHP, y ese servidor atiende UNA
#    peticion a la vez. Al abrir un PDF el navegador abre conexiones extra para
#    su visor; con un solo hilo eso se bloquea: la peticion del PDF no termina
#    y, como no queda nadie para atender otra, la aplicacion ENTERA deja de
#    responder. El sintoma es que tras abrir un PDF, recargar cualquier
#    pantalla tarda o se queda colgada.
#
#    Medido aqui: con un hilo el PDF no terminaba en 45 s y dejaba el servidor
#    sin responder; con cuatro, 174 ms la primera vez y 95 ms despues.
#
#    PHP_CLI_SERVER_WORKERS solo funciona en Linux y macOS. En Windows se
#    ignora, pero alli no hace falta: el despliegue en el cliente va sobre
#    Apache, que ya atiende varias peticiones a la vez.
#
# 2. Elige una version de PHP que el proyecto soporte.
#
#    composer.json pide ^7.4|^8.0 y el servidor del cliente corre 7.4.33. Con un
#    PHP mas nuevo la aplicacion arranca, pero Laravel 8 y sus dependencias
#    sueltan avisos de obsolescencia en cada comando y en cada peticion: 104
#    avisos con PHP 8.5 frente a 0 con 8.1. Eso ensucia la salida y llega a
#    romper lo que lee JSON de un comando.
#
set -e
cd "$(dirname "$0")"

# --- Que PHP usar ------------------------------------------------------------
elegir_php() {
    # Si el PHP del sistema ya sirve, se usa ese.
    if command -v php >/dev/null 2>&1; then
        local v
        v=$(php -r 'echo PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION;' 2>/dev/null || echo 0)
        if [ "$v" -ge 704 ] && [ "$v" -le 802 ]; then
            command -v php
            return
        fi
    fi

    # Si no, se busca una instalada al lado. Mas nueva primero dentro del rango.
    local candidato
    for candidato in \
        /opt/homebrew/opt/php@8.1/bin/php \
        /opt/homebrew/opt/php@8.0/bin/php \
        /opt/homebrew/opt/php@7.4/bin/php \
        /usr/local/opt/php@8.1/bin/php \
        /usr/local/opt/php@8.0/bin/php \
        /usr/local/opt/php@7.4/bin/php
    do
        [ -x "$candidato" ] && { echo "$candidato"; return; }
    done

    for candidato in /opt/homebrew/Cellar/php@8.1/*/bin/php \
                     /opt/homebrew/Cellar/php@8.0/*/bin/php \
                     /opt/homebrew/Cellar/php@7.4/*/bin/php
    do
        [ -x "$candidato" ] && { echo "$candidato"; return; }
    done

    command -v php
}

PHP_BIN="${PHP_BIN:-$(elegir_php)}"
VERSION=$("$PHP_BIN" -r 'echo PHP_VERSION;')
VERSION_NUM=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION;')

if [ "$VERSION_NUM" -lt 704 ] || [ "$VERSION_NUM" -gt 802 ]; then
    echo "AVISO: PHP ${VERSION}. El proyecto pide 7.4 a 8.2 y el servidor del"
    echo "       cliente corre 7.4.33. Vas a ver avisos de obsolescencia de"
    echo "       Laravel y sus dependencias; no son de este codigo."
    echo
fi

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

echo "PHP ${VERSION} · ${PHP_CLI_SERVER_WORKERS} procesos · http://127.0.0.1:${1:-8000}"
exec "$PHP_BIN" artisan serve --host=127.0.0.1 --port="${1:-8000}"
