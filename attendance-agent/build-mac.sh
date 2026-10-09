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

DMG="dist/5Core-Attendance-Mac.dmg"
ZIP="dist/5Core-Attendance-Mac.zip"

mkdir -p ../public/downloads
if [[ -f "$DMG" ]]; then
    cp -f "$DMG" ../public/downloads/5core-attendance-mac.dmg
    chmod 644 ../public/downloads/5core-attendance-mac.dmg
    echo "Published public/downloads/5core-attendance-mac.dmg"
fi
if [[ -f "$ZIP" ]]; then
    cp -f "$ZIP" ../public/downloads/5core-attendance-mac.zip
    chmod 644 ../public/downloads/5core-attendance-mac.zip
    echo "Published public/downloads/5core-attendance-mac.zip"
fi
