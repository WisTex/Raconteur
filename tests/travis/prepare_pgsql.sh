#!/usr/bin/env bash

#
# Copyright (c) 2016 Hubzilla
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#

# Exit if anything fails
set -e

echo "Preparing for PostgreSQL ..."

if [[ "$POSTGRESQL_VERSION" == "10" ]]; then
	echo "Using PostgreSQL in Docker container, need to use TCP"
	export PROTO="-h localhost"
fi

# Print out some PostgreSQL information
psql --version
# Why does this hang further execution of the job?
psql $PROTO -U postgres -c "SELECT VERSION();"

# Create Hubzilla database
psql $PROTO -U postgres -c "DROP DATABASE IF EXISTS travis_hubzilla;"
psql $PROTO -U postgres -v ON_ERROR_STOP=1 <<-EOSQL
    CREATE USER travis_hz WITH PASSWORD 'hubzilla';
    CREATE DATABASE travis_hubzilla;
    ALTER DATABASE travis_hubzilla OWNER TO travis_hz;
    GRANT ALL PRIVILEGES ON DATABASE travis_hubzilla TO travis_hz;
EOSQL

# Import table structure
psql $PROTO -U travis_hz -v ON_ERROR_STOP=1 travis_hubzilla < ./install/schema_postgres.sql

# Show databases and tables
psql $PROTO -U postgres -l
psql $PROTO -U postgres -d travis_hubzilla -c "\dt;"
