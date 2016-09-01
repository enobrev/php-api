#!/usr/bin/env bash

../vendor/bin/generate_tables.php -j ./Mock/sql.json -o ./Mock -n Enobrev\\API\\Mock
../bin/generate_data_map.php -j ./Mock/sql.json -o ./Mock/
