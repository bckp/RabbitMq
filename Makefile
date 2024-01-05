.PHONY: help install qa cs csf phpstan tests coverage-clover coverage-html
.DEFAULT_GOAL := help

help:  ## This help
	@printf "\033[33mUsage:\033[0m\n  make <command>\n\n\033[33mAvailable commands:\033[0m\n";
	@grep -F -h "##" $(MAKEFILE_LIST) | grep -F -v grep -F | sed -e 's/\\$$//' | awk 'BEGIN {FS = ":*[[:space:]]*##[[:space:]]*"}; \
	{ \
		if($$2 == "") \
			printf; \
		else if($$0 ~ /^#/) \
			printf " \033[36m%s\033[0m\n", $$2; \
		else if($$1 == "") \
			printf "    %-20s%s\n", "", $$2; \
		else \
			printf "   \033[32m%-20s\033[0;0m %s\n", $$1, $$2; \
	}'

install: ## Install composer dependencies
	composer update

qa: ## Quality assurance (code sniffer and phpstan)
qa: phpstan cs

cs: ## Run code sniffer
	composer phpcs

csf: ## Run code sniffer and fix errors
	composer fix-style

phpstan: ## Run phpstan
	vendor/bin/phpstan analyse src

tests:
	vendor/bin/tester -s -p php --colors 1 -C tests/Cases

coverage-clover:
	vendor/bin/tester -s -p phpdbg --colors 1 -C --coverage ./coverage.xml --coverage-src ./src ./tests/Cases

coverage-html:
	vendor/bin/tester -s -p phpdbg --colors 1 -C --coverage ./coverage.html --coverage-src ./src ./tests/Cases
