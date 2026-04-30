#!/usr/bin/env bash
# Install CannyForge Hreflang plugin into the system WordPress at /var/www/html
# Run from a terminal: bash install-wp-cannyforge-hreflang.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST="/var/www/html/wp-content/plugins/cannyforge-hreflang"
ZIP_OUT="${SCRIPT_DIR}/cannyforge-hreflang.zip"

if [[ -f "${SCRIPT_DIR}/cannyforge-hreflang.php" ]]; then
  SRC="${SCRIPT_DIR}"
elif [[ -f "${SCRIPT_DIR}/cannyforge-hreflang/cannyforge-hreflang.php" ]]; then
  SRC="${SCRIPT_DIR}/cannyforge-hreflang"
else
  echo "Expected plugin entrypoint at ${SCRIPT_DIR}/cannyforge-hreflang.php or ${SCRIPT_DIR}/cannyforge-hreflang/cannyforge-hreflang.php." >&2
  exit 1
fi

if [[ ! -f /var/www/html/wp-config.php ]]; then
  echo "No WordPress found at /var/www/html (missing wp-config.php)." >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "The zip command is required to build ${ZIP_OUT}. Install zip (e.g. sudo apt install zip)." >&2
  exit 1
fi

echo "Creating archive ${ZIP_OUT} ..."
rm -f "${ZIP_OUT}"
(
  cd "${SRC}"
  zip -r "${ZIP_OUT}" . -x "*.git*" -x "*node_modules*" -x "*.DS_Store" -x "install-wp-cannyforge-hreflang.sh"
)
echo "Archive ready."

echo "Installing plugin to ${DEST} ..."
sudo rsync -a --delete "${SRC}/" "${DEST}/"
sudo chown -R www-data:www-data "${DEST}"

PHP_ACTIVATE="$(mktemp)"
trap 'rm -f "${PHP_ACTIVATE}"' EXIT
cat > "${PHP_ACTIVATE}" <<'PHP'
<?php
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$slug = 'cannyforge-hreflang/cannyforge-hreflang.php';
if (is_plugin_active($slug)) {
    echo "Plugin already active.\n";
    exit(0);
}
$result = activate_plugin($slug);
if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message() . "\n");
    exit(1);
}
echo "Plugin activated.\n";
PHP

chmod a+r "${PHP_ACTIVATE}"

echo "Activating plugin (runs as www-data) ..."
sudo -u www-data php "${PHP_ACTIVATE}"

echo "Done. Open your site, then manage hreflang groups in the admin or visit the generated hreflang sitemap."