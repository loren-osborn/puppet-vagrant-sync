SOURCE_FILES = $(shell find source -name '*.php' -type f -not -path '*/.git/*' -not -path '*/vendor/*')
COMPOSER_EXECUTABLE = $(shell bash -c 'which composer || echo php ./composer.phar')
COMPOSER_DEPENDANCY = $(shell bash -c 'which composer || echo composer.phar')
COMPOSER_LOCK_FILE_IF_PRESENT = $(shell bash -c 'ls -1 composer.lock || echo ')
COMPOSER_SOURCE_LOCK_FILE_IF_PRESENT = $(shell bash -c 'ls -1 source/composer.lock || echo ')
PHPUNIT = php vendor/phpunit/phpunit/phpunit.php

run_test: build/vagrant_sync.phar vendor
	$(PHPUNIT)

build/vagrant_sync.phar: $(SOURCE_FILES) source/vendor
	php -d phar.readonly=0 source/createPhar.php

vendor: $(COMPOSER_DEPENDANCY) composer.json $(COMPOSER_LOCK_FILE_IF_PRESENT)
	$(COMPOSER_EXECUTABLE) install

source/vendor: $(COMPOSER_DEPENDANCY) source/composer.json $(COMPOSER_SOURCE_LOCK_FILE_IF_PRESENT)
	cd source ; $(COMPOSER_EXECUTABLE) install ; cd ..

$(COMPOSER_DEPENDANCY):
	curl -sS https://getcomposer.org/installer | php

clean:
	rm -rf build vendor composer.phar
	mkdir build
