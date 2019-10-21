FROM php:7.2

RUN curl -sL https://deb.nodesource.com/setup_12.x | bash -

RUN mkdir -p /usr/share/man/man1

RUN apt-get update && apt-get install -y default-mysql-client git-all nodejs openjdk-11-jdk

RUN curl -Os https://dl.google.com/dl/cloudsdk/channels/rapid/downloads/google-cloud-sdk-267.0.0-linux-x86_64.tar.gz && tar -xzf google-cloud-sdk-267.0.0-linux-x86_64.tar.gz

RUN ./google-cloud-sdk/install.sh 

RUN google-cloud-sdk/bin/gcloud components install --quiet beta cloud-datastore-emulator

RUN google-cloud-sdk/bin/gcloud config set project pmi-hpo-dev

RUN docker-php-ext-install pdo_mysql

WORKDIR /app

RUN gcloud beta emulators datastore start &

# Start web server
CMD bin/console pmi:deploy --local
