# Testing mod_crucible Locally

## Prerequisites

```bash
cd /mnt/data/crucible/moodle/mod/crucible
composer install
```

## Quick Checks

### 1. PHP Syntax Check
```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; | grep -v "No syntax errors"
```

### 2. Moodle Code Standards (PHPCS)
```bash
# Check all files
vendor/bin/phpcs --standard=moodle --ignore=vendor/ .

# Check specific file
vendor/bin/phpcs --standard=moodle view.php

# Auto-fix what's possible
vendor/bin/phpcbf --standard=moodle --ignore=vendor/ .
```

## Common PHPCS Fixes

**Ignore warnings for now, focus on errors:**
```bash
vendor/bin/phpcs --standard=moodle --error-severity=1 --warning-severity=8 --ignore=vendor/ .
```

**See only fixable issues:**
```bash
vendor/bin/phpcbf --standard=moodle --ignore=vendor/ --dry-run .
```

## Using Makefile

```bash
# Install dependencies
make install

# Run syntax check + code standards (errors only)
make test

# Auto-fix code standards
make fix

# Full code standards check (including warnings)
make lint
```

## Integration with Aspire

The Moodle container at `http://localhost:8081` has this plugin mounted. After code changes:
1. Refresh Moodle page to see changes
2. Use Xdebug for debugging (port 9003)

## Before Committing

Run this checklist:
```bash
# 1. Check syntax
make syntax

# 2. Check standards (errors only)
make lint-errors

# 3. Auto-fix what you can
make fix

# 4. Commit
git add .
git commit -m "Your message"
```
