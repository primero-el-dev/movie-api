#!/usr/bin/env bash

COMMAND="COMPOSER_MEMORY_LIMIT=-1 composer $@"

su -s /bin/bash www-data -p -c "$COMMAND"
