#!/bin/bash
# Step 7: Cleanup - orphan icons, sequences, stop MariaDB
set -e
TMP_MARIADB="ninegate-migration-mariadb"

echo "=== [7/8] Cleanup ==="

# Clean orphan icon references
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
UPDATE item SET icon_id = NULL WHERE icon_id IS NOT NULL AND icon_id NOT IN (SELECT id FROM icon);
UPDATE bookmark SET icon_id = NULL WHERE icon_id IS NOT NULL AND icon_id NOT IN (SELECT id FROM icon);
" 2>&1 > /dev/null

# Refresh all sequences
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
SELECT setval(pg_get_serial_sequence('app_user', 'id'), COALESCE((SELECT MAX(id) FROM app_user), 1));
SELECT setval(pg_get_serial_sequence('app_group', 'id'), COALESCE((SELECT MAX(id) FROM app_group), 1));
SELECT setval(pg_get_serial_sequence('user_group', 'id'), COALESCE((SELECT MAX(id) FROM user_group), 1));
SELECT setval(pg_get_serial_sequence('icon', 'id'), COALESCE((SELECT MAX(id) FROM icon), 1));
SELECT setval(pg_get_serial_sequence('widget', 'id'), COALESCE((SELECT MAX(id) FROM widget), 1));
SELECT setval(pg_get_serial_sequence('item', 'id'), COALESCE((SELECT MAX(id) FROM item), 1));
SELECT setval(pg_get_serial_sequence('item_category', 'id'), COALESCE((SELECT MAX(id) FROM item_category), 1));
SELECT setval(pg_get_serial_sequence('blog', 'id'), COALESCE((SELECT MAX(id) FROM blog), 1));
SELECT setval(pg_get_serial_sequence('bookmark', 'id'), COALESCE((SELECT MAX(id) FROM bookmark), 1));
" 2>&1 > /dev/null

# Stop MariaDB
docker rm -f "$TMP_MARIADB" 2>/dev/null
rm -f /tmp/ninegate_formatted.sql /tmp/migrate_*.sql /tmp/old_icons.txt

echo "✓ Cleanup done"
