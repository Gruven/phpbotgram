.PHONY: test stan lint fix regenerate coverage coverage-gate

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
