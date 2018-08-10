#!/usr/bin/env bash

# generate sql.json
../../vendor/bin/sql_to_json.php -h 192.168.1.2 -u dev -p -n dev_api_mock

# generate mysql.sql
mysqldump -h 192.168.1.2 -u dev -p --no-data --databases dev_api_mock > mysql.sql

# download script for converting mysql database to sqlite
if [ ! -f ./mysql2sqlite.sh ]; then
    wget https://gist.githubusercontent.com/esperlu/943776/raw/be469f0a0ab8962350f3c5ebe8459218b915f817/mysql2sqlite.sh
    chmod +x mysql2sqlite.sh
fi

# generate sqlite.sql
./mysql2sqlite.sh -h 192.168.1.2 -u dev -p --no-data --databases dev_api_mock > sqlite.sql
sed -i '/CREATE DATABASE/d' ./sqlite.sql
sed -i '/CONSTRAINT/d' ./sqlite.sql
sed -i '/PRAGMA/d' ./sqlite.sql
sed -i '/TRANSACTION/d' ./sqlite.sql
sed -i '/CREATE INDEX/d' ./sqlite.sql
sed -i 's/"address_id" int(10)  NOT NULL/"address_id" INTEGER/' ./sqlite.sql