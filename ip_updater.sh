#!/bin/sh
### BEGIN INIT INFO
# Provides:  UNATTENDED DYNAMIC IP AUTO UPDATER FOR ISPConfig
# Required-Start:  $local_fs $network
# Required-Stop:  $local_fs
# Default-Start:  2 3 4 5
# Default-Stop:  0 1 6
# Short-Description:  UNATTENDED DYNAMIC IP AUTO UPDATER FOR ISPConfig
# Description:  Unattended automatic update of dynamic ip for ISPConfig server.
### END INIT INFO
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
