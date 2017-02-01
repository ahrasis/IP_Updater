#!/bin/sh
if [ ! -f "/usr/local/ispconfig/interface/lib/ip_updater.php" ]
then
    cd /usr/local/ispconfig/interface/lib
    sudo wget https://raw.githubusercontent.com/ahrasis/IP_Updater/master/ip_updater.php
    sudo wget https://raw.githubusercontent.com/ahrasis/IP_Updater/master/ipu_resync.php
    sudo wget https://raw.githubusercontent.com/ahrasis/IP_Updater/master/ipu_app.inc.php
    php -q /usr/local/ispconfig/interface/lib/ip_updater.php 2>&1 | while read line; do echo `/bin/date` "$line" >> /var/log/ip_updater.log; done
else
    php -q /usr/local/ispconfig/interface/lib/ip_updater.php 2>&1 | while read line; do echo `/bin/date` "$line" >> /var/log/ip_updater.log; done
fi
root@tetuan:/home/abufahimatturobi# 
