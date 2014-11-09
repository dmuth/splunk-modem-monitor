#!/bin/bash
#
# This wrapper script seems silly, but the reason why we have it is because 
# otherwise we'll get errors like this from PHP:
#
# /opt/splunk/lib/libssl.so.1.0.0: version `OPENSSL_1.0.0' not found
#


#
# Errors are fatal
#
set -e

unset LD_LIBRARY_PATH
pushd `dirname $0` > /dev/null

./arris.php
#./arris.php |logger -i -s 2>&1 # Debugging


