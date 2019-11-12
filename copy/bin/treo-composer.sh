#!/bin/bash

# prepare PHP
php=$2

# prepare file(s) path
path="data/treo-composer-run.txt"
log="data/treo-composer.log"

while true
do
   # delete check-up file
   rm "data/composer-check-up.log" > /dev/null 2>&1

   # exit
   if [ -f "data/process-kill.txt" ]; then
     exit 1;
   fi

   if [ -f $path ]; then
     # delete file
     rm $path;

     # start
     echo -e "" > $log 2>&1

     # composer update
     if ! $php composer.phar update --no-dev --no-scripts >> $log 2>&1; then
       echo "{{error}}" >> $log 2>&1
     else
       $php composer.phar run-script post-update-cmd >> $log 2>&1
       echo "{{success}}" >> $log 2>&1
     fi
     $php index.php composer log > /dev/null 2>&1
   fi

   sleep 1;
done