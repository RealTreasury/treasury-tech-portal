#!/usr/bin/env bash
set -e

# PHP syntax validation
find . -type f -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l

# Basic plugin activation checks and shortcode tests
if command -v wp >/dev/null 2>&1; then
    wp plugin activate treasury-tech-portal
    wp eval 'do_shortcode("[treasury_portal]");'
    wp plugin deactivate treasury-tech-portal
else
    echo "wp-cli not found; skipping plugin activation and shortcode tests."
fi

echo "Tests completed."
