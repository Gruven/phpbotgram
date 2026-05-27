.PHONY: test stan lint fix regenerate coverage coverage-gate docs-api

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyze

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	vendor/bin/php-cs-fixer fix

regenerate:
	php tools/generator/bin/generate.php

coverage:
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover=build/coverage/clover.xml

coverage-gate: coverage
	php scripts/coverage-gate.php build/coverage/clover.xml

# Build the API documentation site under build/docs/api/. The
# `phpdocumentor/shim` composer dev-dep installs the official phpDocumentor
# phar into `vendor/bin/phpdoc` on `composer install` — no extra fetch step.
# Output is gitignored; CI publishes it to gh-pages via .github/workflows.
docs-api:
	VERSION=0.1.0-dev bash scripts/build-docs.sh
