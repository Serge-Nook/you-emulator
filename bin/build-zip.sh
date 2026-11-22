#!/usr/bin/env bash
# Builds the distributable plugin ZIP into build/.
# Usage: bin/build-zip.sh [--skip-fetch]
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=you-emulator
VERSION=$(sed -n "s/^define( 'YOUEM_VERSION', '\([^']*\)' );/\1/p" you-emulator.php)
if [ "${1:-}" != "--skip-fetch" ]; then
	bin/fetch-emulatorjs.sh
fi
for f in fceumm snes9x genesis_plus_gx mgba pcsx_rearmed ppsspp-thread mupen64plus_next; do
	ls vendor/emulatorjs/data/cores/${f}-wasm.data >/dev/null
done
rm -rf build && mkdir -p "build/${SLUG}"
cp -r you-emulator.php uninstall.php README.md LICENSE admin includes languages public templates "build/${SLUG}/"
mkdir -p "build/${SLUG}/vendor" && cp -r vendor/emulatorjs "build/${SLUG}/vendor/"
find "build/${SLUG}" -name '.DS_Store' -delete
(cd build && zip -qr9 "${SLUG}-${VERSION}.zip" "${SLUG}")
sha256sum "build/${SLUG}-${VERSION}.zip" | tee "build/${SLUG}-${VERSION}.zip.sha256"
