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

TEST_URL="$1"
TMP_FILE="/tmp/load_test_$(cat /proc/sys/kernel/random/uuid).txt"

REPETITIONS=64
CONCURRENCY=128

if [ -z "$TEST_URL" ]; then
    cat <<EOF
Usage: $0 <test URL>

    Run a simple load test on a URL to look for non-unique SIDs

    Example:
        $0 http://example.com/magento/sitemap.xml
EOF

    exit 1
fi

echo "Starting load test..."
echo "Using $CONCURRENCY clients doing $REPETITIONS requests each for $(($CONCURRENCY * $REPETITIONS)) requests total"

for i in $(seq 1 $CONCURRENCY); do
    { for j in $(seq 1 $REPETITIONS); do curl -sIH 'User-Agent: TestAgent' -H 'Accept-Encoding: gzip' "$TEST_URL" | grep ^Set-Cookie >> "$TMP_FILE"; done; } &>/dev/null &
done

echo -n "Waiting test to finish..."

while [ $(jobs -l | wc -l) -gt 4 ]; do
    sleep 1 && echo -n '.'
done
echo

sleep 1 && echo "Test finished, checking for duplicate SIDs..."

# find non-unique SIDs
non_unique_sids="$(sed -r 's/.*frontend=([^;]+).*/\1/' "$TMP_FILE" | \
    sort | uniq -c | grep -vE '^\s*1\s+' | sort -rn)"
non_unique_sids_count="$(echo -n "$non_unique_sids" | wc -l)"
if [ $non_unique_sids_count -gt 0 ]; then
    echo "Found $non_unique_sids_count duplicated SIDs"
    echo "$non_unique_sids"
else
    echo "No duplicate SIDs found"
fi
echo "Full log at: $TMP_FILE"

if [ $non_unique_sids_count -gt 0 ]; then
    exit 2
else
    exit 0
fi
