#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

echo "Running PHP syntax validation..."
find "$ROOT_DIR" -name "*.php" -not -path "$ROOT_DIR/vendor/*" -print0 | while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null || { echo "PHP syntax error in $file"; exit 1; }
done
echo "PHP syntax validation passed."

if command -v wp >/dev/null; then
    echo "Running plugin activation check..."
    wp plugin deactivate treasury-tech-portal >/dev/null 2>&1 || true
    if wp plugin activate treasury-tech-portal >/dev/null 2>&1; then
        echo "Plugin activated successfully."
    else
        echo "Plugin activation failed."
        exit 1
    fi

    echo "Running shortcode test..."
    if wp eval 'echo do_shortcode("[treasury_portal]");' >/dev/null 2>&1; then
        echo "Shortcode executed without fatal errors."
    else
        echo "Shortcode execution failed."
        exit 1
    fi
else
    echo "wp-cli not found; skipping plugin activation and shortcode tests."
fi

echo "All tests completed."
