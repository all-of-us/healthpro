# PMI Provider Portal and Admin Dashboard

## Developer Quick Start

Install the [Google App Engine SDK for PHP](https://cloud.google.com/appengine/downloads).

Make GAE SDK not watch too many files:

`./bin/patchWatcher`

Install PHP dependencies via [Composer](https://getcomposer.org/doc/00-intro.md#globally):

`composer install`

Install Gulp dependencies via NPM:

`./bin/npm install`

Install asset dependencies via Bower:

`./bin/bower install`

Initialize assets and recompile on the fly as assets change:

`./bin/gulp`

Run local App Engine dev server:

`./bin/console pmi:deploy --local`
 
### Datastore persistence
By default, the local server stores Datastore data in a temporary location that can be wiped out at any time.  In order to maintain persistent Datastore locally, create a directory to store the data in, and specify the `datastoreDir` parameter:

`./bin/console pmi:deploy --local --datastoreDir=~/datastore`


## Credentials and configuration

### config.yml

The easiest way to configure your local development parameters is copying the `dev_config/config.yml.dist` file to `dev_config/config.yml`.  Edit `config.yml` as needed.  This file is .gitignore'd.  See comments in the dist file for more details.

### Configuration datastore entities
Alternatively, you can set up configuration parameters using Configuration datastore entities.  The GAE SDK local server has a data store interface that runs by default on [port 8000](http://localhost:8000/datastore).  However, there is no way to manually create the first entity of a type.  There is a route accessible only in dev and test that creates this first Configuration entity:

1. Log in as an admin to [http://localhost:8080/_ah/login](http://localhost:8080/_ah/login)
2. Go to [http://localhost:8080/_dev/datastore-init](http://localhost:8080/_dev/datastore-init)

#### MySQL database configuration
Configure your local MySQL database connection by creating the following Configuration entities:

* `mysql_host`
* `mysql_schema` (database name)
* `mysql_user`
* `mysql_password`
