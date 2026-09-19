# Invent Chat Android (TWA)

Package ID (locked): `com.fivecore.invent.chat`

Laravel is the backend. `/chat` is the UI. This folder only wraps the existing HTTPS PWA. Do not create a second chat app.

Start URL: `https://inventory.5coremanagement.com/chat`

## Current folder

Checked on this machine: only `twa-manifest.json`, this README, and `.gitignore`. There is **no** generated `android/` project yet.

- `bubblewrap init` will **create** `android/` the first time. That is expected.
- Run `bubblewrap init` again later only if `android/` is missing. If `android/` already exists, use `bubblewrap update` instead. Do not re-init over an existing project.

Bubblewrap CLI was **not** installed globally here (`bubblewrap` not on PATH). Install it before building.

## 1. Generate a release keystore (local only)

Create the keystore **outside the repo**. Do not commit it.

```bash
mkdir -p "$HOME/android-keys"
keytool -genkeypair \
  -v \
  -keystore "$HOME/android-keys/invent-chat-release.keystore" \
  -alias invent-chat \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000 \
  -storetype JKS
```

`keytool` will prompt for store password, key password, and certificate name. Keep those passwords in a password manager. Never put them in Git.

## 2. SHA-256 fingerprint

```bash
keytool -list -v \
  -keystore "$HOME/android-keys/invent-chat-release.keystore" \
  -alias invent-chat
```

Copy the `SHA256:` line (colon-separated hex). Put that value into:

`public/.well-known/assetlinks.json`

Leave `REPLACE_WITH_UPLOAD_OR_APP_SIGNING_SHA256` until you have the real fingerprint. Do not invent one.

If Play App Signing is on, also add Play’s **App signing key certificate** SHA-256 as a second fingerprint.

## 3. Digital Asset Links

Public file (no login):

`https://inventory.5coremanagement.com/.well-known/assetlinks.json`

Must include:

- `package_name`: `com.fivecore.invent.chat`
- `sha256_cert_fingerprints`: real release certificate(s)

After deploy:

```bash
curl -sI https://inventory.5coremanagement.com/.well-known/assetlinks.json
curl -s https://inventory.5coremanagement.com/.well-known/assetlinks.json
```

Expect HTTP 200, `Content-Type: application/json`, no login redirect.

## 4. Bubblewrap

```bash
npm install -g @bubblewrap/cli
cd android-twa
bubblewrap init --manifest=./twa-manifest.json
```

When Bubblewrap asks for the keystore, point it at `$HOME/android-keys/invent-chat-release.keystore` and alias `invent-chat`.

If `android/` already exists:

```bash
cd android-twa
bubblewrap update
```

Build (uses the keystore Bubblewrap stored in the generated project):

```bash
cd android-twa
bubblewrap build
```

## 5. APK / AAB

After `bubblewrap init`, Gradle lives in `android-twa/android/`.

Debug APK (not for Play):

```bash
cd android-twa/android
./gradlew assembleDebug
```

Release APK:

```bash
cd android-twa/android
./gradlew assembleRelease
```

Release AAB (Play Store artifact):

```bash
cd android-twa/android
./gradlew bundleRelease
```

Typical outputs:

- `android/app/build/outputs/apk/debug/app-debug.apk`
- `android/app/build/outputs/apk/release/app-release.apk`
- `android/app/build/outputs/bundle/release/app-release.aab`

Do not commit APK/AAB files.

## 6. Production Laravel deploy

After `git pull` on the server:

```bash
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com
php artisan view:clear
php artisan config:clear
```

Then replace the SHA-256 placeholder in `public/.well-known/assetlinks.json` if that file is still a placeholder.

Confirm:

```bash
curl -sI https://inventory.5coremanagement.com/manifest.json
curl -sI https://inventory.5coremanagement.com/sw.js
curl -sI https://inventory.5coremanagement.com/offline.html
curl -sI https://inventory.5coremanagement.com/images/pwa-icon-192.png
curl -sI https://inventory.5coremanagement.com/images/pwa-icon-512.png
curl -sI https://inventory.5coremanagement.com/chat
```
