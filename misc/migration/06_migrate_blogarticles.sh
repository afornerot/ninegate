#!/bin/bash
# Step 6: Migrate ALL blog articles
# HTML → Markdown via pandoc
set -e
TMP_MARIADB="migration-mariadb"
TMP_HTML="/tmp/blog_html"
FINAL="/tmp/migrate_blogarticles.sql"

echo "=== [6/9] Migrate blog articles ==="

docker exec -i ninegate-postgres psql -U user -d ninegate -c "DELETE FROM blog_article; DELETE FROM blog;" 2>&1 > /dev/null

cat > "$FINAL" << 'HEADER'
SET session_replication_role = 'replica';
INSERT INTO blog (id, title, slug, blog_order) VALUES (3, 'Interne', 'interne', 0) ON CONFLICT (id) DO NOTHING;
INSERT INTO blog_group (blog_id, group_id) VALUES (3, 5) ON CONFLICT DO NOTHING;
HEADER
rm -rf "$TMP_HTML" /tmp/articles_*.txt
mkdir -p "$TMP_HTML"

# Export metadata (blog Interne only, id=3)
docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT CONCAT(id, '|', COALESCE(name, ''), '|', COALESCE(user_id, 'NULL'), '|', submit) FROM blogarticle WHERE blog_id = 3 ORDER BY id;" > /tmp/articles_meta.txt 2>/dev/null

# Export HTML content (blog Interne only)
docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate --raw -e "SELECT id, COALESCE(description, '') FROM blogarticle WHERE blog_id = 3 ORDER BY id;" > /tmp/articles_html_raw.txt 2>/dev/null

META_COUNT=$(wc -l < /tmp/articles_meta.txt)
echo "  ✓ $META_COUNT articles found"

# Convert each article
echo "  Converting HTML → Markdown..."
while IFS='|' read -r id name user_id submit blog_id; do
    [ -z "$id" ] && continue
    awk -v id="$id" 'BEGIN{f=0} $1==id{f=1;next} f&&/^[0-9]+\t/{exit} f{print}' /tmp/articles_html_raw.txt > "$TMP_HTML/${id}.html"
    if [ -s "$TMP_HTML/${id}.html" ]; then
        # Clean HTML then convert to Markdown via html2md
        php /home/afornerot/git/nine-project/ninegate/misc/migration/clean_html.php "$TMP_HTML/${id}.html" "$TMP_HTML/${id}_clean.html"
        ~/go/bin/html2md -i "$TMP_HTML/${id}_clean.html" > "$TMP_HTML/${id}.md" 2>/dev/null
    fi
done < /tmp/articles_meta.txt

CONVERTED=$(ls "$TMP_HTML"/*.md 2>/dev/null | wc -l)
echo "  ✓ $CONVERTED articles converted"

# Build SQL using PHP for proper escaping
echo "  Building SQL..."
cat > "$FINAL" << 'HEADER'
SET session_replication_role = 'replica';
INSERT INTO blog (id, title, slug, blog_order) VALUES (3, 'Interne', 'interne', 0) ON CONFLICT (id) DO NOTHING;
INSERT INTO blog_group (blog_id, group_id) VALUES (3, 5) ON CONFLICT DO NOTHING;
HEADER

while IFS='|' read -r id name user_id submit; do
    [ -z "$id" ] && continue
    # Write name to temp file to avoid shell escaping issues
    echo -n "$name" > /tmp/_tmp_name.txt
    echo -n "$MD" > /tmp/_tmp_md.txt
    php -r "
        \$id = intval('$id');
        \$name = str_replace(\"'\", \"''\", file_get_contents('/tmp/_tmp_name.txt'));
        \$user = '$user_id';
        \$submit = '$submit';
        \$slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', \$name));
        \$html = str_replace(\"'\", \"''\", @file_get_contents('$TMP_HTML/${id}.md') ?: '');
        echo \"INSERT INTO blog_article (id, title, slug, content, image, \\\"created_at\\\", \\\"updated_at\\\", user_id, blog_id) VALUES (\"
            . \$id . ', '
            . \"'\" . \$name . \"', \"
            . \"'\" . \$slug . \"', \"
            . \"'\" . \$html . \"', \"
            . 'NULL, '
            . \"'\" . \$submit . \"', \"
            . \"'\" . \$submit . \"', \"
            . \$user . ', 3) ON CONFLICT (id) DO NOTHING;'
            . PHP_EOL;
    " >> "$FINAL"
done < /tmp/articles_meta.txt

echo "SET session_replication_role = 'origin';" >> "$FINAL"

# Apply
echo "  Applying to PostgreSQL..."
docker exec -i ninegate-postgres psql -U user -d ninegate < "$FINAL" 2>&1 | grep -i "error" | head -3 || true

ACOUNT=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM blog_article;" 2>/dev/null)
echo "✓ $ACOUNT blog articles migrated"

rm -rf "$TMP_HTML" /tmp/articles_*.txt "$FINAL"
