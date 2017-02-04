<?php
/*
Copyright (c) 2015, Ahmad Rasyid Ismail, ahrasis@gmail.com
Project IP Updater for ubuntu, ispconfig 3 and dynamic ip users.
BSD3 License. All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

*	Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.
*	Redistributions in binary form must reproduce the above copyright notice,
	this list of conditions and the following disclaimer in the documentation
	and/or other materials provided with the distribution.
*	Neither the name of ISPConfig nor the names of its contributors
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

/*	Check internet access by using accessing www.google.com. Log its 
	error then restart ip updater if its connection failed. */

$sock = fsockopen('www.google.com', 80, $errno, $errstr, 10);
if (!$sock) {
        printf("\r\nNo connection to internet! Retry IP Updater again.\r\n, $errstr ($errno)\r\n");
        require_once 'ip_updater.php';
        exit();
}

/*	Get database access by using ispconfig default configuration so no
	user and its password are disclosed. Exit if its connection failed */

require_once 'config.inc.php';
$ip_updater = mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database']);
if (mysqli_connect_errno()) {
        printf("\r\nConnection to ISPConfig database failed!\r\n", mysqli_connect_error());
        exit();
}

/*	Else, it works. Now get public ip from a reliable source.
	We are using this but you can define your own. But We just
	need ipv4. So we exit if its filtering failed */

$public_ip = file_get_contents('http://ip.sch.my/');
if(!filter_var($public_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === true) {
	printf("\r\nIPV4 for public IP filtering failed! \r\nYou may need to use other ip source. \r\n\r\n");
	exit();
}

/*	Else, it's truly ipv4. Now we obtain the server ip,
	based on server id. Do change your server id accordingly. */

$query_ip = mysqli_query($ip_updater, 'SELECT ip_address FROM server_ip WHERE server_id =1');
list($db_ip) = mysqli_fetch_row($query_ip);

/*	Other than the above ip, we also need soa ip from bind files */

$binds = glob('/etc/bind/pri.*'); 
foreach ($binds as $bind)
	$filename[] = $bind;
$matcher = file_get_contents($filename[0]);
preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $matcher, $matched);
$soa_ip = $matched[0];

/*	If the server public ip matches both, exit. Log is
	for error only, so we close database connection and restart
	apache without any logging for now on. However, you may
	enable it, by uncommenting the log line below this. */

if(($public_ip == $db_ip) && ($public_ip == $soa_ip)) {
	// printf("\r\nThe server, soa zone files and public ip addresses match. \r\n\r\n");
	exit();
}

/* If not, start to change soa zone files with the new ip */ 

if($public_ip != $soa_ip) {
	foreach ($binds as $bind) {
		$file = file_get_contents($bind);
		file_put_contents($bind, preg_replace("/$soa_ip/", "$public_ip", $file));
	}

	// Warn and update if soa zone files update failed.
	foreach(file($bind) as $binding=>$b) {
		if(strpos($b, "$soa_ip")==true) {
			printf("\r\nSOA zone files updates failed! \r\nZone files updating code may need a fix or update. \r\n\r\n");
			exit();
		}
	}
}

/* Then, we update our database with the new ip */

if($public_ip != $db_ip) {
	$update1 = mysqli_query($ip_updater, "UPDATE dns_rr SET data = replace(data, '$db_ip', '$public_ip')");
	$update2 = mysqli_query($ip_updater, "UPDATE server_ip SET ip_address = replace(ip_address, '$db_ip', '$public_ip')");

	// Warn and exit if database update failed.
	$query_new_ip = mysqli_query($ip_updater, 'SELECT ip_address FROM server_ip WHERE server_id =1');
	list($db_new_ip) = mysqli_fetch_row($query_new_ip);
	if ($public_ip != $db_new_ip) {
		printf("\r\nDatabase updates failed! \r\nDatabase updating code may need a fix or update. \r\n\r\n");
		exit();
	}
}

/*	Now resync so that above changes updated properly. Important! 
	Do refer to http://ipupdater.sch.my and download or copy
	resync.php to ipu_resync,php and app.inc.php to 
	ipu_app.inc.php. Disable admin check and tpl in ipu_resync.php
	and start_session() in ipu_app.inc.php and change require 
	once in ipu_resync.php to ipu_app.inc.php. */

require_once 'ipu_resync.php';

/*	Lastly, congratulations! All updates are successful. Log is
	for error only, so we close database connection and restart
	apache without any logging for now on. However, you may
	enable it, by uncommenting the line below this. */

// printf("\r\nDatabase and SOA zone files updates are successful! \r\n\r\n");

mysqli_close($ip_updater);

/*	You should define your server software to restart if it is not here. */

if (strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache') !== false) 
	exec('service apache2 restart');
if (strpos( $_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) 
	exec('service nginx restart');

/* Comment this out if you do not want to reboot afterwards */
exec('reboot');

?>
