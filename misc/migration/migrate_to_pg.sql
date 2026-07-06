-- Migration: MariaDB old ninegate -> PostgreSQL new ninegate
-- Run from MariaDB container and redirect output
--
-- Usage:
--   docker run -d --name migration-mariadb -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=ninegate \
--     -v ./ninegate.sql:/docker-entrypoint-initdb.d/01_dump.sql mariadb:10.11
--   # Wait for MariaDB to start, load dump manually
--   docker exec -i migration-mariadb mysql -uroot -proot ninegate < ninegate.sql
--   # Generate INSERT PostgreSQL
--   docker exec -i migration-mariadb mysql -uroot -proot ninegate < migrate_to_pg.sql > migrate_data.sql
--   # Filter valid inserts
--   grep "^INSERT INTO" migrate_data.sql > migrate_data_clean.sql
--   # Apply to PostgreSQL (add TRUNCATE, widgets, post-migration manually)
--   docker exec -i ninegate-postgres psql -U user -d ninegate < migrate_final.sql

-- ======================================================================
-- TRUNCATE (order matters due to FKs)
-- ======================================================================
SELECT 'TRUNCATE page_widget, page_group, page, blog_article, blog, bookmark, item_group, item, item_category, user_group, app_group, app_user, icon, widget CASCADE;' AS sql_statement;

