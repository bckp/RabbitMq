.PHONY: help

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

install:
	composer update

qa: phpstan cs

cs:
	composer phpcs

csf:
	composer fix-style

phpstan:
	vendor/bin/phpstan analyse src

tests:
	vendor/bin/tester -s -p php --colors 1 -C tests/Cases

coverage-clover:
	vendor/bin/tester -s -p phpdbg --colors 1 -C --coverage ./coverage.xml --coverage-src ./src ./tests/Cases

coverage-html:
	vendor/bin/tester -s -p phpdbg --colors 1 -C --coverage ./coverage.html --coverage-src ./src ./tests/Cases
