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

docker exec ninegate-postgres psql -U user -d ninegate -t -A -c "
SELECT id || '|' || route || '|' || SUBSTRING(route FROM LENGTH(route) - POSITION('/' IN REVERSE(route)) + 2) FROM icon;
" > /tmp/new_icons_raw.txt 2>/dev/null

while IFS='|' read -r id route basename; do
    [ -z "$id" ] && continue
    docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -e "
    INSERT INTO new_icons (id, route, basename) VALUES ($id, '$route', '$basename');
    " 2>/dev/null
done < /tmp/new_icons_raw.txt
rm -f /tmp/new_icons_raw.txt

# 2. Add hardcoded icon mappings (old filename → new icon route)
# These are items whose old UUID icons didn't match any new icon by basename
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -e "
INSERT INTO new_icons (id, route, basename) VALUES
(25,  'medias/icon/icon_cadoles.png',  'icon_cadoles.png'),
(51,  'medias/icon/icon_corpus.png',   'icon_corpus.png'),
(70,  'medias/icon/icon_efs.png',      'icon_efs.png'),
(72,  'medias/icon/icon_envole.png',   'icon_envole.png'),
(100, 'medias/icon/icon_grafana.svg',  'icon_grafana.svg'),
(102, 'medias/icon/icon_harbor.png',   'icon_harbor.png'),
(140, 'medias/icon/icon_ninedad.png',  'icon_ninedad.png'),
(141, 'medias/icon/icon_ninemine.png', 'icon_ninemine.png'),
(174, 'medias/icon/icon_redmine.png',  'icon_redmine.png'),
(241, 'medias/icon/icon_xolo.png',     'icon_xolo.png'),
(250, 'medias/icon/icon_sentry.png',   'icon_sentry.png')
ON DUPLICATE KEY UPDATE id = id;
" 2>/dev/null

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

# Hardcoded icon mappings (item title → icon route)
echo "  Applying hardcoded icon mappings..."
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
-- Map items by title
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_cadoles.png') WHERE title IN ('Cadoles', 'Organisation', 'Site Vitrine');
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_efs.png') WHERE title IN ('EFS', 'Sheila DEV', 'Sheila Recette');
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_corpus.png') WHERE title = 'Corpus';
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_ninedad.png') WHERE title = 'Ninedad';
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_harbor.png') WHERE title = 'Harbor';
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_envole.png') WHERE title = 'Envole';
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_ninemine.png') WHERE title = 'Ninemine MSE';
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_grafana.svg') WHERE title = 'Grafana';
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_redmine.png') WHERE title IN ('Redmine', 'DProj');
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_xolo.png') WHERE title = 'Xolo';
UPDATE item SET icon_id = (SELECT id FROM icon WHERE route = 'medias/icon/icon_sentry.png') WHERE title = 'Sentry';
" 2>&1 > /dev/null

IC=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item;" 2>/dev/null)
II=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item WHERE icon_id IS NOT NULL;" 2>/dev/null)
IG=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM item_group;" 2>/dev/null)
echo "✓ $IC items ($II with icon), $IG item_groups"
