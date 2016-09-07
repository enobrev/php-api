
## generate_sql.sh
This script generates sql.json (needed for generate_mock_tables) and the sql files (mysql.sql and sqlite.sql).

You may need to adjust the host, user, pass, and database params to get it to work.

### sqlite.sql
sqlite.sql is generated from mysql.sql using the script at https://gist.github.com/esperlu/943776

## generate_mock_tables.sh
This script generates the Mock Objects needed for the tests.

## How to Prepare Tests
```
# create a mysql table in your local mysql server using mysql.sql
./generate_sql.sh
# it will request your local mysql password three times
./generate_mock_tables.sh
```