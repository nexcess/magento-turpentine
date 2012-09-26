SHELL := /bin/bash
.PHONY: connect-pkg all clean

connect-pkg:
	./build/build_package.py build/mage-package.xml

all: connect-pkg

clean:
	rm -f ./build/*.tgz ./package.xml
