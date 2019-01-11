# HealthPro Portal and Admin Dashboard

![Build Status of develop](https://circleci.com/gh/all-of-us/healthpro.png)

## Contributions

HealthPro is being developed to facilitate in-person enrollment and other operations of the All of Us Research Program. We are developing this project in the open, and publishing the code with an open-source license, to share knowledge and provide insight and sample code for the community. We welcome your feedback! Please send any security concerns to security@pmi-ops.org, and feel free to file other issues via GitHub. Please note that we do not plan to incorporate external code contributions at this time, given that HealthPro exists to meet the specific operational needs of the All of Us Research Program.

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
* [git-secrets](https://github.com/awslabs/git-secrets/issues/65#issuecomment-446519551)

Install PHP dependencies via Composer:

`composer install`

Install asset and Gulp dependencies via NPM:

`npm install`

Initialize assets and recompile on the fly as assets change:

`./bin/gulp`

Initialize assets, recompile on the fly and reload the browser as assets change (Optional):

`./bin/gulp browser-sync --option localhost:8080`

Run local App Engine dev server:

`./bin/console pmi:deploy --local`

## Credentials and configuration

### config.yml

Configure your local development parameters by copying the `dev_config/config.yml.dist` file to `dev_config/config.yml`.  Edit `config.yml` as needed.  This file is .gitignore'd.  See comments in the dist file for more details.

#### MySQL database configuration
Create a new MySQL for this application.  Configure the MySQL connection in `config.yml`.  Then, import the SQL scripts in `/sql` into the new database.
