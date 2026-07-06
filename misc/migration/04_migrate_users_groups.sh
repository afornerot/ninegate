#!/bin/bash
# Step 4: Purge users/groups + Migrate users + groups + memberships
set -e
TMP_MARIADB="ninegate-migration-mariadb"

echo "=== [4/6] Migrate users + groups ==="

# Purge (targeted: only tables this script populates)
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
DELETE FROM user_group;
DELETE FROM app_user;
DELETE FROM app_group;
" 2>&1 > /dev/null

# Refresh sequences after purge
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
SELECT setval(pg_get_serial_sequence('app_user', 'id'), 1, false);
SELECT setval(pg_get_serial_sequence('app_group', 'id'), 1, false);
SELECT setval(pg_get_serial_sequence('user_group', 'id'), 1, false);
SELECT setval(pg_get_serial_sequence('item', 'id'), 1, false);
SELECT setval(pg_get_serial_sequence('item_category', 'id'), 1, false);
SELECT setval(pg_get_serial_sequence('blog', 'id'), 1, false);
SELECT setval(pg_get_serial_sequence('bookmark', 'id'), 1, false);
" 2>&1 > /dev/null

# Build single SQL with FK disabled
FINAL="/tmp/migrate_users_groups.sql"
cat > "$FINAL" << 'HEADER'
SET session_replication_role = 'replica';
HEADER

# Users
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO app_user (id, username, email, password, roles, firstname, lastname, avatar) VALUES (',
  id, ', ', QUOTE(username), ', ', QUOTE(COALESCE(email, CONCAT(username, '@ninegate.local'))), ', ',
  QUOTE(password), ', ',
  QUOTE(IF(role = 'ROLE_ADMIN', '[\"ROLE_ADMIN\"]', IF(role = 'ROLE_MASTER', '[\"ROLE_MASTER\"]', '[\"ROLE_USER\"]'))), ', ',
  QUOTE(COALESCE(firstname, '')), ', ', QUOTE(COALESCE(lastname, '')), ', NULL',
  ') ON CONFLICT (username) DO NOTHING;')
FROM user ORDER BY id;
" 2>/dev/null >> "$FINAL"

# Groups
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO app_group (id, name, slug, is_open, is_system, type) VALUES (',
  id, ', ', QUOTE(label), ', ', QUOTE(LOWER(REPLACE(label, ' ', '-'))), ', ',
  IF(fgopen, 'true', 'false'), ', ', IF(fgall, 'true', 'false'), ', ',
  QUOTE('Groupe de Travail'),
  ') ON CONFLICT (id) DO NOTHING;')
FROM groupe ORDER BY id;
" 2>/dev/null >> "$FINAL"

# User-group memberships
docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO user_group (id, user_id, group_id, role) VALUES (',
  id, ', ', user_id, ', ', group_id, ', ',
  IF(fgmanager, '''MASTER''', '''USER'''),
  ') ON CONFLICT DO NOTHING;')
FROM usergroupe ORDER BY id;
" 2>/dev/null >> "$FINAL"

# Fix "Tout le Monde" slug
echo "UPDATE app_group SET slug = 'all', is_system = true WHERE name = 'Tout le Monde';" >> "$FINAL"
echo "SET session_replication_role = 'origin';" >> "$FINAL"

# Fix MySQL escaping
sed -i "s/\\\\\\\\'/''/g" "$FINAL"

# Apply
docker exec -i ninegate-postgres psql -U user -d ninegate < "$FINAL" 2>&1 | grep -i "error" | head -3 || true
rm -f "$FINAL"

UCOUNT=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM app_user;" 2>/dev/null)
GCOUNT=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM app_group;" 2>/dev/null)
MCOUNT=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM user_group;" 2>/dev/null)
echo "✓ $UCOUNT users, $GCOUNT groups, $MCOUNT memberships"
