#!/bin/bash
# Step 8: Summary
set -e

echo "=== [8/8] Migration Summary ==="
docker exec -i ninegate-postgres psql -U user -d ninegate -c "
SELECT 'app_user' t, COUNT(*) c FROM app_user UNION ALL SELECT 'app_group', COUNT(*) FROM app_group UNION ALL SELECT 'user_group', COUNT(*) FROM user_group UNION ALL SELECT 'icon', COUNT(*) FROM icon UNION ALL SELECT 'widget', COUNT(*) FROM widget UNION ALL SELECT 'item_category', COUNT(*) FROM item_category UNION ALL SELECT 'item', COUNT(*) FROM item UNION ALL SELECT 'item_group', COUNT(*) FROM item_group UNION ALL SELECT 'blog', COUNT(*) FROM blog ORDER BY t;
"
echo "✓ Migration complete"
