#!/bin/bash
set -eo pipefail

# Se positionner sur la racine du projet
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd ${DIR}
cd ../..
DIR=$(pwd)

# Purge du cache compilé : l'image peut embarquer un cache obsolète (warmup fait
# pendant le build, avant la copie du code). Un bundle retiré dans ce cache fait
# échouer le cache:clear des auto-scripts composer, et le conteneur boucle.
rm -rf var/cache/prod var/cache/dev

bin/console cache:clear
# Doctrine ORM 3 : l'option --complete n'existe plus
bin/console d:s:u --force
bin/console app:init
bin/console assets:install public

# Lancer le worker Messenger en tâche de fond
nohup bin/console messenger:consume async --time-limit=3600 --memory-limit=128M >> var/log/messenger-worker.log 2>&1 &
disown
echo "Worker Messenger lancé (PID: $!)"

# Lancer le scheduler supercronic en tâche de fond
supercronic -quiet -no-reap /crontab &
disown
echo "Supercronic lancé (PID: $!)"

exec $@