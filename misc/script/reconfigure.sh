#!/bin/bash
set -eo pipefail

# Se positionner sur la racine du projet
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd ${DIR}
cd ../..
DIR=$(pwd)

bin/console d:s:u --force --complete
bin/console app:init

# Lancer le worker Messenger en tâche de fond
nohup bin/console messenger:consume async --time-limit=3600 --memory-limit=128M >> var/log/messenger-worker.log 2>&1 &
disown
echo "Worker Messenger lancé (PID: $!)"

exec $@