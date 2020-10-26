# HealthPro Portal and Admin Dashboard

![Build Status of develop](https://circleci.com/gh/all-of-us/healthpro.png)

## Contributions

HealthPro is being developed to facilitate in-person enrollment and other operations of the All of Us Research Program. We are developing this project in the open, and publishing the code with an open-source license, to share knowledge and provide insight and sample code for the community. We welcome your feedback! Please send any security concerns to security@pmi-ops.org, and feel free to file other issues via GitHub. Please note that we do not plan to incorporate external code contributions at this time, given that HealthPro exists to meet the specific operational needs of the All of Us Research Program.

## Developer Quick Start

Prerequisites:

* [Google Cloud SDK](https://cloud.google.com/sdk/docs/)
    1. Follow platform-specific instructions for downloading and installing the [Google Cloud SDK](https://cloud.google.com/sdk/docs/)
    2. Run the optional `install.sh` command so that `gcloud` is available from path
    3. Run `gcloud init`
    4. Install additional gcloud components:
        * `gcloud components install app-engine-php`
        * `gcloud components install cloud-datastore-emulator`
* [NodeJS](https://nodejs.org/) (latest LTS should be fine)
* [MySQL](https://dev.mysql.com/downloads/mysql/) (select version 5.7 which is used by Google Cloud SQL)
* [Composer](https://getcomposer.org/doc/00-intro.md#globally)
* [git-secrets](https://github.com/awslabs/git-secrets#installing-git-secrets)

Install PHP dependencies via Composer:

`composer install`

Install front end assets and build tooling via NPM:

`npm install`

Compile assets using [Webpack Encore](https://symfony.com/doc/4.4/frontend.html) and recompile on the fly as assets change:

`npm run watch`

Run local Datastore emulator

`gcloud beta emulators datastore start`

Run local App Engine dev server:

`./bin/console pmi:deploy --local`

## Credentials and configuration

### Automated credential scanning

**Important:** Install and use [`git-secrets`](https://github.com/awslabs/git-secrets) to avoid exposing API keys and certificates by screening commits for matched strings. An installation script has been provided.

`./bin/installHooks`

### config.yml

Configure your local development parameters by copying the `dev_config/config.yml.dist` file to `dev_config/config.yml`.  Edit `config.yml` as needed.  This file is .gitignore'd.  See comments in the `config.yml.dist` file for more details.

#### MySQL database configuration
Create a new MySQL for this application.  Configure the MySQL connection in `config.yml`.  Then, import the SQL scripts in `/sql` into the new database.
