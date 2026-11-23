#!/usr/bin/env bash
# Vendors EmulatorJS runtime and the cores used by You Emulator.
# Usage: bin/fetch-emulatorjs.sh [--no-cores] [--from-fork DIR]
set -euo pipefail

VERSION="${EJS_VERSION:-4.2.3}"
CDN="https://cdn.emulatorjs.org/${VERSION}/data"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="${ROOT}/vendor/emulatorjs/data"
CORES=(fceumm nestopia snes9x genesis_plus_gx picodrive mgba mednafen_psx_hw pcsx_rearmed ppsspp mupen64plus_next parallel_n64)
WITH_CORES=1
FORK=""

while [ $# -gt 0 ]; do
	case "$1" in
		--no-cores) WITH_CORES=0 ;;
		--from-fork) FORK="$2"; shift ;;
		*) echo "Unknown option $1" >&2; exit 1 ;;
	esac
	shift
done

mkdir -p "${DEST}/cores/reports" "${DEST}/compression" "${DEST}/localization"

fetch() {
	curl -fsSL --retry 3 -o "${DEST}/$1" "${CDN}/$1"
}

if [ -n "${FORK}" ]; then
	cp "${FORK}/data/loader.js" "${FORK}/data/emulator.min.js" "${FORK}/data/emulator.min.css" "${FORK}/data/version.json" "${DEST}/"
	cp "${FORK}"/data/compression/*.js "${FORK}"/data/compression/*.wasm "${DEST}/compression/"
	cp "${FORK}"/data/localization/*.json "${DEST}/localization/"
	cp "${FORK}/LICENSE" "${DEST}/LICENSE"
else
	for f in loader.js emulator.min.js emulator.min.css version.json \
		compression/extract7z.js compression/extractzip.js compression/libunrar.js compression/libunrar.wasm; do
		fetch "$f"
	done
	for l in en-US ru-RU zh-CN de-GER es-ES it-IT ja-JA ko-KO pt-BR; do
		fetch "localization/${l}.json"
	done
	curl -fsSL -o "${DEST}/LICENSE" "https://raw.githubusercontent.com/EmulatorJS/EmulatorJS/v${VERSION}/LICENSE"
fi

if [ "${WITH_CORES}" = 1 ]; then
	for core in "${CORES[@]}"; do
		fetch "cores/reports/${core}.json"
		for variant in "" "-legacy" "-thread" "-thread-legacy"; do
			file="cores/${core}${variant}-wasm.data"
			if curl -fsSL --retry 3 -o "${DEST}/${file}" "${CDN}/${file}" 2>/dev/null; then
				echo "  ${file}"
			else
				rm -f "${DEST}/${file}"
			fi
		done
	done
	fetch "cores/ppsspp-assets.zip"
fi

echo "EmulatorJS ${VERSION} vendored to ${DEST}"
