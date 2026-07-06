#!/bin/bash
# =============================================================================
# Migration: old ninegate (MariaDB) → new ninegate (PostgreSQL)
# =============================================================================
# Usage: ./misc/migration/migrate.sh
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DUMP_FILE="$SCRIPT_DIR/ninegate.sql"
TMP_MARIADB="ninegate-migration-mariadb"
NETWORK="ninegate_default"
FINAL_SQL="/tmp/migrate_final.sql"

echo "=== Ninegate Migration Tool ==="
echo ""

[ ! -f "$DUMP_FILE" ] && echo "ERROR: $DUMP_FILE not found" && exit 1

# 1. Load fixtures (purge + base data)
echo "[1/5] Loading fixtures..."
docker exec ninegate php bin/console doctrine:fixtures:load --no-interaction 2>&1 | tail -3

# 2. Start MariaDB + load dump
echo "[2/5] Starting MariaDB + loading dump..."
docker rm -f "$TMP_MARIADB" 2>/dev/null || true
docker run -d --name "$TMP_MARIADB" --network "$NETWORK" -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=ninegate mariadb:10.11
sleep 20
sed 's/;/;\n/g' "$DUMP_FILE" > /tmp/ninegate_formatted.sql
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate < /tmp/ninegate_formatted.sql 2>/dev/null || true
echo "  ✓ $(docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e 'SELECT COUNT(*) FROM user;' 2>/dev/null) users loaded"

# 3. Generate INSERT statements
echo "[3/5] Generating INSERTs..."
MYSQL="docker exec -i $TMP_MARIADB mysql -uroot -proot ninegate -N -e"

$MYSQL "SELECT CONCAT('INSERT INTO app_group (id, name, slug, is_open, is_system, type) VALUES (', id, ', ', QUOTE(label), ', ', QUOTE(LOWER(REPLACE(label, ' ', '-'))), ', ', IF(fgopen, 'true', 'false'), ', ', IF(fgall, 'true', 'false'), ', ', QUOTE('Groupe de Travail'), ') ON CONFLICT (id) DO NOTHING;') FROM groupe ORDER BY id;" > /tmp/migrate_groups.sql 2>/dev/null
$MYSQL "SELECT CONCAT('INSERT INTO app_user (id, username, email, password, roles, firstname, lastname, avatar) VALUES (', id, ', ', QUOTE(username), ', ', QUOTE(COALESCE(email, CONCAT(username, '@ninegate.local'))), ', ', QUOTE(password), ', ', QUOTE(IF(role = 'ROLE_ADMIN', '[\"ROLE_ADMIN\"]', IF(role = 'ROLE_MASTER', '[\"ROLE_MASTER\"]', '[\"ROLE_USER\"]'))), ', ', QUOTE(COALESCE(firstname, '')), ', ', QUOTE(COALESCE(lastname, '')), ', NULL) ON CONFLICT (username) DO NOTHING;') FROM user ORDER BY id;" > /tmp/migrate_users.sql 2>/dev/null
$MYSQL "SELECT CONCAT('INSERT INTO user_group (id, user_id, group_id, role) VALUES (', id, ', ', user_id, ', ', group_id, ', ', IF(fgmanager, '''MASTER''', '''USER'''), ') ON CONFLICT DO NOTHING;') FROM usergroupe ORDER BY id;" > /tmp/migrate_usergroups.sql 2>/dev/null
$MYSQL "SELECT CONCAT('INSERT INTO item_category (id, title, sort_order) VALUES (', id, ', ', QUOTE(label), ', ', COALESCE(rowOrder, 0), ') ON CONFLICT (id) DO NOTHING;') FROM itemcategory ORDER BY id;" > /tmp/migrate_itemcategories.sql 2>/dev/null
$MYSQL "SELECT CONCAT('INSERT INTO item (id, title, summary, bgcolor, color, url, new_tab, sort_order, icon_id, category_id) VALUES (', id, ', ', QUOTE(title), ', ', COALESCE(QUOTE(subtitle), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', QUOTE(url), ', ', IF(target = '_blank', 'true', 'false'), ', ', COALESCE(rowOrder, 0), ', ', COALESCE(icon_id, 'NULL'), ', ', category, ') ON CONFLICT (id) DO NOTHING;') FROM item ORDER BY id;" > /tmp/migrate_items.sql 2>/dev/null
$MYSQL "SELECT CONCAT('INSERT INTO item_group (item_id, group_id) VALUES (', item, ', ', groupe, ') ON CONFLICT DO NOTHING;') FROM itemgroupe ORDER BY item, groupe;" > /tmp/migrate_itemgroups.sql 2>/dev/null
$MYSQL "SELECT CONCAT('INSERT INTO blog (id, title, slug, blog_order) VALUES (', id, ', ', QUOTE(name), ', ', QUOTE(LOWER(REPLACE(name, ' ', '-'))), ', 0) ON CONFLICT (id) DO NOTHING;') FROM blog ORDER BY id;" > /tmp/migrate_blogs.sql 2>/dev/null
$MYSQL "SELECT CONCAT('INSERT INTO bookmark (id, title, summary, bgcolor, color, url, new_tab, user_id) VALUES (', id, ', ', QUOTE(title), ', ', COALESCE(QUOTE(subtitle), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', QUOTE(url), ', ', IF(target = '_blank', 'true', 'false'), ', ', COALESCE(user_id, 'NULL'), ') ON CONFLICT (id) DO NOTHING;') FROM bookmark ORDER BY id;" > /tmp/migrate_bookmarks.sql 2>/dev/null

