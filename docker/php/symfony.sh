#!/usr/bin/env bash

COMMAND="php bin/console $@"

su -s /bin/bash www-data -p -c "$COMMAND"
