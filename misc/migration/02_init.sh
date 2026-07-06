#!/bin/bash
# Step 2: Load fixtures (icons, pages, widgets, config, etc.)
set -e
echo "=== [2/6] Init: fixtures ==="
docker exec ninegate php bin/console doctrine:fixtures:load --no-interaction 2>&1 | tail -3
echo "✓ Fixtures loaded"
