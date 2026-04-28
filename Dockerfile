FROM php:8.2-fpm

ENV DEBIAN_FRONTEND=noninteractive
ENV VIRTUAL_ENV=/opt/whisper-venv
ENV PATH="${VIRTUAL_ENV}/bin:${PATH}"
ENV PIP_NO_CACHE_DIR=1
ENV HF_HOME=/var/www/storage/app/call-center/cache
ENV TRANSFORMERS_CACHE=/var/www/storage/app/call-center/cache
ENV XDG_CACHE_HOME=/var/www/storage/app/call-center/cache
ENV MPLCONFIGDIR=/var/www/storage/app/call-center/cache/matplotlib

RUN apt-get update && apt-get install -y \
    build-essential \
    pkg-config \
    git \
    curl \
    unzip \
    zip \
    ffmpeg \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libavcodec-dev \
    libavdevice-dev \
    libavfilter-dev \
    libavformat-dev \
    libavutil-dev \
    libswresample-dev \
    libswscale-dev \
    python3 \
    python3-pip \
    python3-venv \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql mbstring zip bcmath opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/php.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf
COPY scripts/requirements-faster-whisper.txt /tmp/requirements-faster-whisper.txt

RUN python3 -m venv "${VIRTUAL_ENV}" \
    && "${VIRTUAL_ENV}/bin/pip" install --upgrade pip setuptools wheel \
    && PIP_EXTRA_INDEX_URL=https://download.pytorch.org/whl/cpu \
       "${VIRTUAL_ENV}/bin/pip" install --prefer-binary -r /tmp/requirements-faster-whisper.txt \
    && rm -f /tmp/requirements-faster-whisper.txt

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

CMD ["php-fpm"]
