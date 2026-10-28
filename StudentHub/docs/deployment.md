# Local deployment with XAMPP (Apache virtual host)

Result: StudentHub opens at **http://studenthub.local**.

## 1. Get the code
```
git clone https://github.com/Snehshahaai/StudentHub.git
```
Copy or clone it to XAMPP's web folder so the path is
`/Applications/XAMPP/xamppfiles/htdocs/StudentHub` (macOS) or `C:\xampp\htdocs\StudentHub` (Windows).

## 2. Database
1. Start **MySQL** in the XAMPP control panel.
2. Import the schema and sample data (⚠️ `schema.sql` drops and recreates `studenthub`):
   ```
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/schema.sql
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/seed.sql
   ```
3. Give the demo accounts passwords (shown once, never stored in the repo):
   ```
   /Applications/XAMPP/xamppfiles/bin/php database/set-passwords.php
   ```
4. Settings live in `php/config.php` (XAMPP defaults: `root`, no password). On a real server override them with the
   `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` environment variables.

## 3. Apache virtual host
1. Open `httpd.conf` and make sure this line is **not** commented out:
   `Include conf/extra/httpd-vhosts.conf`
2. Append the contents of `deploy/studenthub.conf` to `conf/extra/httpd-vhosts.conf` and fix the `DocumentRoot` /
   `<Directory>` paths if yours differ.
3. Map the name to your machine. Edit the hosts file (as administrator / with `sudo`):
   - macOS: `/etc/hosts`  - Windows: `C:\Windows\System32\drivers\etc\hosts`
   ```
   127.0.0.1   studenthub.local
   ```
4. Restart **Apache** in the XAMPP control panel.
5. The poster folder must be writable by Apache: `chmod 775 uploads/events` (macOS/Linux).

## 4. Check it
- http://studenthub.local/ – home page
- http://studenthub.local/php/db-test.php – database connection test (delete or block this file on a public server)
- Log in as `admin@university.edu` with the password printed in step 2.3, then follow `docs/testing.md`.
- Without a database you can still run the offline checks: `bash scripts/verify.sh`

## Without XAMPP (quick demo)
```
php -S localhost:8000 router.php
```
`router.php` applies the same blocking rules as the `.htaccess` files.

## Troubleshooting
| Symptom | Fix |
|---|---|
| "The database is unavailable" | MySQL is not running, or `schema.sql` was not imported |
| Browser says `studenthub.local` not found | hosts entry missing, or browser DNS cache – restart the browser |
| 403 on every page | `<Directory>` path wrong, or `Require all granted` missing |
| Helper PHP files / `database/` open in the browser | `AllowOverride All` is missing, so `.htaccess` is ignored |
| Poster upload fails | `uploads/events` not writable, or PHP `upload_max_filesize` below 2 MB |
