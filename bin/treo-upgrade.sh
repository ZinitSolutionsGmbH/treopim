#!/bin/bash

# prepare PHP
php=$2

# prepare log file
log="data/treo-upgrade.log"

currentVersion="3.3.8"
version="3.3.10"

# download package
echo "1. Downloading upgrade package" > $log 2>&1
if ! $php console.php upgrade $version --download > /dev/null 2>&1; then
    echo "ERROR" >> $log 2>&1
    echo "{{failed}}" >> $log 2>&1
    exit 1
fi
echo -e "OK\n" >> $log 2>&1

# composer update
echo "2. Updating dependencies" >> $log 2>&1
$php console.php composer-version $version --set > /dev/null 2>&1
$php composer.phar run-script pre-update-cmd > /dev/null 2>&1
if ! $php composer.phar update --no-dev --no-scripts >> $log 2>&1; then
    $php console.php composer-version $currentVersion --set > /dev/null 2>&1
    echo "{{failed}}" >> $log 2>&1
    exit 1
fi
echo -e "OK\n" >> $log 2>&1

# upgrade
echo "3. Upgrading core" >> $log 2>&1
$php console.php upgrade $version --force > /dev/null 2>&1
$php console.php migrate TreoCore $currentVersion $version > /dev/null 2>&1
$php composer.phar run-script post-update-cmd > /dev/null 2>&1
echo -e "OK\n" >> $log 2>&1
echo "{{finished}}" >> $log 2>&1