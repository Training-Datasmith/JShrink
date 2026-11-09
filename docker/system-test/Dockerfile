FROM php:7.2-cli@sha256:42ffbc0798e4449bbd1e14fc4dcb87774aa1ad1900a09ef6a965bc0880aa2161

ENV DEBIAN_FRONTEND=noninteractive

# php:7.2-cli is Debian buster (archived); apt uses https://archive.debian.org only.
RUN rm -f /etc/apt/sources.list \
    && printf '%s\n' \
        'deb https://archive.debian.org/debian buster main' \
        'deb https://archive.debian.org/debian-security buster/updates main' \
        'deb https://archive.debian.org/debian buster-updates main' \
        > /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\n' >/etc/apt/apt.conf.d/99no-check-valid-until \
    && cat /etc/apt/sources.list

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
