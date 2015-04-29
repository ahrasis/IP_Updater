<?php
/*
Copyright (c) 2015, Ahmad Rasyid Ismail, ahrasis@gmail.com
Project IP Updater for ispconfig and dynamic ip users.
BSD License. All rights reserved.

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

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
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

// Get access by using ispconfig default
require_once '/usr/local/ispconfig/interface/lib/config.inc.php';
// Create and check connection to proceed
$ip_updater = mysql_connect($conf['db_host'], $conf['db_user'], $conf['db_password']);
if(!$ip_updater) {
	echo "Connection failed! \r\n"; die(mysql_connect_error());
} else {
	// Connection is fine. Select the database
	$db_selection = mysql_select_db($conf['db_database'], $ip_updater);
	if(!$db_selection) {
		echo "Can\'t use selected database! \r\n"; die(mysql_error());
	} else {
		// Databse is selected. Get public ip. Use others if this not working.
		$public_ip = file_get_contents('http://phihag.de/ip/');
		if(!filter_var($public_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === true) {
			echo "Filtering failed!";  die (mysql_error());
		} else {
			// IPv4 is true. Get stored ip (only from ip_address column in server_ip table) // echo "Comparing public IP " . $public_ip . " with ";
			$select_ip = "SELECT ip_address FROM server_ip WHERE sys_userid =1";
			$query_ip = mysql_query($select_ip);
			list ($stored_ip) = mysql_fetch_row($query_ip);
			// echo "stored IP " . $stored_ip . ". \r\n";
			// Update stored ip only if there is a change
			if($public_ip == $stored_ip) {
				echo "Same IP address. No changes is made." . "\r\n"; die (mysql_error());
			} else {
				echo "Different IP address. Attempting updates... \r\n";
				// Updating database
				$update_ip = mysql_query("UPDATE `dns_rr` SET `data` = replace(`data`, '$stored_ip', '$public_ip')");
				// $update_ip2 = mysql_query("UPDATE `sys_datalog` SET `data` = replace(`data`, '$stored_ip', '$public_ip')");
				$update_ip3 = mysql_query("UPDATE `server_ip` SET `ip_address` = replace(`ip_address`, '$stored_ip', '$public_ip')");
				// Updating soa zones serial and ip address assuming they are all the same.
				foreach (glob("/etc/bind/pri.*") as $filename) {
					$date=date_create();
					$new_serial=date_format($date,"YmdHis"); // echo "New serial: ".$new_serial."\r\n"; to see new serial
					$dns = dns_get_record("your.domain.tld", DNS_SOA); // print_r($dns); to check for right array
					$old_serial=$dns[0]['serial']; // echo "Old serial: ".$old_serial."\r\n"; to see old serial number
					$file = file_get_contents($filename);
					if($old_serial < $new_serial) {
						file_put_contents($filename, preg_replace("/$old_serial/","$new_serial",$file)); // Update serial
					}
					if($stored_ip != $public_ip) {
						file_put_contents($filename, preg_replace("/$stored_ip/","$public_ip",$file)); // Update public ip
					}
				}
			}
			// Check the updates mysql_query
			if(!($update_ip || $update_ip2 || $update_ip3)) {
				echo "Public ip should now be the same with stored ip. :( \r\n"; die (mysql_error());
			}
			echo "Give sometimes for the updates. In the meantime, we restart apache. \r\n";
			exec('service apache2 restart');
			// Double check stored ip as update mysql_query always returns successful :(
			$select_new_ip = "SELECT ip_address FROM server_ip WHERE sys_userid =1";
			$query_new_ip = mysql_query($select_new_ip);
			list ($new_stored_ip) = mysql_fetch_row($query_new_ip);
			if ($new_stored_ip != $public_ip) && ( {
				echo "Updates failed! \r\n"; die (mysql_error());
			} else {
				echo "Updates are successful! Thanks God." . "\r\n";
			}
		}
	}
}
// Close connection
mysql_close($ip_updater);
echo "Closing connection... \r\n";
exec('service apache2 restart');
?>
