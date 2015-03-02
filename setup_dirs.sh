#!/bin/bash
export white='printf \033[01;37m'
export lyellow='printf \033[01;33m'
export lred='printf \033[01;31m'
export normal='tput sgr0'

function errperms(){
	$lred; echo "Permissions denied! try running this script as root"; $normal
	exit 1
}
touch .test
if [ ! $? -eq 0 ]; then
	errperms
fi
rm .test
$lyellow; echo "Setting up dirs and permissions..."; $normal
if [ ! -d epks ]; then
	mkdir epks
fi
if [ ! -d models ]; then
	mkdir models
fi
if [ ! -d requests ]; then
	mkdir requests
fi
if [ ! -d logs ]; then
	mkdir logs
fi
chmod -R 777 epks logs models requests
if [ ! $? -eq 0 ]; then
	errperms
fi
if [ ! -L "CheckSWManualUpdate.laf" ]; then
	ln -s CheckSWManualUpdate.php CheckSWManualUpdate.laf
fi
$lred; echo "!!IMPORTANT!!"; $normal
$white
echo "Don't forget to register .laf files as php in your apache config"
echo "Add the following line:"
echo "--------------------------"
echo "AddType application/x-httpd-php .laf"
echo
echo "--------------------------"
fmt="%-20s %s\n"
printf "$fmt" "epks" "Holds epk files for TVs"
printf "$fmt" "models" "Holds optional, custom replies"
printf "$fmt" "requests" "Holds copies of update requests made by TVs"
printf "$fmt" "logs" "Holds logs, generated during an update request"
$normal
