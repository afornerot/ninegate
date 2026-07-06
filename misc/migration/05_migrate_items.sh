#!/bin/bash
# Step 5: Migrate items + item_categories + item_groups
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

cat > "$FINAL" << 'HEADER'
SET session_replication_role = 'replica';
HEADER

# Item categories
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item_category (id, title, sort_order) VALUES (', id, ', ', QUOTE(label), ', ', COALESCE(rowOrder, 0), ') ON CONFLICT (id) DO NOTHING;') FROM itemcategory ORDER BY id;
" 2>/dev/null >> "$FINAL"

# Items (icon_id = NULL, old UUID icons don't match new descriptive icons)
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item (id, title, summary, bgcolor, color, url, new_tab, sort_order, icon_id, category_id) VALUES (',
  id, ', ', QUOTE(title), ', ', COALESCE(QUOTE(subtitle), 'NULL'), ', ',
  COALESCE(QUOTE(color), 'NULL'), ', ', COALESCE(QUOTE(color), 'NULL'), ', ',
  QUOTE(url), ', ', IF(target = '_blank', 'true', 'false'), ', ',
  COALESCE(rowOrder, 0), ', NULL, ', category,
  ') ON CONFLICT (id) DO NOTHING;')
FROM item ORDER BY id;
" 2>/dev/null >> "$FINAL"

# Item-group memberships
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO item_group (item_id, group_id) VALUES (', item, ', ', groupe, ') ON CONFLICT DO NOTHING;') FROM itemgroupe ORDER BY item, groupe;
" 2>/dev/null >> "$FINAL"

echo "SET session_replication_role = 'origin';" >> "$FINAL"

sed -i "s/\\\\\\\\'/''/g" "$FINAL"

docker exec -i ninegate-postgres psql -U user -d ninegate < "$FINAL" 2>&1 | grep -i "error" | head -3 || true
rm -f "$FINAL"

IC=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item;" 2>/dev/null)
IG=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item_group;" 2>/dev/null)
echo "✓ $IC items, $IG item_groups"
