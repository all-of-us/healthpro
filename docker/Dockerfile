FROM php:7.2

RUN curl -sL https://deb.nodesource.com/setup_12.x | bash -

RUN echo "deb http://packages.cloud.google.com/apt cloud-sdk main" | tee -a /etc/apt/sources.list.d/google-cloud-sdk.list

RUN curl -sL https://packages.cloud.google.com/apt/doc/apt-key.gpg | apt-key add -

RUN apt-get update && apt-get install -y default-mysql-client git-all nodejs google-cloud-sdk

RUN docker-php-ext-install pdo_mysql

WORKDIR /app

RUN gcloud beta emulators datastore start &

# Start web server
CMD bin/console pmi:deploy --local