-- ======================================================================
-- ICONS
-- ======================================================================
SELECT CONCAT('INSERT INTO icon (id, route, tags) VALUES (', id, ', ''', REPLACE(COALESCE(label, ''), '''', ''''''), ''', ''', REPLACE(COALESCE(tags, ''), '''', ''''''), ''');') FROM icon ORDER BY id;

-- ======================================================================
-- USERS (password SSHA kept, avatar cleared)
-- ======================================================================
SELECT CONCAT(
  'INSERT INTO app_user (id, username, email, password, roles, firstname, lastname, avatar) VALUES (',
  id, ', ',
  '''', REPLACE(username, '''', ''''''), ''', ',
  '''', REPLACE(COALESCE(email, CONCAT(username, '@ninegate.local')), '''', ''''''), ''', ',
  '''', REPLACE(password, '''', ''''''), ''', ',
  '''', REPLACE(IF(role = 'ROLE_ADMIN', '["ROLE_ADMIN"]', IF(role = 'ROLE_MASTER', '["ROLE_MASTER"]', '["ROLE_USER"]')), '''', ''''''), ''', ',
  '''', REPLACE(COALESCE(firstname, ''), '''', ''''''), ''', ',
  '''', REPLACE(COALESCE(lastname, ''), '''', ''''''), ''', ',
  'NULL',
  ');'
) FROM user ORDER BY id;

-- ======================================================================
-- GROUPS (fgall=1 -> slug='all', is_system=true)
-- ======================================================================
SELECT CONCAT(
  'INSERT INTO app_group (id, name, description, slug, is_open, is_system, type) VALUES (',
  id, ', ',
  '''', REPLACE(label, '''', ''''''), ''', ',
  COALESCE(CONCAT('''', REPLACE(description, '''', ''''''), ''''), 'NULL'), ', ',
  '''', LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(label, ' ', '-'), '.', ''), '/', '-'), CHAR(233), 'e'), CHAR(232), 'e'), CHAR(234), 'e'), CHAR(224), 'a'), CHAR(226), 'a'), CHAR(244), 'o'), CHAR(251), 'u'), CHAR(39), '''''')), ''', ',
  IF(fgopen, 'true', 'false'), ', ',
  IF(fgall, 'true', 'false'), ', ',
  '''Groupe de Travail''',
  ');'
) FROM groupe ORDER BY id;

-- ======================================================================
-- USER-GROUP
-- ======================================================================
SELECT CONCAT('INSERT INTO user_group (id, user_id, group_id, role) VALUES (', id, ', ', user_id, ', ', group_id, ', ', IF(fgmanager, '''MASTER''', '''USER'''), ');') FROM usergroupe ORDER BY id;

-- ======================================================================
-- ITEM CATEGORIES
-- ======================================================================
SELECT CONCAT('INSERT INTO item_category (id, title, sort_order) VALUES (', id, ', ', '''', REPLACE(label, '''', ''''''), ''', ', COALESCE(rowOrder, 0), ');') FROM itemcategory ORDER BY id;

-- ======================================================================
-- ITEMS
-- ======================================================================
SELECT CONCAT('INSERT INTO item (id, title, summary, description, bgcolor, color, url, new_tab, sort_order, icon_id, category_id) VALUES (', id, ', ', '''', REPLACE(title, '''', ''''''), ''', ', COALESCE(CONCAT('''', REPLACE(subtitle, '''', ''''''), ''''), 'NULL'), ', ', COALESCE(CONCAT('''', REPLACE(content, '''', ''''''), ''''), 'NULL'), ', ', COALESCE(CONCAT('''', REPLACE(color, '''', ''''''), ''''), 'NULL'), ', ', COALESCE(CONCAT('''', REPLACE(color, '''', ''''''), ''''), 'NULL'), ', ', '''', REPLACE(url, '''', ''''''), ''', ', IF(target = '_blank', 'true', 'false'), ', ', COALESCE(rowOrder, 0), ', ', COALESCE(icon_id, 'NULL'), ', ', category, ');') FROM item ORDER BY id;

-- ======================================================================
-- ITEM-GROUP
-- ======================================================================
SELECT CONCAT('INSERT INTO item_group (item_id, group_id) VALUES (', item, ', ', groupe, ');') FROM itemgroupe ORDER BY item, groupe;

-- ======================================================================
-- BOOKMARKS
-- ======================================================================
SELECT CONCAT('INSERT INTO bookmark (id, title, summary, bgcolor, color, url, new_tab, icon_id, user_id) VALUES (', id, ', ', '''', REPLACE(title, '''', ''''''), ''', ', COALESCE(CONCAT('''', REPLACE(subtitle, '''', ''''''), ''''), 'NULL'), ', ', COALESCE(CONCAT('''', REPLACE(color, '''', ''''''), ''''), 'NULL'), ', ', COALESCE(CONCAT('''', REPLACE(color, '''', ''''''), ''''), 'NULL'), ', ', '''', REPLACE(url, '''', ''''''), ''', ', IF(target = '_blank', 'true', 'false'), ', ', COALESCE(icon_id, 'NULL'), ', ', COALESCE(user_id, 'NULL'), ');') FROM bookmark ORDER BY id;

-- ======================================================================
-- BLOGS
-- ======================================================================
SELECT CONCAT('INSERT INTO blog (id, title, slug, blog_order) VALUES (', id, ', ', '''', REPLACE(name, '''', ''''''), ''', ', '''', LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, ' ', '-'), '.', ''), '/', '-'), CHAR(233), 'e'), CHAR(232), 'e'), CHAR(224), 'a'), CHAR(39), '''''')), ''', ', 0, ');') FROM blog ORDER BY id;

-- ======================================================================
-- BLOG ARTICLES
-- ======================================================================
SELECT CONCAT('INSERT INTO blog_article (id, title, slug, content, image, "created_at", "updated_at", user_id, blog_id) VALUES (', id, ', ', '''', REPLACE(name, '''', ''''''), ''', ', '''', LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, ' ', '-'), '.', ''), '/', '-'), CHAR(233), 'e'), CHAR(232), 'e'), CHAR(224), 'a'), CHAR(39), '''''')), ''', ', COALESCE(CONCAT('''', REPLACE(description, '''', ''''''), ''''), 'NULL'), ', ', COALESCE(CONCAT('''', REPLACE(image, '''', ''''''), ''''), 'NULL'), ', ', '''', submit, ''', ', '''', submit, ''', ', COALESCE(user_id, 'NULL'), ', ', blog_id, ');') FROM blogarticle ORDER BY id;

-- ======================================================================
-- PAGES (roles to NULL, default template)
-- ======================================================================
SELECT CONCAT(
  'INSERT INTO page (id, title, slug, "page_order", "created_at", "updated_at", roles, user_id, page_template_id) VALUES (',
  id, ', ',
  '''', REPLACE(name, '''', ''''''), ''', ',
  '''', LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, ' ', '-'), '.', ''), '/', '-'), CHAR(233), 'e'), CHAR(232), 'e'), CHAR(224), 'a'), CHAR(39), '''''')), ''', ',
  COALESCE(roworder, 0), ', ',
  '''2024-01-01 00:00:00'', ',
  '''2024-01-01 00:00:00'', ',
  'NULL', ', ',
  COALESCE(user_id, 'NULL'), ', ',
  '25',
  ');'
) FROM page ORDER BY id;

-- ======================================================================
-- PAGE-GROUP
-- ======================================================================
SELECT CONCAT('INSERT INTO page_group (page_id, group_id) VALUES (', page, ', ', groupe, ');') FROM pagegroupe ORDER BY page, groupe;
