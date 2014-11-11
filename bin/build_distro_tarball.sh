#!/bin/bash
#
# Build a distro tarball so that I can upload this app to the Splunk Apps website. :-)
#

#set -x # Debugging

#
# Errors are fatal
#
set -e 


#
# First, get the name of the directory this app resides in.
#
cd `dirname $0`
cd ..

TARBALL=`pwd`
DIR_TO_TAR=`basename ${TARBALL}`
TARBALL=`basename ${TARBALL}`.tgz

#
# Head up to the parent directory and tar up the file now
#
cd ..

tar cfvz ${TARBALL} --exclude="splunk*.deb" --exclude=".vagrant" --exclude="*DS_Store" ${DIR_TO_TAR}

OUTPUT=`pwd`/${TARBALL}
echo "Tarball created as '${OUTPUT}'!"

