# Deploying to cPanel from GitHub

The live site is `https://pools.dnncreatives.com`. Replace `USER` with your cPanel username.

```
GitHub  --pull-->  /home/USER/dnn-pools-backend/          the whole app (private)
                                            └── public/    pools.dnncreatives.com points here
```

cPanel keeps one copy of the repository, and the website runs from its `public` folder. Nothing is
copied between folders. The **Deploy HEAD Commit** button runs `.cpanel.yml`, which calls
`scripts/cpanel-deploy.sh`: it installs the PHP packages, updates the database and refreshes the caches.

| Part | Comes from |
|---|---|
| The code | GitHub |
| Styles and scripts (`public/build`) | GitHub: you build them on your PC and commit them |
| PHP packages (`vendor`) | Composer, run on the server |
| Settings and secrets (`.env`) | created once on the server, never in GitHub |

The server needs no Node, cron job or queue worker.

## Before every push (on your PC)

If you changed anything under `resources/` (pages, styles or scripts), or updated packages, build
first and commit the result:

```powershell
npm run build
```

```powershell
git add -A public/build
```

If you skip this, the live site keeps the old styles, and new designs can look broken. Two branches
that both rebuilt may conflict in `public/build`; after merging, run the two commands again.

## Hosting requirements

| Need | Where to look in cPanel |
|---|---|
| **PHP 8.4.1 or newer** (a hard requirement) | MultiPHP Manager, or Select PHP Version |
| PHP extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `dom`, `ctype`, `filter`, `iconv`, `tokenizer` | Select PHP Version → Extensions, or ask the host |
| A MySQL or MariaDB database | MySQL Databases |
| **Git Version Control** | Files |
| **Terminal** or SSH access, with Composer | Terminal |
| An SSL certificate | SSL/TLS Status (AutoSSL) |

HTTPS is not optional: the Android app refuses plain `http://`.

No Terminal? Ask the host to enable it. Do not work around this with a web page that runs commands.

### The PHP and Composer commands

The `php` command in the terminal is often older than the website's PHP. Check with `php -v`.
This guide writes `$PHP` and `$COMPOSER`. Set them each time you open Terminal:

```bash
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
COMPOSER=/opt/cpanel/composer/bin/composer
```

These are the usual cPanel paths. On CloudLinux, PHP is often `/opt/alt/php84/usr/bin/php`. If a path
does not exist, ask your host.

# First-time setup

## 1. Clone the repository

**Git Version Control** → **Create**:

- Turn on **Clone a Repository**.
- **Clone URL:** `https://github.com/IsaacNyamari/dnn-pools-backend.git`
- **Repository Path:** `/home/USER/dnn-pools-backend`, a new or empty folder outside `public_html`.
- Press **Create**.

The repository is public, so no key is needed. If you make it private, follow cPanel's
[access to private repositories](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-set-up-access-to-private-repositories/)
guide to add an SSH deploy key (read access is enough), and clone with
`git@github.com:IsaacNyamari/dnn-pools-backend.git`.

## 2. Point the subdomain at `public`

1. **Domains** → `pools.dnncreatives.com` → **Manage**.
2. Set **New Document Root** to `dnn-pools-backend/public` and press **Update**.

The document root must be the `public` folder. Everything above it, including `.env`, then stays
private. The folder cPanel first made for the subdomain is no longer used.

Then:

- **MultiPHP Manager**: set `pools.dnncreatives.com` to PHP 8.4.
- **SSL/TLS Status**: run AutoSSL for it.
- **Domains**: switch on **Force HTTPS Redirect**.

## 3. Install the PHP packages

Set `$PHP` and `$COMPOSER` as shown above, then:

```bash
cd ~/dnn-pools-backend
$PHP $COMPOSER install --no-dev --optimize-autoloader
```

## 4. Create the database

In **MySQL Databases**, create a database and a user, then add the user to the database with
**All Privileges**. cPanel prefixes both names with your username, for example `USER_pools`.

## 5. Create `.env`

In **File Manager** (turn on **Settings → Show Hidden Files**), create
`/home/USER/dnn-pools-backend/.env`:

```ini
APP_NAME=Dnn-Pool-Tables-Tech
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pools.dnncreatives.com

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
MAIL_HOST=mail.dnncreatives.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@dnncreatives.com
MAIL_PASSWORD="the mailbox password"
MAIL_FROM_ADDRESS=no-reply@dnncreatives.com
MAIL_FROM_NAME="${APP_NAME}"

POOL_TIMEZONE=Africa/Nairobi
POOL_APP_KEYS=
```

- Check the mail server name and mailbox under **Email Accounts → Connect Devices**.
- For port 587 instead of 465, set `MAIL_SCHEME=smtp`. This Laravel version ignores `MAIL_ENCRYPTION`.
- Put passwords in double quotes if they contain `#`, spaces or other symbols.
- `APP_DEBUG` must stay `false`. When it is `true`, error pages show your settings to visitors.
- Never commit this file. The repository is public.

## 6. Finish in Terminal

```bash
cd ~/dnn-pools-backend
chmod 600 .env
chmod -R u+rwX storage bootstrap/cache
$PHP artisan key:generate --force
```

Generate the app key, then paste the output after `POOL_APP_KEYS=` in `.env`:

