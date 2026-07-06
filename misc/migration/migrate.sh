#!/bin/bash
# =============================================================================
# Migration: old ninegate (MariaDB) → new ninegate (PostgreSQL)
# =============================================================================
# Usage: ./misc/migration/migrate.sh
#
# What it does:
# 1. Loads fixtures (base data: config, icons, pages, widgets)
# 2. Purges users + groups
# 3. Migrates users + groups + user_groups from old dump (same IDs)
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DUMP_FILE="$SCRIPT_DIR/ninegate.sql"
TMP_MARIADB="ninegate-migration-mariadb"
NETWORK="ninegate_default"

echo "=== Ninegate Migration Tool ==="
echo ""

if [ ! -f "$DUMP_FILE" ]; then
    echo "ERROR: Dump file not found: $DUMP_FILE"
    exit 1
fi

# Step 1: Load fixtures (base data)
echo "[1/7] Loading fixtures..."
docker exec ninegate php bin/console doctrine:fixtures:load --no-interaction 2>&1 | tail -5
echo "  ✓ Fixtures loaded"

# Step 2: Purge users, groups, and migrated data
echo "[2/7] Purging users, groups, and migrated data..."
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
DELETE FROM user_group;
DELETE FROM bookmark;
DELETE FROM blog_article;
DELETE FROM blog;
DELETE FROM item_group;
DELETE FROM item;
DELETE FROM item_category;
DELETE FROM app_user;
DELETE FROM app_group;
" 2>&1
echo "  ✓ Purged"

# Step 3: Start temporary MariaDB
echo "[3/7] Starting temporary MariaDB..."
docker rm -f "$TMP_MARIADB" 2>/dev/null || true
docker run -d --name "$TMP_MARIADB" --network "$NETWORK" \
    -e MYSQL_ROOT_PASSWORD=root \
    -e MYSQL_DATABASE=ninegate \
    mariadb:10.11
echo "Waiting for MariaDB..."
sleep 20

