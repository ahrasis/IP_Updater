<?php
/*
Copyright (c) 2009-2019, Hj Ahmad Rasyid Hj Ismail "ahrasis" ahrasis@gmail.com
Project IP Updater for debian and ubuntu, ispconfig 3 and dynamic ipv4 ip users.
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
	require_once 'ipupdater.php';
	exit();
}

/*	Get database access by using ispconfig default configuration so no
	user and its password are disclosed. Exit if its connection failed */

require_once 'config.inc.php';
require_once 'app.inc.php';
$mysqli = new mysqli($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database']);

// Check connection //
if ($mysqli->connect_errno) {
	printf("\r\nConnection to ISPConfig database in dns server failed!\r\n", $mysqli->connect_error);
	exit();
}

// Get domains' details (id, origin, serial from dns_soa table //
$binds = $app->db->queryAllRecords("SELECT origin FROM dns_soa WHERE active = 'Y'");
if (!is_array($binds) || empty($binds)) {
	printf("\r\nNo useful data available in dns_soa table or it is empty.\r\n");
	exit();
}

// Proceed only if domain A record is available
foreach ($binds as $bind) {
	if (checkdnsrr($bind, 'A'))
		
		// Get cleaned domain name from $bind['origin']
		$bind = preg_replace('/\b\.$/', '', $bind['origin']);
	
		// Get the ipv4 of this domain and proceed further only if it has one (dynamic) ip 
		$ipv4 = gethostbynamel($bind);
		if (empty($ipv4[1])) {
			$ipv4 = gethostbyname($bind);
			
			// Now get zone and data from dns_rr table
			$query = $app->db->queryOneRecord("SELECT zone, data FROM dns_rr WHERE name LIKE '%$bind%' AND type = 'A' AND active = 'Y'");
			$zone = $query['zone']; $db_ip = $query['data'];

			// Update ip in column data of dns_rr table with new ip if its public ip is different //
			if ($ipv4 != $db_ip) {
				$update = mysqli_query($ip_updater, "UPDATE dns_rr SET data = replace(data, '$db_ip', '$ipv4') WHERE zone = '$zone'");

				// Check if this domain updated ip is the same as its public ip
				$requery = $app->db->queryOneRecord("SELECT data FROM dns_rr WHERE name LIKE '%$bind%' AND type = 'A'");
				list($new_ip) = mysqli_fetch_row($requery);
				if ($ipv4 != $new_ip)
					printf("\r\nIP update for $bind failed! \nCode may need some fixes or updates.\r\n\");
	
				// Firstly we update the serial in dns_rr table and resync
				$dns_rr = $app->db->queryOneRecord("SELECT id, serial FROM dns_rr WHERE name LIKE '%$bind%'");
				if(is_array($dns_rr) && !empty($dns_rr)) {
					foreach($dns_rr as $rec) {
						$new_serial = $app->validate_dns->increase_serial($rec["serial"]);
						$app->db->datalogUpdate('dns_rr', "serial = '".$new_serial."'", 'id', $rec['id']);
					}
				}
				
				// Now we update the serial in dns_soa table and resync
				$dns_soa = $app->db->queryOneRecord("SELECT id, serial FROM dns_soa WHERE origin LIKE '%$bind%'");
				if(is_array($dns_soa) && !empty($dns_soa)) {
					foreach($dns_soa as $rec) {
						$new_serial = $app->validate_dns->increase_serial($rec["serial"]);
						$app->db->datalogUpdate('dns_soa', "serial = '".$new_serial."'", 'id', $rec['id']);
					}
				}
			}
		}
	}
}

/* close connection */
$mysqli->close();

?>
