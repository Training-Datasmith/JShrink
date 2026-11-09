FROM php:7.2-cli@sha256:42ffbc0798e4449bbd1e14fc4dcb87774aa1ad1900a09ef6a965bc0880aa2161

ENV DEBIAN_FRONTEND=noninteractive

# php:7.2-cli is Debian buster (archived); use archive.debian.org over HTTPS.
RUN sed -i -e 's|deb.debian.org/debian|archive.debian.org/debian|g' \
        -e 's|security.debian.org/debian-security|archive.debian.org/debian-security|g' \
        /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\n' >/etc/apt/apt.conf.d/99no-check-valid-until

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libxml2-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" xml \
    && docker-php-ext-install -j"$(nproc)" dom mbstring tokenizer

WORKDIR /app
