ARG PHP_IMAGE_VERSION=8.4

FROM ghcr.io/phpyh/php:${PHP_IMAGE_VERSION}

USER root

# Install inotify extension
RUN <<EOF
    set -eux
    (curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - || echo 'return 1') | sh -s \
        inotify
EOF

USER dev
