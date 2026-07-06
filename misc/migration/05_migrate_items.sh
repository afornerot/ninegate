#!/bin/bash
# Step 5: Migrate items + item_categories + item_groups
# Includes icon mapping: old UUID icons → new descriptive icons by basename
set -e
TMP_MARIADB="ninegate-migration-mariadb"
FINAL="/tmp/migrate_items.sql"

echo "=== [5/8] Migrate items ==="

# Purge (targeted)
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
DELETE FROM item_group;
DELETE FROM item;
DELETE FROM item_category;
" 2>&1 > /dev/null

# 1. Create temporary table in MariaDB with new icons (from PostgreSQL)
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -e "
DROP TABLE IF EXISTS new_icons;
CREATE TABLE new_icons (id INT, route VARCHAR(255), basename VARCHAR(255));
" 2>/dev/null

# Export new icons to temp file (avoids stdin conflict with docker exec -i)
TMP_ICONS=$(mktemp)
docker exec ninegate-postgres psql -U user -d ninegate -t -A -c "
SELECT id || '|' || route || '|' || SUBSTRING(route FROM LENGTH(route) - POSITION('/' IN REVERSE(route)) + 2) FROM icon;
" > "$TMP_ICONS" 2>/dev/null

# Load into MariaDB
while IFS='|' read -r id route basename; do
    [ -z "$id" ] && continue
    docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -e "
    INSERT INTO new_icons (id, route, basename) VALUES ($id, '$route', '$basename');
    " 2>/dev/null
done < "$TMP_ICONS"
rm -f "$TMP_ICONS"

echo "  ✓ $(docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT COUNT(*) FROM new_icons;" 2>/dev/null) new icons loaded into MariaDB"

# 2. Generate SQL with icon mapping
cat > "$FINAL" << 'HEADER'
SET session_replication_role = 'replica';
HEADER

# Item categories
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item_category (id, title, sort_order) VALUES (', id, ', ', QUOTE(label), ', ', COALESCE(rowOrder, 0), ') ON CONFLICT (id) DO NOTHING;') FROM itemcategory ORDER BY id;
" 2>/dev/null >> "$FINAL"

# Items with mapped icon_id via basename matching
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item (id, title, summary, bgcolor, color, url, new_tab, sort_order, icon_id, category_id) VALUES (',
  i.id, ', ', QUOTE(i.title), ', ', COALESCE(QUOTE(i.subtitle), 'NULL'), ', ',
  COALESCE(QUOTE(i.color), 'NULL'), ', ', COALESCE(QUOTE(i.color), 'NULL'), ', ',
  QUOTE(i.url), ', ', IF(i.target = '_blank', 'true', 'false'), ', ',
  COALESCE(i.rowOrder, 0), ', ',
  COALESCE((SELECT n.id FROM new_icons n INNER JOIN icon o ON SUBSTRING_INDEX(o.label, '/', -1) = n.basename WHERE o.id = i.icon_id LIMIT 1), 'NULL'),
  ', ', i.category,
  ') ON CONFLICT (id) DO NOTHING;')
FROM item i ORDER BY i.id;
" 2>/dev/null >> "$FINAL"

# Item-group memberships
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item_group (item_id, group_id) VALUES (', item, ', ', groupe, ') ON CONFLICT DO NOTHING;') FROM itemgroupe ORDER BY item, groupe;
" 2>/dev/null >> "$FINAL"

echo "SET session_replication_role = 'origin';" >> "$FINAL"

sed -i "s/\\\\\\\\'/''/g" "$FINAL"

docker exec -i ninegate-postgres psql -U user -d ninegate < "$FINAL" 2>&1 | grep -i "error" | head -3 || true
rm -f "$FINAL"

# Drop temp table
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -e "DROP TABLE IF EXISTS new_icons;" 2>/dev/null

IC=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item;" 2>/dev/null)
II=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item WHERE icon_id IS NOT NULL;" 2>/dev/null)
IG=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item_group;" 2>/dev/null)
echo "✓ $IC items ($II with icon), $IG item_groups"
