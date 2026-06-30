FROM reg.cadoles.com/envole/nineapache:8.4

USER root

RUN apk add --no-cache tzdata
ENV TZ=Europe/Paris
RUN ln -sf /usr/share/zoneinfo/Europe/Paris /etc/localtime && echo "Europe/Paris" > /etc/timezone

COPY ./misc/docker/apache.conf /etc/apache2/conf.d/nine/site.conf

# Définir le répertoire HOME de composer dans un endroit accessible
ENV COMPOSER_HOME=/tmp/composer
RUN mkdir -p /tmp/composer && chown -R apache:apache /tmp/composer

# Latest releases available at https://github.com/aptible/supercronic/releases
ENV SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.2.43/supercronic-linux-amd64 \
    SUPERCRONIC_SHA1SUM=f97b92132b61a8f827c3faf67106dc0e4467ccf2 \
    SUPERCRONIC=supercronic-linux-amd64

RUN curl -fsSLO "$SUPERCRONIC_URL" \
    && echo "${SUPERCRONIC_SHA1SUM}  ${SUPERCRONIC}" | sha1sum -c - \
    && chmod +x "$SUPERCRONIC" \
    && mv "$SUPERCRONIC" "/usr/local/bin/${SUPERCRONIC}" \
    && ln -s "/usr/local/bin/${SUPERCRONIC}" /usr/local/bin/supercronic

RUN echo "* * * * * php /app/bin/console app:cron >> /app/var/log/cron.log 2>&1" > /crontab
RUN chown apache:apache /crontab


WORKDIR /app

# Crée vendor à l’avance et donne les droits
RUN mkdir -p /app/vendor && chown -R apache:apache /app

USER apache
COPY --chown=apache:apache . .

RUN composer install --no-interaction --optimize-autoloader --no-scripts

RUN mkdir -p /app/uploads \
    && mkdir -p /app/public/uploads/avatar \
    && mkdir -p /app/public/uploads/logo \
    && cp -rf /app/public/medias/logo /app/public/uploads/logo \
    && cp -rf /app/public/medias/avatar /app/public/uploads/avatar

CMD ["/app/misc/script/reconfigure.sh","/etc/apache2/apache2.sh"]
