## Developer Quick Start

Install the [Google App Engine SDK for PHP](https://cloud.google.com/appengine/downloads).

Install PHP dependencies via Composer:

`composer install`

Install Gulp dependencies via NPM:

`./bin/npm install`

Install asset dependencies via Bower:

`./bin/bower install`

Initialize assets and recompile on the fly as assets change:

`./bin/gulp`

Run local App Engine dev server:

`./bin/console pmi:deploy --local`
 
**NOTE** persist your Datastore with: `./bin/console pmi:deploy --local --datastoreDir=~/datastore`