# Step 4: Load dump
echo "[4/7] Loading old dump..."
sed 's/;/;\n/g' "$DUMP_FILE" > /tmp/ninegate_formatted.sql
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate < /tmp/ninegate_formatted.sql 2>/dev/null || true
USER_COUNT=$(docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT COUNT(*) FROM user;" 2>/dev/null)
GROUP_COUNT=$(docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT COUNT(*) FROM groupe;" 2>/dev/null)
echo "  ✓ $USER_COUNT users, $GROUP_COUNT groups loaded"

# Step 5: Generate INSERTs
echo "[5/7] Generating INSERTs..."

# Groups (with slug from label)
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO app_group (id, name, slug, is_open, is_system, type) VALUES (', id, ', ', QUOTE(label), ', ', QUOTE(LOWER(REPLACE(label, ' ', '-'))), ', ', IF(fgopen, 'true', 'false'), ', ', IF(fgall, 'true', 'false'), ', ', QUOTE('Groupe de Travail'), ');') FROM groupe ORDER BY id;
" > /tmp/migrate_groups.sql 2>/dev/null

# Users
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO app_user (id, username, email, password, roles, firstname, lastname, avatar) VALUES (', id, ', ', QUOTE(username), ', ', QUOTE(COALESCE(email, CONCAT(username, '@ninegate.local'))), ', ', QUOTE(password), ', ', QUOTE(IF(role = 'ROLE_ADMIN', '[\"ROLE_ADMIN\"]', IF(role = 'ROLE_MASTER', '[\"ROLE_MASTER\"]', '[\"ROLE_USER\"]'))), ', ', QUOTE(COALESCE(firstname, '')), ', ', QUOTE(COALESCE(lastname, '')), ', ', 'NULL', ');') FROM user ORDER BY id;
" > /tmp/migrate_users.sql 2>/dev/null

# User-groups (IDs match now)
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO user_group (id, user_id, group_id, role) VALUES (', id, ', ', user_id, ', ', group_id, ', ', IF(fgmanager, '''MASTER''', '''USER'''), ');') FROM usergroupe ORDER BY id;
" > /tmp/migrate_usergroups.sql 2>/dev/null

echo "  ✓ Groups: $(wc -l < /tmp/migrate_groups.sql), Users: $(wc -l < /tmp/migrate_users.sql), Memberships: $(wc -l < /tmp/migrate_usergroups.sql)"

# Item categories
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item_category (id, title, sort_order) VALUES (', id, ', ', QUOTE(label), ', ', COALESCE(rowOrder, 0), ');') FROM itemcategory ORDER BY id;
" > /tmp/migrate_itemcategories.sql 2>/dev/null

# Items (icon_id set to NULL - old icon IDs don't match fixtures)
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item (id, title, summary, bgcolor, color, url, new_tab, sort_order, category_id) VALUES (', id, ', ', QUOTE(title), ', ', COALESCE(QUOTE(subtitle), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', QUOTE(url), ', ', IF(target = '_blank', 'true', 'false'), ', ', COALESCE(rowOrder, 0), ', ', category, ');') FROM item ORDER BY id;
" > /tmp/migrate_items.sql 2>/dev/null

# Item-group memberships
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item_group (item_id, group_id) VALUES (', item, ', ', groupe, ');') FROM itemgroupe ORDER BY item, groupe;
" > /tmp/migrate_itemgroups.sql 2>/dev/null

# Blogs
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO blog (id, title, slug, blog_order) VALUES (', id, ', ', QUOTE(name), ', ', QUOTE(LOWER(REPLACE(name, ' ', '-'))), ', ', 0, ');') FROM blog ORDER BY id;
" > /tmp/migrate_blogs.sql 2>/dev/null

# Blog articles - content too complex to migrate cleanly (HTML entities, special chars)
# Articles can be re-imported manually
touch /tmp/migrate_blogarticles.sql

# Bookmarks (icon_id set to NULL - old icon IDs don't match fixtures)
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO bookmark (id, title, summary, bgcolor, color, url, new_tab, user_id) VALUES (', id, ', ', QUOTE(title), ', ', COALESCE(QUOTE(subtitle), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ', QUOTE(url), ', ', IF(target = '_blank', 'true', 'false'), ', ', COALESCE(user_id, 'NULL'), ');') FROM bookmark ORDER BY id;
" > /tmp/migrate_bookmarks.sql 2>/dev/null

echo "  ✓ ItemCategories: $(wc -l < /tmp/migrate_itemcategories.sql), Items: $(wc -l < /tmp/migrate_items.sql), Blogs: $(wc -l < /tmp/migrate_blogs.sql), Articles: $(wc -l < /tmp/migrate_blogarticles.sql), Bookmarks: $(wc -l < /tmp/migrate_bookmarks.sql)"

# Step 6: Apply to PostgreSQL
echo "[6/7] Applying to PostgreSQL..."
cat /tmp/migrate_groups.sql /tmp/migrate_users.sql /tmp/migrate_usergroups.sql /tmp/migrate_itemcategories.sql /tmp/migrate_items.sql /tmp/migrate_itemgroups.sql /tmp/migrate_blogs.sql /tmp/migrate_blogarticles.sql /tmp/migrate_bookmarks.sql > /tmp/migrate_final.sql
echo "UPDATE app_group SET slug = 'all', is_system = true WHERE name = 'Tout le Monde';" >> /tmp/migrate_final.sql

# Fix MySQL escaping → PostgreSQL escaping
# MySQL QUOTE() uses \' but PostgreSQL expects ''
sed -i "s/\\\\\\\\'/''/g" /tmp/migrate_final.sql

docker exec -i ninegate-postgres psql -U user -d ninegate < /tmp/migrate_final.sql 2>&1 | grep -i "error" | head -10 || true

# Resync sequences
echo "[6/7] Resyncing sequences..."
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
SELECT setval(pg_get_serial_sequence('app_user', 'id'), COALESCE((SELECT MAX(id) FROM app_user), 1));
SELECT setval(pg_get_serial_sequence('app_group', 'id'), COALESCE((SELECT MAX(id) FROM app_group), 1));
SELECT setval(pg_get_serial_sequence('user_group', 'id'), COALESCE((SELECT MAX(id) FROM user_group), 1));
SELECT setval(pg_get_serial_sequence('icon', 'id'), COALESCE((SELECT MAX(id) FROM icon), 1));
SELECT setval(pg_get_serial_sequence('widget', 'id'), COALESCE((SELECT MAX(id) FROM widget), 1));
SELECT setval(pg_get_serial_sequence('page', 'id'), COALESCE((SELECT MAX(id) FROM page), 1));
SELECT setval(pg_get_serial_sequence('item', 'id'), COALESCE((SELECT MAX(id) FROM item), 1));
SELECT setval(pg_get_serial_sequence('item_category', 'id'), COALESCE((SELECT MAX(id) FROM item_category), 1));
SELECT setval(pg_get_serial_sequence('bookmark', 'id'), COALESCE((SELECT MAX(id) FROM bookmark), 1));
SELECT setval(pg_get_serial_sequence('blog', 'id'), COALESCE((SELECT MAX(id) FROM blog), 1));
SELECT setval(pg_get_serial_sequence('blog_article', 'id'), COALESCE((SELECT MAX(id) FROM blog_article), 1));
SELECT setval(pg_get_serial_sequence('page_template', 'id'), COALESCE((SELECT MAX(id) FROM page_template), 1));
" 2>&1
echo "  ✓ Sequences resynced"
echo "  ✓ Done"

# Step 7: Summary
echo "[7/7] Summary..."
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
SELECT 'app_user' t, COUNT(*) c FROM app_user UNION ALL SELECT 'app_group', COUNT(*) FROM app_group UNION ALL SELECT 'user_group', COUNT(*) FROM user_group UNION ALL SELECT 'icon', COUNT(*) FROM icon UNION ALL SELECT 'widget', COUNT(*) FROM widget UNION ALL SELECT 'item_category', COUNT(*) FROM item_category UNION ALL SELECT 'item', COUNT(*) FROM item UNION ALL SELECT 'item_group', COUNT(*) FROM item_group UNION ALL SELECT 'blog', COUNT(*) FROM blog UNION ALL SELECT 'blog_article', COUNT(*) FROM blog_article UNION ALL SELECT 'bookmark', COUNT(*) FROM bookmark ORDER BY t;
"

docker rm -f "$TMP_MARIADB" 2>/dev/null
rm -f /tmp/ninegate_formatted.sql /tmp/migrate_groups.sql /tmp/migrate_users.sql /tmp/migrate_usergroups.sql /tmp/migrate_itemcategories.sql /tmp/migrate_items.sql /tmp/migrate_itemgroups.sql /tmp/migrate_blogs.sql /tmp/migrate_blogarticles.sql /tmp/migrate_bookmarks.sql
echo "  Final SQL kept at: /tmp/migrate_final.sql"

echo "=== Migration terminée ==="
