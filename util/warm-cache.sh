#!/bin/bash

# Nexcess.net Turpentine Extension for Magento
# Copyright (C) 2012  Nexcess.net L.L.C.
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

SITEMAP_URL="$1"
TMP_URL_FILE="/tmp/urls_$(cat /proc/sys/kernel/random/uuid).txt"
PROCS="${PROCS-$(grep processor /proc/cpuinfo | wc -l)}"

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
cat "$TMP_URL_FILE" | \
    xargs -P "$PROCS" -r -n 1 -- \
        siege -H 'Accept-Encoding: gzip' -b -v -c 1 -r once 2>/dev/null | \
    sed -r 's/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]//g' | \
    grep -E '^HTTP'

rm -f "$TMP_URL_FILE"
