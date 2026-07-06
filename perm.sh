#!/bin/bash
# Fix permissions for ninegate project
# Usage: sudo ./perm.sh

set -e

OWNER="afornerot:afornerot"

echo "=== Fixing ownership to $OWNER ==="
chown -R $OWNER /home/afornerot/git/nine-project/ninegate/

echo "=== Setting directory permissions (775) ==="
find /home/afornerot/git/nine-project/ninegate/ -type d -not -path "*/.git/*" -exec chmod 775 {} +

echo "=== Setting file permissions (664) ==="
find /home/afornerot/git/nine-project/ninegate/ -type f -not -path "*/.git/*" -exec chmod 664 {} +

echo "=== Executables ==="
chmod +x /home/afornerot/git/nine-project/ninegate/bin/console
chmod +x /home/afornerot/git/nine-project/ninegate/perm.sh

echo "=== Docker writable dirs ==="
chmod -R a+w /home/afornerot/git/nine-project/ninegate/var/
chmod -R a+w /home/afornerot/git/nine-project/ninegate/volume/
chmod -R a+w /home/afornerot/git/nine-project/ninegate/uploads/
chmod -R a+w /home/afornerot/git/nine-project/ninegate/public/uploads/
chmod -R a+w /home/afornerot/git/nine-project/ninegate/public/bundles/
chmod -R a+w /home/afornerot/git/nine-project/ninegate/public/lib/
chmod -R a+w /home/afornerot/git/nine-project/ninegate/public/medias/
chmod -R a+w /home/afornerot/git/nine-project/ninegate/misc/

echo "=== Done ==="
