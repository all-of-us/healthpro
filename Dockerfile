FROM php:8.2

# Fix for issue with OpenJDK install
RUN mkdir -p /usr/share/man/man1

# Install baseline packages
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - \
      && apt-get update \
      && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git-all \
        nodejs \
        openjdk-17-jdk \
        libzstd-dev \
        zlib1g-dev \
        autoconf \
        g++ \
        libpng-dev \
      && docker-php-ext-install pdo_mysql \
      && docker-php-ext-install gd \
      && rm -rf /var/lib/apt/lists/*

# Install GRPC module for PHP
RUN MAKEFLAGS="-j $(nproc)" pecl install grpc \
      && docker-php-ext-enable grpc

# Google Cloud Tools
WORKDIR /opt
RUN export CLOUDSDK_PYTHON=/usr/bin/python3 \
      && curl -Os https://dl.google.com/dl/cloudsdk/channels/rapid/downloads/google-cloud-sdk-367.0.0-linux-x86_64.tar.gz \
      && tar -xzf google-cloud-sdk-367.0.0-linux-x86_64.tar.gz \
      && /opt/google-cloud-sdk/install.sh --quiet --path-update true \
      && /opt/google-cloud-sdk/bin/gcloud components install --quiet beta cloud-datastore-emulator \
      && /opt/google-cloud-sdk/bin/gcloud config set project pmi-hpo-dev \
      && curl -sL https://dl.google.com/cloudsql/cloud_sql_proxy.linux.amd64 -o /usr/local/bin/cloud_sql_proxy \
      && chmod +x /usr/local/bin/cloud_sql_proxy

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Symfony CLI
RUN curl -sSL https://get.symfony.com/cli/installer | bash - \
      && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Start emulator and the web server
WORKDIR /app
CMD ["/bin/sh", "-c", "/opt/google-cloud-sdk/bin/gcloud beta emulators datastore start & bin/console pmi:deploy --local --no-interaction 2>&1"]
