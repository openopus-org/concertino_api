# concertino_api
[Concertino](https://getconcertino.com) is a classical music front-end for Apple Music.

It's splitted in several projects. **This one provides only the data API**, used by our [HTML/JS client](https://github.com/openopus-org/concertino_player). It's written in PHP and relies on a MySQL database. It consumes the Apple Music API, therefore it can't be used by more than one client. So, if you plan to use the Concertino API in your app, you'll have to fork it beforehand.

## Usage

It's a public RESTful API which returns data in JSON format. There is a [wiki](https://wiki.openopus.org/wiki/Using_the_Concertmaster/Concertino_API) explaining all endpoints and data structures. No registration needed, but create/edit/delete endpoints require simple authentication.

## How to build

1. Fork and clone this git repository to your webserver (for example, in the `/var/www/` folder)
2. The Concertmaster API uses a shared [Open Opus utilities library](https://github.com/openopus-org/openopus_utils). Clone it in the same directory
3. Create a MySQL database (for example, `app_concertino`) and load its strucuture

```bash
mysql -u USER -p app_concertino < /var/www/concertino_api/sql/app_concertino.sql
```

4. Create an `inc.php` file from the example:

```bash
cd /var/www/concertino_api/lib/
cp inc-example.php inc.php
vim inc.php
```
5. Change variable values in the `lib/inc.php` accordingly to your webserver and Apple Music developer token. Learn on how to create one [here](https://developer.apple.com/documentation/applemusicapi/getting_keys_and_creating_tokens). Good luck!

### Cache and cache cleaning routines

The Concertino API can cache its results, saving server resources. In order to activate this feature you must:

1. Give ownership of the public directory to the web server group (e.g., `www-data`):

```bash
chgrp www-data /var/www/concertino_api/html -R
```
2. Set the environment variables for root:

```bash
vim /etc/environment
```

```bash
export BASEHTMLDIR="/var/www/concertino_api/html"
```

3. Update crontab for root (this will set the cache cleaning routines)

```bash
# m     h       dom     mon     dow     command
0       *       *       *       *       /var/www/concertino_api/cln/db.sh
*/30      *       *       *       *       /var/www/concertino_api/cln/user.sh
```

## Domains

There is a single public directory in the project (`/html`) and it must have its own virtual host on your webserver. (For example, we host it at [api.concertino.app](https://api.concertino.app).)

The API *must* be served with HTTPS. You can use a free [Let's Encrypt](https://letsencrypt.org/)-provided certificate, it's perfectly fine.

### CORS Settings

Due to the Apple Music API restriction, the Concertino API may accept requests from trusted domains only. We have chosen to implement this through [CORS](https://medium.com/@baphemot/understanding-cors-18ad6b478e2b) (cross-origin resource sharing). The following configuration works for Apache and must be placed inside your `<virtualhost>` directive:

```
SetEnvIf Origin ^(https?://(?:.+\.)?(concertino\.app|cncert\.in)(?::\d{1,5})?)$   CORS_ALLOW_ORIGIN=$1
Header append Access-Control-Allow-Origin  %{CORS_ALLOW_ORIGIN}e   env=CORS_ALLOW_ORIGIN
Header merge  Vary "Origin"
```

Change the domains above to the ones your app will use.

#### CORS in development environments

In order the make the API available to a local, dev application, edit the `html/.htaccess` file and change the domain for the one your dev environment will use.

## Contributing with code
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## Contributing with data
Concertino composers and works information come from [Open Opus](https://openopus.org), a free, wiki-style, open source database of classical music metadata. You can review existing info or add new entries to the database - any help will be appreciated!

## Contributing with money
Concertino is free to use but it runs on web servers that cost us money. You can help us by supporting us on [Patreon](https://www.patreon.com/openopus) - any amount is more than welcome!

## License
[GPL v3.0](https://choosealicense.com/licenses/gpl-3.0/)