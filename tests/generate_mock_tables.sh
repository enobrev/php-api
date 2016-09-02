#!/usr/bin/env bash

../vendor/bin/generate_tables.php -j ./Mock/sql.json -o ./Mock/Table -n Enobrev\\API\\Mock\\Table
../bin/generate_data_map.php -j ./Mock/sql.json -o ./Mock/
