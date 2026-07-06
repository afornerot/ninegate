-- migrate_final.sql
-- Generated from: docker exec -i migration-mariadb mysql -uroot -proot ninegate < migrate_to_pg.sql > migrate_data.sql
-- Then: grep "^INSERT INTO" migrate_data.sql > migrate_data_clean.sql
-- Then: manually assemble this file

-- TRUNCATE all tables
TRUNCATE page_widget, page_group, page, blog_article, blog, bookmark, item_group, item, item_category, user_group, app_group, app_user, icon, widget CASCADE;

-- Paste INSERT statements from migrate_data_clean.sql here
-- (filter out: page_widget, widget)
-- (fix: page rows — deduplicate, set page_template_id=25, roles=NULL)
-- (filter: bookmarks with NULL icon_id or NULL user_id)

-- WIDGETS (new ninegate definitions)
INSERT INTO widget (id, title, description, route, with_border, with_title) VALUES
(-1, 'Notes', 'Bloc de notes rapide pour garder une idée.', 'pagewidget_note', true, false),
(-2, 'Fichiers', 'Espace de stockage et de partage de fichiers.', 'pagewidget_file', true, true),
(-3, 'Galerie', 'Affiche vos images dans une galerie immersive.', 'pagewidget_gallery', false, false),
(-4, 'Carousel', 'Diapositive d images défilantes en plein écran.', 'pagewidget_carousel', false, false),
(-5, 'Bureau', 'Centre de navigation avec vos raccourcis.', 'pagewidget_bureau', true, true),
(-6, 'Favoris', 'Vos raccourcis et signets favoris.', 'pagewidget_bookmark', true, true),
(-7, 'Liens', 'Liste de liens web organisés et cliquables.', 'pagewidget_link', true, true),
(-8, 'Flux RSS', 'Agrège et affiche les actualités de vos flux RSS.', 'pagewidget_rss', true, true),
(-9, 'Meteo', 'Prévisions météo en temps réel.', 'pagewidget_weather', true, true),
(-10, 'Horloge', 'Affiche l heure actuelle.', 'pagewidget_clock', true, true),
(-11, 'Blog', 'Derniers articles de blog publiés.', 'pagewidget_blog', true, true)
ON CONFLICT (id) DO NOTHING;

-- POST-MIGRATION
UPDATE app_group SET slug = 'all', is_system = true WHERE name = 'Tout le Monde';
UPDATE app_user SET needs_password_upgrade = true;
