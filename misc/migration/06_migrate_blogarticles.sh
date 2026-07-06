#!/bin/bash
# Step 6: Migrate ALL blog articles
# HTML → Markdown via pandoc
set -e
TMP_MARIADB="migration-mariadb"
TMP_HTML="/tmp/blog_html"
FINAL="/tmp/migrate_blogarticles.sql"

echo "=== [6/9] Migrate blog articles ==="

docker exec -i ninegate-postgres psql -U user -d ninegate -c "DELETE FROM blog_article;" 2>&1 > /dev/null
rm -rf "$TMP_HTML" /tmp/articles_*.txt
mkdir -p "$TMP_HTML"

# Export metadata (ALL blogs, include blog_id)
docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate -N -e "SELECT CONCAT(id, '|', COALESCE(name, ''), '|', COALESCE(user_id, 'NULL'), '|', submit, '|', blog_id) FROM blogarticle ORDER BY id;" > /tmp/articles_meta.txt 2>/dev/null

# Export HTML content
docker exec "$TMP_MARIADB" mysql -uroot -proot ninegate --raw -e "SELECT id, COALESCE(description, '') FROM blogarticle ORDER BY id;" > /tmp/articles_html_raw.txt 2>/dev/null

META_COUNT=$(wc -l < /tmp/articles_meta.txt)
echo "  ✓ $META_COUNT articles found"

# Convert each article
echo "  Converting HTML → Markdown..."
while IFS='|' read -r id name user_id submit blog_id; do
    [ -z "$id" ] && continue
    awk -v id="$id" 'BEGIN{f=0} $1==id{f=1;next} f&&/^[0-9]+\t/{exit} f{print}' /tmp/articles_html_raw.txt > "$TMP_HTML/${id}.html"
    if [ -s "$TMP_HTML/${id}.html" ]; then
        sed -i 's/<[a-z]*#[^>]*>//g; s/<\/[a-z]*#[^>]*>//g; s/{[^}]*}//g' "$TMP_HTML/${id}.html"
        pandoc -f html -t markdown --wrap=none "$TMP_HTML/${id}.html" > "$TMP_HTML/${id}.md" 2>/dev/null
    else
        touch "$TMP_HTML/${id}.md"
    fi
done < /tmp/articles_meta.txt

CONVERTED=$(ls "$TMP_HTML"/*.md 2>/dev/null | wc -l)
echo "  ✓ $CONVERTED articles converted"

# Build SQL
echo "  Building SQL..."
cat > "$FINAL" << 'HEADER'
SET session_replication_role = 'replica';
HEADER

while IFS='|' read -r id name user_id submit blog_id; do
    [ -z "$id" ] && continue
    MD=$(sed "s/'/''/g" "$TMP_HTML/${id}.md" 2>/dev/null || echo "")
    NAME_ESC=$(echo "$name" | sed "s/'/''/g")
    SLUG=$(echo "$name" | tr '[:upper:]' '[:lower:]' | sed "s/[^a-z0-9-]/-/g; s/--*/-/g")
    echo "INSERT INTO blog_article (id, title, slug, content, image, \"created_at\", \"updated_at\", user_id, blog_id) VALUES ($id, '$NAME_ESC', '$SLUG', '$MD', NULL, '$submit', '$submit', $user_id, $blog_id) ON CONFLICT (id) DO NOTHING;" >> "$FINAL"
done < /tmp/articles_meta.txt

echo "SET session_replication_role = 'origin';" >> "$FINAL"

# Apply
echo "  Applying to PostgreSQL..."
docker exec -i ninegate-postgres psql -U user -d ninegate < "$FINAL" 2>&1 | grep -i "error" | head -3 || true

ACOUNT=$(docker exec -i ninegate-postgres psql -U user -d ninegate -t -A -c "SELECT COUNT(*) FROM blog_article;" 2>/dev/null)
echo "✓ $ACOUNT blog articles migrated"

rm -rf "$TMP_HTML" /tmp/articles_*.txt "$FINAL"
