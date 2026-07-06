#!/bin/bash
# Step 3: Start MariaDB and load old dump
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DUMP_FILE="$SCRIPT_DIR/ninegate.sql"
TMP_MARIADB="ninegate-migration-mariadb"

echo "=== [3/7] Load old dump into MariaDB ==="

[ ! -f "$DUMP_FILE" ] && echo "ERROR: $DUMP_FILE not found" && exit 1

docker rm -f "$TMP_MARIADB" 2>/dev/null || true
docker run -d --name "$TMP_MARIADB" --network ninegate_default \
    -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=ninegate mariadb:10.11

echo "Waiting for MariaDB..."
sleep 20

sed 's/;/;\n/g' "$DUMP_FILE" > /tmp/ninegate_formatted.sql
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate < /tmp/ninegate_formatted.sql 2>/dev/null || true

USER_COUNT=$(docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT COUNT(*) FROM user;" 2>/dev/null)
GROUP_COUNT=$(docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT COUNT(*) FROM groupe;" 2>/dev/null)
ITEM_COUNT=$(docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT COUNT(*) FROM item;" 2>/dev/null)

echo "✓ Old dump loaded: $USER_COUNT users, $GROUP_COUNT groups, $ITEM_COUNT items"
echo "  MariaDB container: $TMP_MARIADB (kept for next steps)"
