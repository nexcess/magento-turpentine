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


show_help()
{	
	echo "Warm the Varnish page cache by using siege to hit a list of URLs."
	echo "Options:"
	echo "-u	URL source - either the URL of a magento sitemap xml file, or the path to a file containing URLS - one per line"
	echo "-a	User-Agent to use"
	echo "-c	Number of concurrent \"users\" - siege will hit this many urls at once. Higher numbers = more server load."

}

# set defaults
CONC=3 # default concurrency level
TMP_URL_FILE="./urls_$RANDOM.txt" # location to store temporarily URL file
REMOVE_TMP_FILE=1

if [ $# -lt 1 ]; then
	show_help
	exit 1
fi

while getopts :u:a:c: opt
do 
	case "$opt" in 
	u) URLS="$OPTARG";;
	a) AGENT="$OPTARG";;
	c) CONC="$OPTARG";;
	\?) show_help;;
	esac
done


# process URLS as needed
if [[ $URLS =~ ^http ]]; then #fetch URLs from sitemap URL
	
	echo '<root/>' | xpath -e '*' &>/dev/null

	if [ $? -eq 2 ]; then
	    XPATH_BIN='xpath'
	else
	    XPATH_BIN='xpath -e'
	fi

	echo "Getting URLs from sitemap..."

	curl -ks "$URLS" | \
		$XPATH_BIN '/urlset/url/loc/text()' 2>/dev/null | \
	sed -r 's~http(s)?:~\nhttp\1:~g' | \
	grep -vE '^\s*$' > "$TMP_URL_FILE"
else
	TMP_URL_FILE=$URLS
	REMOVE_TMP_FILE=0
	if [ -f $TMP_URL_FILE ]; then
		echo "Getting URLs from $TMP_URL_FILE..."
	else 
		echo "No URL file found at $TMP_URL_FILE"
		exit 1
	fi
fi

UA=""
if [[ $AGENT =~ .. ]]; then 
	UA="-A '$AGENT'"
	echo "Warning with User-Agent '$AGENT'"
fi

echo "Warming $(cat $TMP_URL_FILE | wc -l) URLs using $CONC concurrent users..."
siege -b -v -c $CONC -f $TMP_URL_FILE -r once $UA -H 'Accept-Encoding: gzip' 2>/dev/null 

if [ $REMOVE_TMP_FILE == 1 ]; then 
	rm -f "$TMP_URL_FILE"
fi

exit 0
