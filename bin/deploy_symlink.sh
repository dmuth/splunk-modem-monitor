#!/bin/bash
#
# "Deploy" this app by creating a symlink in $SPLUNK_HOME/etc/apps/
#

#set -x # Debugging

#
# Errors are fatal
#
set -e 


if test ! "$1"
then
	echo "Syntax: $0 \$SPLUNK_ROOT"
	exit 1
fi

SPLUNK_ROOT=$1
TARGET=${SPLUNK_ROOT}/etc/apps
FILENAME="Cable_Modem_Monitor"

#
# Change into the parent directory of this script
#
cd `dirname $0`
cd ..
DIR=`pwd`

#
# Now make a symlink under the apps directory to this directory
#
pushd $TARGET > /dev/null

if test -r $FILENAME
then
	echo "# " 
	echo "# File ${TARGET}/${FILENAME} already exists. Aborting." 
	echo "# " 
	exit
fi

ln -sf $DIR $FILENAME

echo "# "
echo "# Created a symlink to the application from ${TARGET}"
echo "# "

