# Deploying to cPanel

Examples use the address `pools.example.com`, the cPanel user `USER` and the folder `/home/USER/dnn-pools`.
Replace them with yours.

## Before you start

Check that your hosting has all of these:

| Need | Where to look in cPanel |
|---|---|
| **PHP 8.4.1 or newer** (a hard requirement) | MultiPHP Manager, or Select PHP Version |
| PHP extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `dom`, `ctype`, `filter`, `iconv`, `tokenizer` | Select PHP Version → Extensions, or ask the host |
| A MySQL or MariaDB database | MySQL Databases |
| **Terminal** or SSH access | Terminal |
| A subdomain whose folder you can choose | Domains |
| An SSL certificate | SSL/TLS Status (AutoSSL) |

HTTPS is not optional: the Android app refuses plain `http://`.

No Terminal? Ask the host to enable it. The database setup and the first administrator both need it.
Do not work around this with a web page that runs artisan commands.

The server does not need Node, Composer, a cron job or a queue worker.

## 1. Build the package (on your PC)

```powershell
cd D:\dnn-pools-backend
```

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\package-cpanel.ps1
```

This creates `dist\dnn-pools-cpanel.zip` with the built styles and the production PHP packages.
It leaves out your `.env`, your local database, the tests and `public\hot`.

Do not upload the project folder by hand instead. `public\hot` would make the live site load its
styles from your PC, and your `.env` holds your own keys and mail password.

## 2. Create the subdomain

1. **Domains** → **Create A New Domain** → `pools.example.com`.
2. Untick "Share document root" and set the document root to **`dnn-pools/public`**.

The document root must be the `public` folder. Everything above it, including `.env`, then stays
private. Do not put the application inside `public_html`. If cPanel will not let you choose the
folder (this happens with the main domain), use a subdomain.

Then:

- **MultiPHP Manager**: set `pools.example.com` to PHP 8.4.
- **SSL/TLS Status**: run AutoSSL for it.
- **Domains**: switch on **Force HTTPS Redirect**.

## 3. Create the database

In **MySQL Databases**, create a database and a user, then add the user to the database with
**All Privileges**. cPanel prefixes both names with your username, for example `USER_pools`.

## 4. Upload

In **File Manager**, upload `dnn-pools-cpanel.zip` to `/home/USER/dnn-pools` and extract it there.
`artisan` must end up at `/home/USER/dnn-pools/artisan`. Delete the zip afterwards.

To see `.env` and `.htaccess`, turn on **Settings → Show Hidden Files**.

## 5. Create `.env`

Create `/home/USER/dnn-pools/.env` with this content:

```ini
APP_NAME=Dnn-Pool-Tables-Tech
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pools.example.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=USER_pools
DB_USERNAME=USER_pools
DB_PASSWORD="the database password"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

# A mailbox created under cPanel "Email Accounts". Only password-reset mail uses it.
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.example.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@example.com
MAIL_PASSWORD="the mailbox password"
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"

POOL_TIMEZONE=Africa/Nairobi
POOL_APP_KEYS=
```

- For port 587 instead of 465, set `MAIL_SCHEME=smtp`. This Laravel version ignores `MAIL_ENCRYPTION`.
- Put passwords in double quotes if they contain `#`, spaces or other symbols.
- `APP_DEBUG` must stay `false`. When it is `true`, error pages show your settings to visitors.

## 6. Set up (in cPanel Terminal)

The `php` command in the terminal is often older than the website's PHP. Check first:

```bash
php -v
```

If it is below 8.4.1, use the full path in every command below. It is usually
`/opt/cpanel/ea-php84/root/usr/bin/php`, or `/opt/alt/php84/usr/bin/php` on CloudLinux.

```bash
cd ~/dnn-pools
chmod 600 .env
chmod -R u+rwX storage bootstrap/cache
php artisan key:generate --force
```

Generate the app key, then paste the output after `POOL_APP_KEYS=` in `.env`:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Create the tables, cache the settings and create your administrator account:

```bash
php artisan migrate --force
php artisan optimize
php artisan app:system-admin you@example.com
```

In production, every password (yours, and those set on the dashboards) needs at least 12 characters
with upper and lower case letters, a number and a symbol, and must not appear in known password leaks.

**After any change to `.env`, run `php artisan optimize` again.** Until you do, the change has no effect.

The live database starts empty. Sign in, then create your institutions and directors.

## 7. Check it

| Check | Expected |
|---|---|
| `https://pools.example.com/up` | the page loads |
| `https://pools.example.com/login` | styled sign-in page with the 8-ball logo |
| `https://pools.example.com/.env` | "Page not found" or "Forbidden", **never** the file's text |
| `https://pools.example.com/register` | "Page not found" |
| Sign in as your administrator | the platform overview |

## 8. Point the Android app at the server

In the Flutter project's `app/app.env`:

```ini
API_BASE_URL=https://pools.example.com
APP_API_KEY=the same value as POOL_APP_KEYS
```

Do not add `/api/v1`; the app adds it. Rebuild and reinstall the APK, then sign in on a phone,
start a game and confirm that it appears on the dashboard.

To replace the app key later, set `POOL_APP_KEYS=newkey,oldkey`, run `php artisan optimize`, update
the phones, then remove the old key.

## Updating later

1. Build a new package (step 1) and upload the zip to `/home/USER/dnn-pools`.
2. Back up the database (**phpMyAdmin → Export**).
3. In Terminal:

```bash
cd ~/dnn-pools
php artisan down --render="errors::503"
rm -rf app config database/migrations resources routes vendor public/build bootstrap/cache/*.php
unzip -oq dnn-pools-cpanel.zip && rm dnn-pools-cpanel.zip
php artisan migrate --force
php artisan optimize
php artisan up
```

Check that the zip is in place before running these commands: the third one removes the old code.
`.env` and `storage` are kept. While the site is down, phones keep their games queued and send them later.

## Backups

Back up two things:

- **The database.**
- **`.env`**, above all `APP_KEY`. Without that key, nobody who uses two-factor sign-in can complete it.

Never run `key:generate` again on a live site.

## If something goes wrong

The log is in `storage/logs/`.

| Symptom | Likely cause |
|---|---|
| Error 500 on every page | wrong PHP version, wrong database settings, or `storage` not writable; read the log |
| "Your Composer dependencies require a PHP version" | the website or terminal PHP is below 8.4.1 |
| A directory listing, or "403 Forbidden" on the home page | the document root is not `dnn-pools/public` |
| Every page except the home page is "Not Found" | `public/.htaccess` is missing |
| Pages have no styling | `public/build` is missing, or a `public/hot` file was uploaded (delete it) |
| A `.env` change does nothing | run `php artisan optimize` |
| App: "app_key_not_configured" (503) | `POOL_APP_KEYS` is empty |
| App: "invalid_app_key" (403) | `APP_API_KEY` in the APK differs from `POOL_APP_KEYS`, or the APK was not rebuilt |
| App cannot connect at all | no valid SSL certificate, or `API_BASE_URL` does not start with `https://` |
| Passkey sign-in fails | `APP_URL` does not match the address in the browser exactly |
| Password-reset mail never arrives | check the `MAIL_*` settings and the log |

**Using Cloudflare or another proxy?** Say so before going live. The app does not yet trust proxy
headers, so it would see the proxy's address instead of the visitor's, which affects sign-in limits.
