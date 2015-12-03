# IP_Updater

ISPConfig Server IP Updater especially for those who is using dynamic IP instead of static IP.

# How To
1. You will need to copy resync.php to ipu_resync.php and app.inc.php to ipu_app.inc.php. In ubuntu, to copy the files to new names, basically just type:
sudo cp /usr/local/ispconfig/interface/web/tools/resync.php /usr/local/ispconfig/interface/web/tools/ipu_resync.php
sudo cp /usr/local/ispconfig/interface/lib/app.inc.php /usr/local/ispconfig/interface/lib/ipu_app.inc.php 


2. In the new file ipu_resync.php, change require_once from app.inc.php to ipu_app.inc.php to look as follows:
require_once '../../lib/ipu_app.inc.php';


3.  In the same file, disable admin check and tpl by commenting out the lines as follows:
//* Check permissions for module
// $app->auth->check_module_permissions('admin');

//* This is only allowed for administrators
// if(!$app->auth->is_admin()) die('only allowed for administrators.');
...
// $app->tpl->setVar('msg', $msg);
// $app->tpl->setVar('error', $error);

// $app->tpl_defaults();
// $app->tpl->pparse();

4.  Then in the other new file ipu_app.inc.php, disable start_session() by commenting out the line as follows:
// session_start();

5. Create ip_updater.php file in /usr/local/ispconfig/interface/web/tools/ and paste ip_updater.php code to it or use wget as follows:
sudo cd /usr/local/ispconfig/interface/web/tools
sudo wget https://raw.githubusercontent.com/ahrasis/IP_Updater/master/ip_updater.php

6. Create cron job using sudo crontab -e or ISPConfig control panel. The timing is up to you but I would suggest to add one at every reboot and the other, every hour on selected minute e.g.:
@reboot php -q /usr/local/ispconfig/interface/web/tools/ip_updater.php
18 * * * * php -q /usr/local/ispconfig/interface/web/tools/ip_updater.php

# License
As stated in the ip_updater.php file.
