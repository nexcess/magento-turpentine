#!/bin/bash

SITEMAP_URL="$1"
TMP_URL_FILE="/tmp/$(cat /proc/sys/kernel/random/uuid).txt"

if [ -z "$1" ]; then
    echo "Need to give me the site's sitemap URL!"
    exit 1
fi

curl -ks "$SITEMAP_URL" | \
	xpath /dev/stdin '/urlset/url/loc/text()' 2>/dev/null | \
	sed -r 's~http(s)?:~\nhttp\1:~g' > "$TMP_URL_FILE"

siege -b -v -c 1 -r once -f "$TMP_URL_FILE"

rm -f "$TMP_URL_FILE"
