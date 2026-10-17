#!/usr/bin/env bash
# Regenera los íconos de la PWA, el apple-touch-icon, el favicon y el PNG del README
# a partir de los SVG de public/img. Necesita un Chromium headless (el de Playwright sirve)
# y Python con Pillow para el .ico.
#
# Uso: deploy/branding/generar-iconos.sh [ruta-a-chromium]
set -euo pipefail
cd "$(dirname "$0")/../.."

CHROME="${1:-${CHROME:-}}"
if [ -z "$CHROME" ]; then
  for c in ~/.cache/ms-playwright/chromium_headless_shell-*/chrome-headless-shell-linux64/chrome-headless-shell \
           ~/.cache/ms-playwright/chromium-*/chrome-linux*/chrome /usr/bin/chromium /usr/bin/chromium-browser /usr/bin/google-chrome; do
    if [ -x "$c" ]; then CHROME="$c"; break; fi
  done
fi
[ -x "${CHROME:-}" ] || { echo "No encontré Chromium. Pásalo como argumento." >&2; exit 1; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# render <svg> <ancho> <alto> <salida.png>  (fondo transparente)
render() {
  "$CHROME" --headless --no-sandbox --disable-gpu --hide-scrollbars --default-background-color=00000000 \
    --window-size="$2,$3" --screenshot="$4" "file://$(realpath "$1")" >/dev/null 2>&1
}

# Isotipo con esquinas redondeadas (íconos "any")
render public/img/logo-mark.svg 512 512 public/icons/icon-512.png
render public/img/logo-mark.svg 192 192 public/icons/icon-192.png

# Maskable: fondo a sangre y el símbolo dentro de la zona segura (80 %)
cat > "$TMP/maskable.svg" <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14264A"/><stop offset="1" stop-color="#050B17"/></linearGradient></defs>
  <rect width="100" height="100" fill="url(#bg)"/>
  <image href="logo-symbol.svg" x="14" y="14" width="72" height="72"/>
</svg>
SVG
cp public/img/logo-symbol.svg "$TMP/"
render "$TMP/maskable.svg" 512 512 public/icons/icon-maskable-512.png

# Apple touch icon: iOS aplica su propia máscara, así que va a sangre
sed 's/rx="22"/rx="0"/' public/img/logo-mark.svg > "$TMP/apple.svg"
render "$TMP/apple.svg" 180 180 public/icons/apple-touch-icon.png

# Favicon .ico (16/32/48) y favicon.svg
render public/img/logo-mark.svg 64 64 "$TMP/fav64.png"
python3 - "$TMP/fav64.png" public/favicon.ico <<'PY'
import sys
from PIL import Image
img = Image.open(sys.argv[1]).convert('RGBA')
img.save(sys.argv[2], format='ICO', sizes=[(16, 16), (32, 32), (48, 48)])
PY
cp public/img/logo-mark.svg public/favicon.svg

# Logotipo horizontal para README y documentos (2x)
cp public/img/logo-horizontal-dark.svg "$TMP/"
cat > "$TMP/horizontal-dark-fondo.svg" <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 140">
  <rect width="460" height="140" rx="20" fill="#0B1528"/>
  <image href="logo-horizontal-dark.svg" x="20" y="20" width="420" height="100"/>
</svg>
SVG
render "$TMP/horizontal-dark-fondo.svg" 920 280 public/img/logo-horizontal-dark.png
render public/img/logo-horizontal-light.svg 840 200 public/img/logo-horizontal-light.png

echo "Íconos regenerados:"; ls -la public/icons public/favicon.ico public/favicon.svg public/img/*.png
