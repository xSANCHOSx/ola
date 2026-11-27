MySQL integration tests
========================
The tests require a dedicated test database. They do not create or drop a database
and roll back their data changes in one transaction.

Prepare a disposable database from the complete clean-install schema:
  mysql -u <user> -p <database> < database/schema.sql

Run:
  TEST_DB_HOST=127.0.0.1 \
  TEST_DB_PORT=3306 \
  TEST_DB_NAME=ola_test \
  TEST_DB_USER=test_user \
  TEST_DB_PASS='test_password' \
  tests/integration/run.sh

If TEST_DB_HOST, TEST_DB_NAME or TEST_DB_USER is not set, the test exits with
code 77 and reports SKIP. It never uses the application's DB_* variables,
which helps prevent accidental execution against a production database.
