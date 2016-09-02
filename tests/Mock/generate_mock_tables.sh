#!/usr/bin/env bash

../../vendor/bin/generate_tables.php -j ./sql.json -o ./Table -n Enobrev\\API\\Mock\\Table
../../bin/generate_data_map.php -j ./sql.json -o ./
