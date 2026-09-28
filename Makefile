.DEFAULT_GOAL := help

.PHONY: help install test check archive

help:
	@printf '%s\n' 'install  Install Composer dependencies' 'test     Run unit tests' 'check    Run all local checks' 'archive  Build the Composer release archive'

install:
	composer install --no-interaction

test:
	composer test

check:
	composer validate --strict --no-check-version
	composer test
	php beacon/scripts/check-project.php

archive:
	composer archive --format=zip --dir=dist
