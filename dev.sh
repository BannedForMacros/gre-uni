#!/usr/bin/env bash
#
# Arranca el proyecto para desarrollo.
#
# POR QUE ESTE SCRIPT Y NO "php artisan serve" A SECAS
#
# artisan serve levanta el servidor que trae PHP, y ese servidor atiende UNA
# peticion a la vez. Al abrir un PDF, el navegador abre conexiones extra para
# su visor; con un solo hilo eso se bloquea: la peticion del PDF no termina y,
# como no hay quien atienda otra, la aplicacion ENTERA se queda colgada. El
# sintoma es que tras abrir un PDF, recargar cualquier pantalla tarda o no
# responde.
#
# Medido en este proyecto: con un hilo, el PDF no terminaba en 45 s y dejaba el
# servidor sin responder; con cuatro, 174 ms la primera vez y 95 ms despues.
#
# PHP_CLI_SERVER_WORKERS solo funciona en Linux y macOS. En Windows se ignora,
# pero alli no hace falta: el despliegue en el cliente va sobre Apache, que ya
# atiende varias peticiones a la vez.
#
set -e
cd "$(dirname "$0")"

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

echo "Arrancando con ${PHP_CLI_SERVER_WORKERS} procesos..."
exec php artisan serve --host=127.0.0.1 --port="${1:-8000}"
