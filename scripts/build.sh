#!/usr/bin/env bash
set -e

echo "=== Treasury Tech Portal Build Process ==="

# Enhanced PHP syntax checking with better error reporting
echo "1. Running comprehensive PHP syntax checks..."
find . -type f -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" | while read -r file; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "❌ PHP syntax error in: $file"
        php -l "$file"
        exit 1
    fi
done
echo "✓ PHP syntax checks passed"

# Validate JSON files
echo "2. Validating JSON files..."
for json_file in data/*.json .wordpress-com/*.json; do
    if [[ -f "$json_file" ]]; then
        if ! python3 -m json.tool "$json_file" > /dev/null 2>&1; then
            echo "❌ Invalid JSON in: $json_file"
            exit 1
        fi
    fi
done
echo "✓ JSON validation passed"

# Verify required files with detailed checking
echo "3. Verifying required files..."
required_files=(
    "treasury-tech-portal.php"
    "readme.txt" 
    "WORDPRESS-COM-DEPLOYMENT.md"
    ".wordpress-com/config.json"
    ".wordpress-com/deployment.yml"
)

for file in "${required_files[@]}"; do
    if [[ ! -f "$file" ]]; then
        echo "❌ Required file missing: $file"
        exit 1
    fi
done
echo "✓ All required files present"

# Validate code structure
echo "4. Running structure validation..."
if [[ -f "scripts/validate-structure.py" ]]; then
    python3 scripts/validate-structure.py
fi

# Asset optimization (if tools available)
if command -v npm >/dev/null 2>&1 && [[ -f package.json ]]; then
    echo "5. Running npm build..."
    npm run build
elif command -v python3 >/dev/null 2>&1; then
    echo "5. Optimizing assets with Python..."
    # Simple CSS minification using Python
    python3 -c "
import re
with open('assets/css/treasury-portal.css', 'r') as f:
    css = f.read()
# Remove comments and extra whitespace
css = re.sub(r'/\*.*?\*/', '', css, flags=re.DOTALL)
css = re.sub(r'\s+', ' ', css)
css = re.sub(r';\s*}', '}', css)
css = re.sub(r'{\s*', '{', css)
with open('assets/css/treasury-portal.min.css', 'w') as f:
    f.write(css.strip())
print('✓ CSS minified')
"
else
    echo "5. Skipping asset optimization (no tools available)"
fi

echo ""
echo "🎉 Build completed successfully!"
echo "   Repository is ready for deployment and automated modifications."
