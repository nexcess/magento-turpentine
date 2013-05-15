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

# Script to dump active Varnish config, use VARNISHADM env var to use an
# alternate path to the varnishadm executable

VARNISHADM="${VARNISHADM:-varnishadm}"

# varnishadm vcl.show "$(varnishadm vcl.list | grep '^active' | awk '{print $3}')"
${VARNISHADM} vcl.show "$(${VARNISHADM} vcl.list | grep '^active' | awk '{print $3}')"
