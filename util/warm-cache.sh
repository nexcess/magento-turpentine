#!/bin/bash

SITEMAP_URL="$1"
TMP_URL_FILE="/tmp/urls_$(cat /proc/sys/kernel/random/uuid).txt"

echo '<root/>' | xpath -e '*' &>/dev/null

if [ $? -eq 2 ]; then
    XPATH_BIN='xpath'
else
    XPATH_BIN='xpath -e'
fi

if [ -z "$SITEMAP_URL" ]; then
    cat <<EOF
Usage: $0 <sitemap URL>

    Warm Magento's cache by visiting the URLs in Magento's sitemap

    Example:
        $0 http://example.com/magento/sitemap.xml
EOF

    exit 1
fi

PROCS=$(grep processor /proc/cpuinfo | wc -l)

echo "Getting URLs from sitemap..."

curl -ks "$SITEMAP_URL" | \
	$XPATH_BIN '/urlset/url/loc/text()' 2>/dev/null | \
	sed -r 's~http(s)?:~\nhttp\1:~g' | \
    grep -vE '^\s*$' > "$TMP_URL_FILE"

echo "Warming $(cat $TMP_URL_FILE | wc -l) URLs using $PROCS processes..."

cat "$TMP_URL_FILE" | \
    xargs -P "$PROCS" -r -n 1 -- \
        siege -b -v -c 1 -r once 2>/dev/null | \
    sed -r 's/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]//g' | \
    grep -E '^HTTP'

rm -f "$TMP_URL_FILE"
