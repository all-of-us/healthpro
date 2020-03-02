FROM gcr.io/gcp-runtimes/php72:2020-02-19-11-00

# Fix for issue with OpenJDK install
RUN mkdir -p /usr/share/man/man1

# Install baseline packages
RUN curl -sL https://deb.nodesource.com/setup_12.x | bash - \
      && apt-get update \
      && apt-get install -y --no-install-recommends \
        libpython2.7-stdlib \
        git-all \
        nodejs \
        default-jre \
      && rm -rf /var/lib/apt/lists/*

# Google Cloud Tools
WORKDIR /opt
RUN export CLOUDSDK_PYTHON=/usr/bin/python \
      && curl -Os https://dl.google.com/dl/cloudsdk/channels/rapid/downloads/google-cloud-sdk-267.0.0-linux-x86_64.tar.gz \
      && tar -xzf google-cloud-sdk-267.0.0-linux-x86_64.tar.gz \
      && /opt/google-cloud-sdk/install.sh --quiet --path-update true \
      && /opt/google-cloud-sdk/bin/gcloud components install --quiet beta cloud-datastore-emulator \
      && /opt/google-cloud-sdk/bin/gcloud config set project pmi-hpo-dev

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Start emulator and the web server
WORKDIR /app
CMD ["/bin/sh", "-c", "/opt/google-cloud-sdk/bin/gcloud beta emulators datastore start & bin/console pmi:deploy --local 2>&1"]
