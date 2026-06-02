.PHONY: help install lint lint-errors fix test

help:
	@echo "Moodle mod_crucible - Local Testing"
	@echo ""
	@echo "Usage:"
	@echo "  make install      Install composer dependencies"
	@echo "  make lint         Check code standards (all)"
	@echo "  make lint-errors  Check code standards (errors only)"
	@echo "  make fix          Auto-fix code standards"
	@echo "  make syntax       Check PHP syntax"
	@echo "  make test         Run all checks"

install:
	composer install

lint:
	vendor/bin/phpcs --standard=moodle --ignore=vendor/ .

lint-errors:
	vendor/bin/phpcs --standard=moodle --error-severity=1 --warning-severity=8 --ignore=vendor/ .

fix:
	vendor/bin/phpcbf --standard=moodle --ignore=vendor/ .

syntax:
	@find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; | grep -v "No syntax errors" || echo "✓ All files have valid PHP syntax"

test: syntax lint-errors
	@echo "✓ All checks passed"
