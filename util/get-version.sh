#!/bin/bash

BASE_DIR="$(dirname "$(dirname "$(readlink -f "$0")")")"

XML_PATH_VERSION='/config/modules/Nexcessnet_Turpentine/version/text()'
CONFIG_XML_PATH='app/code/community/Nexcessnet/Turpentine/etc/config.xml'

echo "$(xpath "$XML_PATH_VERSION" \
    < "${BASE_DIR}/${CONFIG_XML_PATH}" \
    2> /dev/null)"
