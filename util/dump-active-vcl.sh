#!/bin/bash

varnishadm vcl.show "$(varnishadm vcl.list | grep '^active' | awk '{print $3}')"
