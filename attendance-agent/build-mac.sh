#!/bin/bash
# Build a universal macOS disk image (Apple Silicon and Intel) and publish it
# where the attendance download page can serve it.
set -euo pipefail
cd "$(dirname "$0")"

echo "Installing build dependencies..."
npm install

echo "Building universal Mac app (DMG + ZIP). This can take several minutes..."
export CSC_IDENTITY_AUTO_DISCOVERY=false
npm run build:mac

APP="dist/mac-universal/5Core Attendance.app"
if [[ ! -d "$APP" ]]; then
    echo "Build finished without $APP" >&2
    exit 1
fi

echo "Ad-hoc signing the universal app (no Apple Developer ID is configured)..."
codesign --force --deep --sign - "$APP"
codesign --verify --deep --strict --verbose=2 "$APP"

echo "Packing signed DMG and ZIP..."
STAGE="$(mktemp -d)"
cp -R "$APP" "$STAGE/"
ln -s /Applications "$STAGE/Applications"
hdiutil create -volname "5Core Attendance" -srcfolder "$STAGE" -ov -format UDZO "dist/5Core-Attendance-Mac.dmg"
rm -rf "$STAGE"
ditto -c -k --keepParent "$APP" "dist/5Core-Attendance-Mac.zip"

# Publishing through artisan records which version was actually shipped. Copying the
# files by hand is what let the portal advertise a build nobody could download.
echo "Publishing to public/downloads and recording the release..."
php ../artisan attendance:agent-publish
