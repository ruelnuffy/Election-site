#!/usr/bin/env bash
# =========================================================================
# SIA 2027 Election Intelligence & Party Agent Command Portal
# Automated Shell Deployment & Setup Runner
# =========================================================================
set -e

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

echo "================================================================="
echo " SIA 2027 ELECTION INTELLIGENCE PORTAL - SETUP RUNNER"
echo "================================================================="

# 1. Ensure required directories exist with write permissions
echo "--> Configuring directory permissions..."
mkdir -p database uploads database/seed
chmod 777 database uploads 2>/dev/null || chmod 775 database uploads 2>/dev/null || true

# 2. Check for PHP CLI
PHP_BIN="php"
if ! command -v php &> /dev/null; then
    if [ -f "/Users/ruelnuffy/.config/herd-lite/bin/php" ]; then
        PHP_BIN="/Users/ruelnuffy/.config/herd-lite/bin/php"
    elif [ -f "/usr/local/bin/php" ]; then
        PHP_BIN="/usr/local/bin/php"
    elif [ -f "/usr/bin/php" ]; then
        PHP_BIN="/usr/bin/php"
    elif [ -f "/opt/cpanel/ea-php84/root/usr/bin/php" ]; then
        PHP_BIN="/opt/cpanel/ea-php84/root/usr/bin/php"
    elif [ -f "/opt/cpanel/ea-php83/root/usr/bin/php" ]; then
        PHP_BIN="/opt/cpanel/ea-php83/root/usr/bin/php"
    elif [ -f "/opt/cpanel/ea-php82/root/usr/bin/php" ]; then
        PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"
    else
        echo "[ERROR] PHP CLI binary could not be found."
        echo "Please install PHP 8.1+ or run this setup via web browser at http://your-domain/deploy.php"
        exit 1
    fi
fi

echo "--> Using PHP Binary: $($PHP_BIN -v | head -n 1)"

# 3. Execute deploy.php CLI
echo "--> Executing automated portal setup..."
"$PHP_BIN" deploy.php --cli "$@"

