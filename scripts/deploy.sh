#!/bin/bash

# Treasury Tech Portal Deployment Script

set -e

# Configuration
PLUGIN_SLUG="treasury-tech-portal"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION=$(grep "Version:" treasury-tech-portal.php | sed 's/.*Version: *//' | sed 's/ .*//')

echo "Deploying Treasury Tech Portal v${VERSION}..."

# Validate plugin
echo "Validating plugin files..."
php -l treasury-tech-portal.php

# Check required files
required_files=("readme.txt" "treasury-tech-portal.php" "includes/class-treasury-portal.php")
for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        echo "Error: Required file $file is missing"
        exit 1
    fi
done

# Create deployment package
echo "Creating deployment package..."
mkdir -p dist
zip -r "dist/${PLUGIN_SLUG}-${VERSION}.zip" . \
    -x "*.git*" "*.github*" "dist/*" "*.md" "node_modules/*" "scripts/*" "*.DS_Store"

echo "Deployment package created: dist/${PLUGIN_SLUG}-${VERSION}.zip"

# Optional: Upload to release
if [ "$1" = "release" ]; then
    echo "Creating GitHub release..."
    gh release create "v${VERSION}" "dist/${PLUGIN_SLUG}-${VERSION}.zip" \
        --title "Release v${VERSION}" \
        --notes "Automated release of Treasury Tech Portal v${VERSION}"
fi

echo "Deployment complete!"
