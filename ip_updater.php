<?php
/*
Copyright (c) 2015, Ahmad Rasyid Ismail, ahrasis@gmail.com
Project IP Updater for ubuntu, ispconfig 3 and dynamic ip users.
BSD3 License. All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

	* Redistributions of source code must retain the above copyright notice,
	  this list of conditions and the following disclaimer.
	* Redistributions in binary form must reproduce the above copyright notice,
	  this list of conditions and the following disclaimer in the documentation
	  and/or other materials provided with the distribution.
	* Neither the name of ISPConfig nor the names of its contributors
	  may be used to endorse or promote products derived from this software without
	  specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 'AS IS' AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/* Get database access by using ispconfig default configuration so no
   user and its password are disclosed. Exit if its connection failed */

require_once '/usr/local/ispconfig/interface/lib/config.inc.php';
$ip_updater = mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database']);
if (mysqli_connect_errno()) {
    printf("Connection failed! \r\n", mysqli_connect_error());
    exit();
}

/* Else, it works. Now get public ip from a reliable source.
   We are using this but you can define your own. But We just
   need ipv4. So we exit if its filetering failed */

$public_ip = file_get_contents('http://ip.sch.my/');
if(!filter_var($public_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === true) {
    printf("IPV4 Filtering failed! \r\n");
    exit();
}

/* Else, it's truly ipv4. Now we obtain the server ip address,
   based on server id. So change your server id accordingly.
   Exit if the server public ip address matches i.e. the same.  */

$query_ip = mysqli_query($ip_updater, 'SELECT ip_address FROM server_ip WHERE server_id =1');
list ($stored_ip) = mysqli_fetch_row($query_ip);
if($public_ip == $stored_ip) {
    printf("\r\nThe server and its public ip addresses match. \r\nNo changes is therefore necesary.\r\n\r\n");
    exit();
}

/* Else, update database and soa zone files with the new ip address */

$update1 = mysqli_query($ip_updater, 'UPDATE `dns_rr` SET `data` = replace(`data`, $stored_ip, $public_ip)');
$update2 = mysqli_query($ip_updater, 'UPDATE `server_ip` SET `ip_address` = replace(`ip_address`, $stored_ip, $public_ip)');
foreach (glob('/etc/bind/pri.*') as $filename) {
	$file = file_get_contents($filename);
	file_put_contents($filename, preg_replace('/$stored_ip/', '$public_ip', $file));
}

/* Now resync so that above changes updated properly. Important! 
   Refer to http://ipupdater.sch.my and download or copy
   resync.php to ipu_resync,php and app.inc.php to 
   ipu_app.inc.php. Disable admin check and tpl in ipu_resync.php
   and start_session() in  ipu_app.inc.php and change require 
   once in ipu_resync.php to ipu_app.inc.php. 
   Then double check if the update truly works */

require_once 'ipu_resync.php';
$query_new_ip = mysqli_query($ip_updater, 'SELECT ip_address FROM server_ip WHERE server_id =1');
list ($new_stored_ip) = mysqli_fetch_row($query_new_ip);
if ($new_stored_ip != $public_ip) {
    printf("\r\nUpdates failed! \r\nUpdates failed! \r\nUpdates failed! \r\n\r\n");
    exit();
} else { printf("\r\nUpdates are successful! Thanks God. \r\n\r\n"); }

/* Lastly, close database connection and restart apache. */
mysqli_close($ip_updater);
printf("\r\nClosing connection... \r\n\r\n");
exec('service apache2 restart');
?>
