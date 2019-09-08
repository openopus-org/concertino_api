# concertino_api
Classical music metadata API for Apple Muisc

It's the data provider for the Concertino player (https://github.com/adrianosbr/concertino_player).

# Dependencies

This API relies on an utilities library (https://github.com/adrianosbr/openopus_utils). Clone it beforehand.

# Steps to install

1. Clone the git repository (for example, in the /var/www/ folder)
2. Install the data (create a database first, for example, dev_concertino)

```bash
mysql -u USER -p dev_concertino < /var/www/concertino_api/db.sql
```

3. Create an inc.php file from the example:

```bash
cd /var/www/concertino_api/lib/
cp inc-example.php inc.php
vim inc.php
```

4. Set the environment variables for root:

```bash
vim /etc/environment
```

```bash
export CTINHTMLDIR="/var/www/concertino_api/html"
```

5. Update crontab for root

```bash
# m     h       dom     mon     dow     command
0       *       *       *       *       /var/www/concertino_api/cln/db.sh
*/30      *       *       *       *       /var/www/concertino_api/cln/user.sh
```

6. Give ownership of the public directory to the web server group (e.g., www-data):

```bash
chgrp www-data /var/www/concertino_api/html -R
```