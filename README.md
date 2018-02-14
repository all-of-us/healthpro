# HealthPro Portal and Admin Dashboard

![Build Status of master](https://circleci.com/gh/vanderbilt/pmi-drc-hpo.png?circle-token=17ce7a55825cb047e685c2376d7e33441a07c590)

## Developer Quick Start

Prerequisites:

* [Google Cloud and App Engine SDK](https://cloud.google.com/appengine/docs/standard/php/download)
    1. Download Google Cloud SDK
    2. Run `install.sh` so that `gcloud` is available from path
    3. Run `gcloud init`
    4. Run `gcloud components install app-engine-php` to install App Engine PHP SDK
* [NodeJS](https://nodejs.org/) (latest LTS should be fine)
* [MySQL](https://dev.mysql.com/downloads/mysql/) (version 5.7 used by Google Cloud SQL)
* [Composer](https://getcomposer.org/doc/00-intro.md#globally)

Install PHP dependencies via Composer:

`composer install`

Install asset and Gulp dependencies via NPM:

`npm install`

Initialize assets and recompile on the fly as assets change:

`./bin/gulp`

Run local App Engine dev server:

`./bin/console pmi:deploy --local`

## Credentials and configuration

### config.yml

Configure your local development parameters by copying the `dev_config/config.yml.dist` file to `dev_config/config.yml`.  Edit `config.yml` as needed.  This file is .gitignore'd.  See comments in the dist file for more details.

#### MySQL database configuration
Create a new MySQL for this application.  Configure the MySQL connection in `config.yml`.  Then, import the SQL scripts in `/sql` into the new database.
