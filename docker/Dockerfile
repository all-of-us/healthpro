FROM php:7.2

# Fix for issue with OpenJDK install
RUN mkdir -p /usr/share/man/man1

# Install baseline packages
RUN curl -sL https://deb.nodesource.com/setup_12.x | bash - \
      && apt-get update \
      && apt-get install -y --no-install-recommends \
        default-mysql-client=1.0.5 \
        git-all=1:2.20.1-2 \
        nodejs=12.11.1-1nodesource1 \
        openjdk-11-jdk=11.0.5+10-1~deb10u1 \
      && docker-php-ext-install pdo_mysql \
      && rm -rf /var/lib/apt/lists/*

# Google Cloud Tools
WORKDIR /opt

RUN export CLOUDSDK_PYTHON=/usr/bin/python3 \
      && curl -Os https://dl.google.com/dl/cloudsdk/channels/rapid/downloads/google-cloud-sdk-267.0.0-linux-x86_64.tar.gz \
      && tar -xzf google-cloud-sdk-267.0.0-linux-x86_64.tar.gz \
      && /opt/google-cloud-sdk/install.sh --quiet --path-update true \
      && /opt/google-cloud-sdk/bin/gcloud components install --quiet beta cloud-datastore-emulator \
      && /opt/google-cloud-sdk/bin/gcloud config set project pmi-hpo-dev

# Start emulator and the web server
WORKDIR /app

CMD ["/bin/sh", "-c", "gcloud beta emulators datastore start & bin/console pmi:deploy --local 2>&1"]
