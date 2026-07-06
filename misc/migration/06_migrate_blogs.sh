#!/bin/bash
# Step 6: Migrate blogs
set -e
TMP_MARIADB="ninegate-migration-mariadb"
FINAL="/tmp/migrate_blogs.sql"

echo "=== [6/8] Migrate blogs ==="

# Purge (targeted)
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
DELETE FROM blog_article;
DELETE FROM blog;
" 2>&1 > /dev/null

cat > "$FINAL" << 'HEADER'
SET session_replication_role = 'replica';
HEADER

docker exec -i "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "
SELECT CONCAT('INSERT INTO blog (id, title, slug, blog_order) VALUES (',
  id, ', ', QUOTE(name), ', ', QUOTE(LOWER(REPLACE(name, ' ', '-'))), ', 0',
  ') ON CONFLICT (id) DO NOTHING;')
FROM blog ORDER BY id;
" 2>/dev/null >> "$FINAL"

echo "SET session_replication_role = 'origin';" >> "$FINAL"

sed -i "s/\\\\\\\\'/''/g" "$FINAL"

docker exec -i ninegate-postgres psql -U user -d ninegate < "$FINAL" 2>&1 | grep -i "error" | head -3 || true
rm -f "$FINAL"

BC=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM blog;" 2>/dev/null)
echo "✓ $BC blogs"
