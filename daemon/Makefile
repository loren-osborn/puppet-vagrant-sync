SOURCE_FILES = $(shell find source -name '*.php' -type f -not -path '*/.git/*')

test: build/vagrant_sync.phar
	phpunit

build/vagrant_sync.phar: $(SOURCE_FILES)

clean:
	rm -rf build
	mkdir build