```bash
$PHP -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Create the tables, cache the settings and create your administrator account:

```bash
$PHP artisan migrate --force
$PHP artisan optimize
$PHP artisan app:system-admin you@example.com
```

In production, every password (yours, and those set on the dashboards) needs at least 12 characters
with upper and lower case letters, a number and a symbol, and must not appear in known password leaks.

**After any change to `.env`, run `$PHP artisan optimize` again.** Until you do, the change has no effect.
Later updates need only the buttons described under "Updating the live site".

The live database starts empty. Sign in, then create your institutions and directors.

## 7. Check it

| Check | Expected |
|---|---|
| `https://pools.dnncreatives.com/up` | the page loads |
| `https://pools.dnncreatives.com/login` | styled sign-in page with the 8-ball logo |
| `https://pools.dnncreatives.com/.env` | "Page not found" or "Forbidden", **never** the file's text |
| `https://pools.dnncreatives.com/register` | "Page not found" |
| Sign in as your administrator | the platform overview |

## 8. Point the Android app at the server

In the Flutter project's `app/app.env`:

```ini
API_BASE_URL=https://pools.dnncreatives.com
APP_API_KEY=the same value as POOL_APP_KEYS
```

Do not add `/api/v1`; the app adds it. Rebuild and reinstall the APK, then sign in on a phone,
start a game and confirm that it appears on the dashboard.

To replace the app key later, set `POOL_APP_KEYS=newkey,oldkey`, run `$PHP artisan optimize`, update
the phones, then remove the old key.

# Updating the live site

1. On your PC, build if needed (see "Before every push"), commit, and push to `main`.
2. If the database changes, back it up (**phpMyAdmin → Export**).
3. **Git Version Control** → **Manage** → **Pull or Deploy**. The checked-out branch should be `main`.
4. Press **Update from Remote**, wait for it to finish, then press **Deploy HEAD Commit** straight away.

Deploying puts the site into maintenance mode, installs the packages, updates the database, refreshes
the caches and brings the site back. While it is down, phones keep their games queued.

cPanel shows little about what happened. Its log is usually the newest `vc_*_git_deploy.log` in
`/home/USER/.cpanel/logs/`. If the site stays in maintenance mode, a step failed: read that log, or run
the same script in Terminal to see the messages:

```bash
cd ~/dnn-pools-backend
sh scripts/cpanel-deploy.sh
```

**Do not edit or build code on the server.** `.env` and `storage` are safe to change, because git
ignores them. Other changes can stop the next pull or deployment.

## When cPanel says "The system cannot deploy"

cPanel shows its two requirements and disables **Deploy HEAD Commit**.

1. **A valid `.cpanel.yml` file exists.** It must be in the server's copy: push it to `main`, then
   press **Update from Remote**.
2. **No uncommitted changes exist.** A file differs on the server. See which one:

   ```bash
   cd ~/dnn-pools-backend
   git status
   ```

| What `git status` lists | Fix |
|---|---|
| `modified: public/.htaccess` (MultiPHP Manager adds lines to it) | `git update-index --skip-worktree public/.htaccess` keeps the server's lines and hides the difference |
| Another file listed as modified, which you did not mean to change | `git diff FILE` to look, then `git restore FILE` to put back GitHub's version |
| An untracked file you uploaded yourself | move it out of the repository folder |

If you later change `public/.htaccess` on GitHub after using `skip-worktree`, the pull will refuse. Run
`git update-index --no-skip-worktree public/.htaccess`, `git stash`, update, then `git stash pop`.


## Backups

Back up two things:

- **The database.**
- **`.env`**, above all `APP_KEY`. Without that key, nobody who uses two-factor sign-in can complete it.

Never run `key:generate` again on a live site.

## If something goes wrong

The log is in `storage/logs/`.

| Symptom | Likely cause |
|---|---|
| Update fails: "untracked working tree files would be overwritten" under `public/build` | you uploaded that folder by hand earlier: delete `public/build` on the server and update again |
| Update fails: "local changes would be overwritten" | a file was changed on the server: see "The system cannot deploy" above |
| Update fails: "Not possible to fast-forward" | history on GitHub was rewritten, or a commit was made on the server |
| Error 500 on every page | wrong PHP version, wrong database settings, packages not installed, or `storage` not writable; read the log |
| "Your Composer dependencies require a PHP version" | the website or terminal PHP is below 8.4.1 |
| A directory listing, or "403 Forbidden" on the home page | the document root is not `dnn-pools-backend/public` |
| Every page except the home page is "Not Found" | `public/.htaccess` is missing |
| Pages have no styling, or old styling | `public/build` was not built and committed before the push |
| A `.env` change does nothing | run `$PHP artisan optimize` |
| App: "app_key_not_configured" (503) | `POOL_APP_KEYS` is empty |
| App: "invalid_app_key" (403) | `APP_API_KEY` in the APK differs from `POOL_APP_KEYS`, or the APK was not rebuilt |
| App cannot connect at all | no valid SSL certificate, or `API_BASE_URL` does not start with `https://` |
| Passkey sign-in fails | `APP_URL` does not match the address in the browser exactly |
| Password-reset mail never arrives | check the `MAIL_*` settings and the log |

**Using Cloudflare or another proxy?** Sort this out before going live. The app does not yet trust
proxy headers, so it would see the proxy's address instead of the visitor's, which affects sign-in limits.

## Without Git or Composer on the server

`scripts\package-cpanel.ps1` builds `dist\dnn-pools-cpanel.zip`, which already contains the PHP
packages and built styles. Upload and extract it into the application folder instead of steps 1 and 3.
Do not mix this with the git method in the same folder.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\package-cpanel.ps1
```
