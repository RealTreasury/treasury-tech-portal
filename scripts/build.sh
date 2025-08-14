#!/usr/bin/env bash
set -e

# PHP syntax check for all PHP files
find . -type f -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l

# Required file checks
required_files=("treasury-tech-portal.php" "readme.txt" "WORDPRESS-COM-DEPLOYMENT.md")
for file in "${required_files[@]}"; do
    if [[ ! -f "$file" ]]; then
        echo "Required file $file is missing!"
        exit 1
    fi
done

# Optional asset minification
if command -v npm >/dev/null 2>&1 && [[ -f package.json ]]; then
    npm run build
else
    echo "Skipping asset minification; npm or package.json not found."
fi

echo "Build completed successfully."
