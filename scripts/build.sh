#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

echo "Running PHP syntax checks..."
find "$ROOT_DIR" -name "*.php" -not -path "$ROOT_DIR/vendor/*" -print0 | while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null || { echo "PHP syntax error in $file"; exit 1; }
done
echo "PHP syntax checks passed."

echo "Checking required files..."
REQUIRED_FILES=( "$ROOT_DIR/treasury-tech-portal.php" "$ROOT_DIR/readme.txt" )
for f in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$f" ]; then
        echo "Missing required file: $f"
        exit 1
    fi
done
echo "All required files present."

if command -v terser >/dev/null && [ -f "$ROOT_DIR/assets/js/treasury-portal.js" ]; then
    echo "Minifying assets..."
    terser "$ROOT_DIR/assets/js/treasury-portal.js" -c -m -o "$ROOT_DIR/assets/js/treasury-portal.min.js"
else
    echo "Skipping asset minification (tooling missing)."
fi

echo "Build completed."
