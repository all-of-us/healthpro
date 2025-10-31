# HealthPro Portal

![Build Status of develop](https://circleci.com/gh/all-of-us/healthpro.png)

## Contributions

HealthPro is a web application established in support of the All of Us Research Program. It serves as an interface for Program health professionals, including Health Provider Organizations (HPOs), Federally Qualified Health Centers (FQHCs), and Direct Volunteer (DV) providers for the collection of physical measurements and biospecimen samples. It has since expanded to support ancillary studies like Nutrition for Precision Health (NPH), incorporating expanded biospecimen collections (e.g., stool, hair, nail samples), and pediatric workflows with growth chart percentiles.

We are developing this project in the open, and publishing the code with an open-source license, to share knowledge and provide insight and sample code for the community. We welcome your feedback! Please send any security concerns to security@pmi-ops.org, and feel free to file other issues via GitHub. Please note that we do not plan to incorporate external code contributions at this time, given that HealthPro exists to meet the specific operational needs of the All of Us Research Program.

## Developer Quick Start

Prerequisites:

* [Google Cloud SDK](https://cloud.google.com/sdk/docs/)
    1. Follow platform-specific instructions for downloading and installing the [Google Cloud SDK](https://cloud.google.com/sdk/docs/)
        * **NOTE**: For WSL, use the generic Linux directions and the tar.gz file, NOT the apt-get option for Ubuntu. Gcloud has a built-in package manager that doesn't work if it's installed through apt.
    2. Run the `install.sh` command so that `gcloud` is available from your path (for Mac and WSL)
    3. Run `gcloud init`
        * When asked for your "authorization code", sign into Google with your PMI-OPS account
        * When asked to "pick cloud project to use", enter `pmi-hpo-dev`
    4. Install additional gcloud components:
        * `gcloud components install cloud-datastore-emulator`
        * `gcloud components install cloud_sql_proxy`
        * `gcloud components install app-engine-php`  **NOTE:** This is not available in WSL and may not be required
* [NodeJS](https://nodejs.org/) (latest LTS should be fine)
* [MySQL](https://dev.mysql.com/downloads/mysql/) (select version 5.7 which is used by Google Cloud SQL)
* [Composer](https://getcomposer.org/doc/00-intro.md#globally)
* [git-secrets](https://github.com/awslabs/git-secrets#installing-git-secrets)
* [Symfony CLI](https://symfony.com/download)
* [Podman](https://podman.io/)

Install PHP dependencies via Composer:

`composer install`

Install front end assets and build tooling via NPM:

`npm install`

Compile assets using [Webpack Encore](https://symfony.com/doc/4.4/frontend.html) and recompile on the fly as assets change:

`npm run watch`

To build and start the containers

`podman compose up -d`

To stop the containers

`podman compose stop`

## Credentials and configuration

### Automated credential scanning

**Important:** Install and use [`git-secrets`](https://github.com/awslabs/git-secrets) to avoid exposing API keys and certificates by screening commits for matched strings. After installing that utility, run the script below to configure the hooks for this project.

`./bin/installHooks`

### config.yml

Configure your local development parameters by copying the `dev_config/config.yml.dist` file to `dev_config/config.yml`.  Edit `config.yml` as needed.  This file is .gitignore'd.  See comments in the `config.yml.dist` file for more details.

#### MySQL database configuration
Create a new MySQL for this application.  Configure the MySQL connection in `config.yml`. 

Then, run `bin/console doctine:migrations:migrate` to build your local database.

Then, run `bin/dx bin/console doctrine:migrations:migrate` to execute the necessary Doctrine migrations.

(`bin/dx` is a convenience wrapper for executing a command inside the Docker web container. You can also run `bin/dx bash` to open a shell into the container.)

## Jira Release Process

To create security approval comment
`./bin/console pmi:jira --comment=approval`

To attach deploy output
`./bin/console pmi:jira --comment=file`
