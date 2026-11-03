.DEFAULT_GOAL := help

.PHONY: help install test check archive runtime installer

help:
	@printf '%s\n' 'install  Install Composer dependencies' 'test     Run unit tests' 'check    Run all local checks' 'archive  Build the Composer release archive' 'runtime  Build the locked standalone runtime' 'installer Build archives and compiled installer'

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

runtime:
	composer install --working-dir=beacon/build-tools --no-interaction
	composer install --working-dir=beacon/runtime --no-interaction
	php beacon/scripts/build-runtime.php

installer: runtime archive
	php beacon/scripts/build-installer.php
