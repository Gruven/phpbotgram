.PHONY: test stan lint fix regenerate

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyze

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	vendor/bin/php-cs-fixer fix

regenerate:
	php tools/generator/bin/generate.php --schema .butcher/schema/schema.json --out src/
