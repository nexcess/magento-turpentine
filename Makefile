SHELL := /bin/bash
.PHONY: connect-desc connect-pkg all clean

connect-desc:
	markdown2 README.md > build/magento-connect-desc-$(shell ./util/get-version.sh).html

connect-pkg:
	./build/build_package.py -d build/mage-package.xml

all: connect-desc connect-pkg

clean:
	rm -f ./build/*.tgz ./build/*.html ./package.xml
