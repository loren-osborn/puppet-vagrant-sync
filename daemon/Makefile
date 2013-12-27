SOURCE_FILES = $(shell find source -name '*.php' -type f -not -path '*/.git/*')

run_test: build/vagrant_sync.phar
	phpunit

build/vagrant_sync.phar: $(SOURCE_FILES)
	touch build/vagrant_sync.phar

clean:
	rm -rf build
	mkdir build
