SOURCE_FILES = $(shell find source -name '*.php' -type f -not -path '*/.git/*')

run_test: build/vagrant_sync.phar
	phpunit

build/vagrant_sync.phar: $(SOURCE_FILES)
	php -d phar.readonly=0 source/createPhar.php

clean:
	rm -rf build
	mkdir build
