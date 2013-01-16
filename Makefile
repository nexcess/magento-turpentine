SHELL := /bin/bash
.PHONY: connect-desc connect-pkg all clean

connect-desc:
	grep -v 'Build Status' README.md | markdown_py -o html5 -f "build/magento-connect-desc-$(shell ./util/get-version.sh).html"

connect-changelog:
	markdown_py -o html5 -f "build/magento-connect-changelog-$(shell ./util/get-version.sh).html" CHANGELOG.md

connect-pkg:
	./build/build_package.py -d build/mage-package.xml

all: connect-desc connect-changelog connect-pkg

clean:
	rm -f ./build/*.tgz ./build/*.html ./package.xml
