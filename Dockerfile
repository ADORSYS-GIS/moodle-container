## Copyright 2026 Adorsys GIS <gis-udm@adorsys.com>
## Licensed under the Apache License, Version 2.0 (the "License");
## you may not use this file except in compliance with the License.
## You may obtain a copy of the License at
##
##     https://www.apache.org/licenses/LICENSE-2.0
##
## Unless required by applicable law or agreed to in writing, software
## distributed under the License is distributed on an "AS IS" BASIS,
## WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
## See the License for the specific language governing permissions and
## limitations under the License.

FROM alpine:3.22

USER root

RUN apk update && apk add --no-cache tar curl gzip unzip nginx php84 php84-fpm php84-opcache \
  php84-mysqli php84-iconv php84-mbstring php84-curl php84-openssl php84-tokenizer php84-intl \
  php84-soap php84-xmlreader php84-fileinfo php84-sodium php84-exif php84-ctype php84-zip \
  php84-xmlwriter php84-gd php84-simplexml php84-dom php84-xml php84-pecl-redis php84-pecl-igbinary \
  php84-phar php84-posix php84-pecl-zstd php84-session envsubst tzdata sudo vim icu-data-full

# Optional packages
RUN apk add --no-cache aspell graphviz ghostscript python3 poppler-utils clamav

RUN adduser -D -g 'www' www

ARG MOODLE_ROOT_PATH='/moodleroot' \
    MOODLE_DATAROOT_PATH='/moodleroot/moodledata' \
    MOODLE_PATH='/moodleroot/moodle' \
    MOODLE_BUILD_URL='https://download.moodle.org/download.php/direct/stable502/moodle-latest-502.tgz'

ENV MOODLE_URL='' \
    MOOSH_URL='' \
    LANG='' \
    LANGUAGE='' \
    MOODLE_LANGUAGE='' \
    SITE_URL='' \
    MOODLE_ROOT_PATH=$MOODLE_ROOT_PATH \
    MOODLE_DATAROOT_PATH=$MOODLE_DATAROOT_PATH \
    MOODLE_PATH=$MOODLE_PATH \
    DB_TYPE='' \
    DB_HOST='' \
    DB_HOST_PORT='' \
    DB_NAME='' \
    DB_USER='' \
    DB_PASS='' \
    DB_PREFIX='' \
    MOODLE_SITENAME='' \
    MOODLE_SITESUMMARY='' \
    MOODLE_USERNAME='' \
    MOODLE_PASSWORD='' \
    MOODLE_EMAIL='' \
    DB_READ_REPLICA_HOST='' \
    DB_READ_REPLICA_PORT='' \
    DB_READ_REPLICA_USER='' \
    DB_READ_REPLICA_PASSWORD='' \
    REDIS_SESSION_ID_HOST="" \
    REDIS_SESSION_ID_PORT= \
    REDIS_SESSION_ID_AUTH_STRING="" \
    REDIS_APP_IP_AND_PORT="" \
    REDIS_APP_AUTH_STRING="" \
    REDIS_SESSION_IP_AND_PORT="" \
    REDIS_SESSION_AUTH_STRING="" \
    REDIS_LOCK_HOST_AND_PORT=""\
    REDIS_LOCK_AUTH_STRING=""\
    SSLPROXY='' \
    NOEMAIL_EVER='' \
    SMTP_HOST='' \
    SMTP_PORT='' \
    SMTP_USER='' \
    SMTP_PASSWORD='' \
    SMTP_PROTOCOL='' \
    MOODLE_MAIL_NOREPLY_ADDRESS='' \
    MOODLE_MAIL_PREFIX=''
    

COPY ["./base/opt", "/opt"]

COPY --chown=root:root ["./base/etc", "/root/etc"]
COPY --chown=www:www ["./config.php.template", "/root/.templates/"]
COPY --chown=www:www ["./base/moodle", "/root/.templates/moodle"]

RUN mkdir -p "$MOODLE_PATH" "$MOODLE_DATAROOT_PATH" \
    && archive_file="$(mktemp)" \
    && curl --fail --location --retry 5 --retry-all-errors --output "$archive_file" "$MOODLE_BUILD_URL" \
    && tar -xzf "$archive_file" --strip-components=1 -C "$MOODLE_PATH" \
    && rm -f "$archive_file" \
    && mkdir -p "$MOODLE_PATH/local" "$MOODLE_PATH/public/local" \
    && cp /root/.templates/moodle/public/local/defaults.php "$MOODLE_PATH/public/local/defaults.php" \
    && chown -R www:www "$MOODLE_ROOT_PATH" /var/lib/nginx \
    && chmod 0755 /opt/*.sh \
    && chown -R root:root /opt/*.sh

ENTRYPOINT [ "/opt/entrypoint.sh" ]
