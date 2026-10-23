FROM reg.cadoles.com/envole/nineapache:8.5

USER root

RUN apk add --no-cache tzdata php85-xmlreader php85-ldap
ENV TZ=Europe/Paris
RUN ln -sf /usr/share/zoneinfo/Europe/Paris /etc/localtime && echo "Europe/Paris" > /etc/timezone

COPY ./misc/docker/apache.conf /etc/apache2/conf.d/nine/site.conf

# Définir le répertoire HOME de composer dans un endroit accessible
ENV COMPOSER_HOME=/tmp/composer
RUN mkdir -p /tmp/composer && chown -R apache:apache /tmp/composer

# supercronic est fourni par l'image de base nineapache (inutile de le réinstaller)

RUN echo "* * * * * php /app/bin/console app:cron >> /app/var/log/cron.log 2>&1" > /crontab
RUN chown apache:apache /crontab


WORKDIR /app

# Crée vendor à l’avance et donne les droits
RUN mkdir -p /app/vendor /app/var/log /app/var/cache && chown -R apache:apache /app

USER apache
COPY --chown=apache:apache . .

RUN composer install --no-interaction --optimize-autoloader --no-scripts

RUN php bin/console assets:install public/ --no-debug 2>/dev/null || true
RUN php bin/console asset-map:compile --no-debug 2>/dev/null || true

RUN chmod +x bin/console misc/script/*.sh

RUN mkdir -p /app/uploads \
    && mkdir -p /app/public/uploads/avatar \
    && mkdir -p /app/public/uploads/logo \
    && cp -rf /app/public/medias/logo /app/public/uploads/logo \
    && cp -rf /app/public/medias/avatar /app/public/uploads/avatar

CMD ["/app/misc/script/reconfigure.sh","/etc/apache2/apache2.sh"]
