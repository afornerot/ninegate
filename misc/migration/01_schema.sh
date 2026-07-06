#!/bin/bash
# Step 1: Drop and recreate database schema
set -e
echo "=== [1/6] Schema: drop + create ==="
docker exec -i ninegate-postgres psql -U user -d ninegate -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker exec ninegate php bin/console d:s:u --force --complete 2>&1 | tail -1
echo "✓ Schema ready"