echo "  ✓ Groups: $(wc -l < /tmp/migrate_groups.sql), Users: $(wc -l < /tmp/migrate_users.sql), Memberships: $(wc -l < /tmp/migrate_usergroups.sql)"
echo "  ✓ Items: $(wc -l < /tmp/migrate_items.sql), Blogs: $(wc -l < /tmp/migrate_blogs.sql), Bookmarks: $(wc -l < /tmp/migrate_bookmarks.sql)"

# 4. Assemble + apply
echo "[4/5] Applying to PostgreSQL..."
{
    echo "SET session_replication_role = 'replica';"
    cat /tmp/migrate_groups.sql /tmp/migrate_users.sql /tmp/migrate_usergroups.sql /tmp/migrate_itemcategories.sql /tmp/migrate_items.sql /tmp/migrate_itemgroups.sql /tmp/migrate_blogs.sql /tmp/migrate_bookmarks.sql
    echo "UPDATE app_group SET slug = 'all', is_system = true WHERE name = 'Tout le Monde';"
    echo "SET session_replication_role = 'origin';"
} > "$FINAL_SQL"

sed -i "s/\\\\\\\\'/''/g" "$FINAL_SQL"

docker exec -i ninegate-postgres psql -U user -d ninegate < "$FINAL_SQL" 2>&1 | grep -i "error" | head -3 || true

# Resync sequences
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

# Summary
echo "[5/5] Summary..."
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
SELECT 'app_user' t, COUNT(*) c FROM app_user UNION ALL SELECT 'app_group', COUNT(*) FROM app_group UNION ALL SELECT 'user_group', COUNT(*) FROM user_group UNION ALL SELECT 'icon', COUNT(*) FROM icon UNION ALL SELECT 'widget', COUNT(*) FROM widget UNION ALL SELECT 'item_category', COUNT(*) FROM item_category UNION ALL SELECT 'item', COUNT(*) FROM item UNION ALL SELECT 'item_group', COUNT(*) FROM item_group UNION ALL SELECT 'blog', COUNT(*) FROM blog UNION ALL SELECT 'bookmark', COUNT(*) FROM bookmark ORDER BY t;
"

docker rm -f "$TMP_MARIADB" 2>/dev/null
rm -f /tmp/ninegate_formatted.sql /tmp/migrate_*.sql /tmp/old_icons.txt

echo "=== Migration terminée ==="
