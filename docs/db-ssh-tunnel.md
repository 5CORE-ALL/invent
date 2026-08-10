# Local MySQL via SSH tunnel

Connect the local Laravel app on macOS to the remote MySQL/MariaDB on `31.59.184.74` **without** exposing port `3306` on the public Internet.

## Architecture

```text
LOCAL MAC
127.0.0.1:3307
  ↓
SSH tunnel (port 22)
  ↓
31.59.184.74
  ↓
127.0.0.1:3306  (MySQL bound locally on the server)
```

## Prerequisites

1. SSH username for the server (project default: `root`).
2. **Key-based SSH auth** from your Mac to the server (no password in repo/scripts).
3. Remote MySQL listening on `127.0.0.1:3306` (or localhost socket equivalent reachable as `127.0.0.1:3306`).
4. Local `.env` database name/user/password matching the **remote** database.

### Authorize your Mac SSH key (one-time)

On your Mac:

```bash
cat ~/.ssh/id_ed25519.pub
```

On the server (interactive SSH session), append that public key line to `~/.ssh/authorized_keys`, then:

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

Verify non-interactive login:

```bash
ssh -o BatchMode=yes root@31.59.184.74 'echo SSH_OK'
```

## Local Laravel `.env`

```env
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=<remote database name>
DB_USERNAME=<remote database user>
DB_PASSWORD=<remote database password>
```

Do **not** change the production server `.env`. Do **not** open `3306` in UFW/firewall/security groups/FastPanel.

## Commands

### Start tunnel

```bash
./scripts/db-tunnel.sh
```

Foreground (optional):

```bash
./scripts/db-tunnel.sh foreground
```

Exact underlying SSH command:

```bash
ssh -N -L 3307:127.0.0.1:3306 \
  -o ExitOnForwardFailure=yes \
  -o ServerAliveInterval=60 \
  -o ServerAliveCountMax=3 \
  -o StrictHostKeyChecking=yes \
  -o PasswordAuthentication=no \
  -o BatchMode=yes \
  root@31.59.184.74
```

### Check tunnel

```bash
lsof -i :3307
# or
./scripts/db-tunnel.sh status
```

### Stop tunnel

```bash
./scripts/db-tunnel.sh stop
```

### Test MySQL connectivity

```bash
mysql -h 127.0.0.1 -P 3307 -u "$DB_USERNAME" -p "$DB_DATABASE"
```

### Test Laravel

```bash
php artisan config:clear
php artisan tinker
```

Then:

```php
DB::connection()->getPdo();
```

## Security notes

- Private keys and passwords must stay on your machine / password manager — never commit them.
- `.env` is gitignored.
- Host key verification stays enabled (`StrictHostKeyChecking=yes`).
- MySQL remains bound to localhost on the server; only SSH (22) is used from outside.
