<?php

function dnsgateway_getCreds($params) {
	$testmode = $params["TestMode"];
	$test_prefix = $testmode ? "OTE" : "PROD";
	$host = $params[$test_prefix . "_URL"];
	$username = $params[$test_prefix . "_Username"];
	$password = $params[$test_prefix . "_Password"];
	
	$host = rtrim($host, '/') . '/'; // Append a trailing slash
	
	return array("host" => $host, "username" => $username, "password" => $password, "debug" => $params["DebugMode"]);
}

function dnsgateway_sendCommand($creds, $method, $command, $payload) {
	error_log("Attempting " . $method . " " . $command . " with username: " . $creds["username"]);
	
	$payload = dnsgateway_cleanData($payload);
	
	$jsonformat = json_encode($payload); // Convert it to JSON format
	
	$process = curl_init($creds["host"] . $command); // A cURL command which can issue the request to the remote server
	
	curl_setopt($process, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Basic ". base64_encode($creds["username"] . ":" . $creds["password"]))); // Set the data type to be JSON
	curl_setopt($process, CURLOPT_HEADER, 0); 
	curl_setopt($process, CURLOPT_TIMEOUT, 30); // The timeout for this connection attempt, if not results are received within 30 seconds, disconnect
	curl_setopt($process, CURLOPT_CUSTOMREQUEST, $method); // Let the invoker select the method
	
	// If method is GET then POSTFIELDS should be PARAMS
	curl_setopt($process, CURLOPT_POSTFIELDS, $jsonformat); // The payload we're sending to the server
	curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE); // Allows us to use the resulting output as a string variable
	curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE); // Don't verify the peer certificate, without this the command will fail because gateway-otande uses a self-signed certificate
	curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE); // Don't verify the peer host information against the provided certificate because it's a self-signed certificate
	
	$return = curl_exec($process); // Run the cURL command
	$code = curl_getinfo($process, CURLINFO_HTTP_CODE);

 	curl_close($process); // Close the connection
	
	error_log("Performed cURL for " . $command . " with result " . $code);
	
	$results = json_decode($return, true); // Decode the JSON string to an associative array for processing
	
	if ( $code >= 300 && array_key_exists("message", $results)) {
		throw new Exception("Server error (".$code."): ".$results["message"]);
	} else if ( !array_key_exists("results", $results) && array_key_exists("message", $results) && $code >= 300 ) {
		throw new Exception("Server error (".$code."): ".$results["message"]);
	} else if ( !array_key_exists("results", $results) && array_key_exists("detail", $results) && $code >= 300 ) {
		throw new Exception("Server error (".$code."): ".$results["detail"]);
	} else if ($code >= 300) {
		throw new Exception("Server error (".$code."): ".$return);
	} // Everything under HTTP Result code 300 is a success in my book
	
	$results["success"] = true;
	
	return $results; 
}

function dnsgateway_contactInfo($creds, $emailaddress) {
	if ($creds['debug']) {
		logActivity("dnsgateway_contactInfo: " + $emailaddress);
	}
	
	$command = "registry/contacts/?search=" . $emailaddress;
	$method = "GET";
	
	return dnsgateway_sendCommand($creds, $method, $command, NULL);	
}

function dnsgateway_domainInfo($creds, $domainname) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainInfo: " + $domainname);
	}
	
	$command = "registry/domains/?search=" . $domainname;
	$method = "GET";
	
	$domain_info_res = dnsgateway_sendCommand($creds, $method, $command, NULL);
	
	if ($domain_info_res && $domain_info_res["count"] > 0) {
		$command = "registry/domains/" . $domain_info_res["results"][0]["wid"] . "/"; // Refetch the full payload following a search
		return dnsgateway_sendCommand($creds, $method, $command, NULL);
	} else {
		throw new Exception("Domain name '" . $domainname . "' not found");
	}	
}

function dnsgateway_contactCreate($creds, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_contactCreate: " + implode(",", $fields));
	}
	
	$command = "registry/contacts/";
	$method = "POST";
	
	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainCreate($creds, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainCreate: " + implode(",", $fields));
	}
	
	$command = "registry/domains/";
	$method = "POST";
	
	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainRenew($creds, $id, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainRenew: " + implode(",", $fields));
	}
	
	$command = "registry/domains/" . $id . "/renew/";
	$method = "POST";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainSuspend($creds, $id, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainSuspend: " + implode(",", $fields));
	}
	
	$command = "registry/domains/" . $id . "/suspend/";
	$method = "POST";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainUnsuspend($creds, $id, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainUnsuspend: " + implode(",", $fields));
	}
	
	$command = "registry/domains/" . $id . "/unsuspend/";
	$method = "POST";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainLock($creds, $id, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainLock: " + implode(",", $fields));
	}
	
	$command = "registry/domains/" . $id . "/lock/";
	$method = "POST";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainUnlock($creds, $id, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainUnlock: " + implode(",", $fields));
	}
	
	$command = "registry/domains/" . $id . "/unlock/";
	$method = "POST";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainDelete($creds, $id, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainDelete: " + implode(",", $fields));
	}
	
	$command = "registry/domains/" . $id . "/";
	$method = "DELETE";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainUpdate($creds, $id, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainUpdate: " + implode(",", $fields));
	}
	
	$command = "registry/domains/" . $id . "/";
	$method = "PUT";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_domainTransfer($creds, $fields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_domainTransfer: " + implode(",", $fields));
	}
	
	$command = "registry/domains/transfer-request/";
	$method = "POST";

	return dnsgateway_sendCommand($creds, $method, $command, $fields);
}

function dnsgateway_getContactID($creds, $emailaddress, $contactFields) {
	if ($creds['debug']) {
		logActivity("dnsgateway_getContactID: " + $emailaddress);
	}
	
	$contact = dnsgateway_contactInfo($creds, $emailaddress);

	$contact_id = NULL;
	if ($contact && $contact["count"] > 0) {
		$contact_id = $contact["results"][0]["id"];
	} else {
		$contact_id = substr(base64_encode($emailaddress), 0, 16);
		$contactFields["id"] = $contact_id;
		$contact = dnsgateway_contactCreate($creds, $contactFields);
		$contact_id = $contact["results"]["id"];
	}
	
	return $contact_id;
}

function dnsgateway_generatePassword() { // Conforms to Verisign's password requirements
	$alphabet_lower = 'abcdefghijklmnopqrstuvwxyz';
	$alphabet_upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$alphabet_numeric = '1234567890';
	$alphabet_special = '!"#$%&\'(;<>=?@[\)*+,-./:]^_`{|}~';
	
	$pass = array(); 
	$alphaLength = strlen($alphabet_lower) - 1; // 6 lower case letters
	for ($i = 0; $i < 6; $i++) {
		$n = rand(0, $alphaLength);
		$pass[] = $alphabet_lower[$n];
	}
	
	$alphaLength = strlen($alphabet_upper) - 1; // 2 upper case letters
	for ($i = 0; $i < 2; $i++) {
		$n = rand(0, $alphaLength);
		$pass[] = $alphabet_upper[$n];
	}
	
	$alphaLength = strlen($alphabet_numeric) - 1; // 4 numbers
	for ($i = 0; $i < 4; $i++) {
		$n = rand(0, $alphaLength);
		$pass[] = $alphabet_numeric[$n];
	}
	
	$n = rand(0, strlen($alphabet_special) - 1); // 1 special character
	$pass[] = $alphabet_special[$n];
	
	
	return str_shuffle(implode($pass)); //turn the array into a string and shuffle
}

function dnsgateway_cleanData($payload) {
	if (is_null($payload["authinfo"])) { // Remove authInfo from payload if it"s NULL from the server
		unset($payload["authinfo"]);
	}
	
	if (array_key_exists("contacts", $payload)) { // Reset all contacts to the format { "type": x, "contact" { "id": y }} to prevent validation errors from the API
		for ($i = 0; $i < count($payload["contacts"]); $i++) {
			if (array_key_exists("contact", $payload["contacts"][$i])) {
				$payload["contacts"][$i]["contact"] = array( 
						"id" => $payload["contacts"][$i]["contact"]["id"],						
					);
			}
		}
	}
	
	return $payload;
}

function dnsgateway_getConfigArray() {
	$configarray = array(
		"FriendlyName" => array("Type" => "System", "Value" => "DNS Africa Ltd Gateway"),
		"Description" => array("Type" => "System", "Value" => "For accreditation and zone activations visit the <a href='https://portal.dns.net.za'>DNS Africa Ltd Portal</a>"),
		"OTE_Username" => array( "Type" => "text", "Size" => "20", "FriendlyName" => "OT&E Username", "Description" => "Enter your OT&E username here", ),
		"OTE_Password" => array( "Type" => "password", "Size" => "20", "FriendlyName" => "OT&E Password", "Description" => "Enter your OT&E password here", ),
		"OTE_URL" => array( "Type" => "text", "Size" => "128", "FriendlyName" => "OT&E URL", "Description" => "Enter the OT&E API URL here", "Default" => "https://gateway-otande.dns.net.za/"),
		"TestMode" => array( "Type" => "yesno", ),
		"PROD_Username" => array( "Type" => "text", "Size" => "20", "FriendlyName" => "Production Username", "Description" => "Enter your production username here", ),
		"PROD_Password" => array( "Type" => "password", "Size" => "20", "FriendlyName" => "Production Password", "Description" => "Enter your production password here", ),
		"PROD_URL" => array( "Type" => "text", "Size" => "128", "FriendlyName" => "Production URL", "Description" => "Enter the production API URL here", ),
		"DebugMode" => array( "Type" => "yesno", "FriendlyName" => "Debug Mode", "Description" => "Enables the logging of user activity"),
	);
	return $configarray;
}

function dnsgateway_GetNameservers($params) {
	error_log("dnsgateway_GetNameservers: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	$values = array();
	try {
		$domain_info = dnsgateway_domainInfo($creds, $domainname); 
		
		for ($x = 0; $x < count($domain_info['hosts']); $x++) {
			$values["ns" . ($x+1)] = $domain_info['hosts'][$x]['hostname'];
		}	
		
		logModuleCall("dnsgateway", "GetNameservers", $params, $domain_info, $values, ["eppcode", "authinfo"]);
		
		return $values;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

function dnsgateway_SaveNameservers($params) {
	error_log("dnsgateway_SaveNameservers: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	try {
		$domain_info = dnsgateway_domainInfo($creds, $domainname);
		
		$domain_info['hosts'] = array(); // Reset hosts
		for ($x = 1; $x < 6; $x++) {
			$key = "ns" . $x; // In the format ns1..5 to search $params
			if (array_key_exists($key, $params) && !empty($params[$key])) { // Check existence and value
				array_push($domain_info['hosts'], array( 
						"hostname" => $params[$key],						
					)
				);
			}
		}
		
		$res = dnsgateway_domainUpdate($creds, $domain_info['wid'], $domain_info);
		
		logModuleCall("dnsgateway", "SaveNameservers", $params, $domain_info, $res, ["eppcode", "authinfo"]);
		
		return $res;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

function dnsgateway_GetRegistrarLock($params) {
	error_log("dnsgateway_GetRegistrarLock: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	$lock = "0";
	try {
		$domain_info = dnsgateway_domainInfo($creds, $domainname); 
		
		for ($x = 0; $x < count($domain_info['statuses']); $x++) {
			if ($domain_info['statuses'][$x]["code"] == "clientTransferProhibited") {
				$lock = "1";
				break;
			}
		}		
		
		logModuleCall("dnsgateway", "GetRegistrarLock", $params, $domain_info, $domain_info, ["eppcode", "authinfo"]);
		
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
	
	if ($lock=="1") {
		$lockstatus="locked";
	} else {
		$lockstatus="unlocked";
	}
	
	return $lockstatus;
}

function dnsgateway_SaveRegistrarLock($params) {
	error_log("dnsgateway_SaveRegistrarLock: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	if ("locked" == $params['lockenabled']) {
		$lockstatus="locked";
	} else {
		$lockstatus="unlocked";
	}
	
	try {
		$domain_info = dnsgateway_domainInfo($creds, $domainname);
	
		if ($lockstatus == "locked") {
			$res = dnsgateway_domainLock($creds, $domain_info['wid'], $domain_info);
			
			logModuleCall("dnsgateway", "SaveRegistrarLock", $params, $domain_info, $res, ["eppcode", "authinfo"]);
			
			return $res;
		} else {
			$res = dnsgateway_domainUnlock($creds, $domain_info['wid'], $domain_info);

			logModuleCall("dnsgateway", "SaveRegistrarLock", $params, $domain_info, $res, ["eppcode", "authinfo"]);
			
			return $res;
		}
		
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

// // NO EMAIL FORWARDING... YET
// function dnsgateway_GetEmailForwarding($params) {
// 	$username = $params["Username"];
// 	$password = $params["Password"];
// 	$testmode = $params["TestMode"];
// 	$tld = $params["tld"];
// 	$sld = $params["sld"];
// 	# Put your code to get email forwarding here - the result should be an array of prefixes and forward to emails (max 10)
// 	foreach ($result AS $value) {
// 		$values[$counter]["prefix"] = $value["prefix"];
// 		$values[$counter]["forwardto"] = $value["forwardto"];
// 	}
// 	return $values;
// }
//
// function dnsgateway_SaveEmailForwarding($params) {
// 	$username = $params["Username"];
// 	$password = $params["Password"];
// 	$testmode = $params["TestMode"];
// 	$tld = $params["tld"];
// 	$sld = $params["sld"];
// 	foreach ($params["prefix"] AS $key=>$value) {
// 		$forwardarray[$key]["prefix"] =	$params["prefix"][$key];
// 		$forwardarray[$key]["forwardto"] =	$params["forwardto"][$key];
// 	}
// 	# Put your code to save email forwarders here
// }

function dnsgateway_GetDNS($params) {
	error_log("dnsgateway_GetDNS: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	$hostrecords = array();
	try {
		$domain_info = dnsgateway_domainInfo($creds, $domainname);
	
		for ($x = 0; $x < count($domain_info['hosts']); $x++) {
			$hostname = $domain_info["hosts"][$x]["hostname"];
			$glue = "";
			$class_field = "A";
			if (array_key_exists("glue", $domain_info["hosts"][$x]) && count($domain_info["hosts"][$x]["glue"] > 0)) {
				$glue = $domain_info["hosts"][$x]["glue"][0]["ip"];
				$class_field = "A" ? $domain_info["hosts"][$x]["glue"][0]["class_field"] == "v4" : "AAAA";				
			}
			
			array_push($hostrecords, array(
					"hostname" => $hostname,
					"type" => $class_field,
					"address" => $glue,
				)
			);
		}
		
		logModuleCall("dnsgateway", "GetDNS", $params, $domain_info, $hostrecords, ["eppcode", "authinfo"]);
		
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
	
	return $hostrecords;
}

function dnsgateway_SaveDNS($params) {
	error_log("dnsgateway_SaveDNS: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	try {
		$domain_info = dnsgateway_domainInfo($creds, $domainname);
	
		$domain_info['hosts'] = array(); // Reset hosts
		foreach ($params["dnsrecords"] AS $key=>$values) {
			$hostname = $values["hostname"];
			$class_field = "v4" ? $values["type"] == 'A' : "v6";
			$address = $values["address"];
			
			if (!empty($hostname) && !empty($address)) {
				array_push($domain_info['hosts'], array(
						"hostname" => $hostname,
						"glue" => array(
							array(
								"ip" => $address,
								"class_field" => $class_field
							),
						),
					)
				);
			}
		}
		
		$res = dnsgateway_domainUpdate($creds, $domain_info['wid'], $domain_info);
		
		logModuleCall("dnsgateway", "SaveDNS", $params, $domain_info, $res, ["eppcode", "authinfo"]);
		
		return $res;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

function dnsgateway_RegisterDomain($params) {
	error_log("dnsgateway_RegisterDomain: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$tld = $params["tld"];
	$sld = $params["sld"];
	$regperiod = $params["regperiod"];
	$nameserver1 = $params["ns1"];
	$nameserver2 = $params["ns2"];
	$nameserver3 = $params["ns3"];
	$nameserver4 = $params["ns4"];
	$nameserver5 = $params["ns5"];
	
	# Registrant Details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["phonenumber"];
	
	# Admin Details
	$AdminFirstName = $params["adminfirstname"];
	$AdminLastName = $params["adminlastname"];
	$AdminAddress1 = $params["adminaddress1"];
	$AdminAddress2 = $params["adminaddress2"];
	$AdminCity = $params["admincity"];
	$AdminStateProvince = $params["adminstate"];
	$AdminPostalCode = $params["adminpostcode"];
	$AdminCountry = $params["admincountry"];
	$AdminEmailAddress = $params["adminemail"];
	$AdminPhone = $params["adminphonenumber"];
	
	$RegistrantFields = array(
		"phone" => $RegistrantPhone, 
		"email" => $RegistrantEmailAddress, 
		"contact_address" => array(
			array(
				"real_name" => $RegistrantFirstName . " " . $RegistrantLastName, 
				"street" => $RegistrantAddress1, 
				"street2" => $RegistrantAddress2, 
				"city" => $RegistrantCity,
				"province" => $RegistrantStateProvince,
				"code" => $RegistrantPostalCode,
				"country" => $RegistrantCountry, // This needs to be converted to two letter country code
				"type" => "loc"
			)			
		)
	);
	
	$AdminFields = array(
		"phone" => $AdminPhone, 
		"email" => $AdminEmailAddress, 
		"contact_address" => array(
			array(
				"real_name" => $AdminFirstName . " " . $AdminLastName, 
				"street" => $AdminAddress1, 
				"street2" => $AdminAddress2, 
				"city" => $AdminCity,
				"province" => $AdminStateProvince,
				"code" => $AdminPostalCode,
				"country" => $AdminCountry, // This needs to be converted to two letter country code
				"type" => "loc"	
			)		
		)
	);
	
	try {
		$registrant_id = dnsgateway_getContactID($creds, $RegistrantEmailAddress, $RegistrantFields);
		$admin_id = dnsgateway_getContactID($creds, $AdminEmailAddress, $AdminFields);
		
		$domain = array(
			"name" => $sld . "." . $tld,
			"period" => $regperiod, 
			"period_unit" => "y", 
			"autorenew" => false,
			"authinfo" => dnsgateway_generatePassword(),
			"hosts" => array(), 
			"contacts" => array(
				array(
					"type" => "registrant", 
					"contact" => array(
						"id" => $registrant_id
					)
				), array(
					"type" => "admin", 
					"contact" => array(
						"id" => $admin_id
					)
				), array(
					"type" => "billing", 
					"contact" => array(
						"id" => $admin_id
					)
				), array(
					"type" => "tech", 
					"contact" => array(
						"id" => $admin_id
					)
				)
			)
		);
		
		if (!empty($nameserver1)) {
			array_push($domain["hosts"], array(
					"hostname" => $nameserver1
				)
			);
		}
		if (!empty($nameserver2)) {
			array_push($domain["hosts"], array(
					"hostname" => $nameserver2
				)
			);
		}
		if (!empty($nameserver3)) {
			array_push($domain["hosts"], array(
					"hostname" => $nameserver3
				)
			);
		}
		if (!empty($nameserver4)) {
			array_push($domain["hosts"], array(
					"hostname" => $nameserver4
				)
			);
		}
		if (!empty($nameserver5)) {
			array_push($domain["hosts"], array(
					"hostname" => $nameserver5
				)
			);
		}
		
		$res = dnsgateway_domainCreate($creds, $domain);
		
		logModuleCall("dnsgateway", "RegisterDomain", $params, $domain, $res, ["eppcode", "authinfo"]);
		
		return $res;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

function dnsgateway_TransferDomain($params) {
	error_log("dnsgateway_TransferDomain: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	try {
		$domain_info = array(
			"name" => $domainname,
			"period" => $params["regperiod"],
			"period_unit" => "y",
			"authinfo" => $params["transfersecret"],
		);	
	
		$res = dnsgateway_domainTransfer($creds, $domain_info);
		
		logModuleCall("dnsgateway", "TransferDomain", $params, $domain_info, $res, ["eppcode", "authinfo"]);
		
		return $res;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

function dnsgateway_RenewDomain($params) {
	error_log("dnsgateway_RenewDomain: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$tld = $params["tld"];
	$sld = $params["sld"];
	$regperiod = $params["regperiod"];
	
	try {
		$domain_info = dnsgateway_domainInfo($creds, $sld . "." . $tld);
		
		$domain = array(
			"name" => $sld . "." . $tld,
			"period" => $regperiod,
			"period_unit" => "y",
			"curExpDate" => $domain_info["curExpDate"],				
		);
		
		$values = dnsgateway_domainRenew($creds, $domain_info["wid"], $domain);
		
		$values['expirydate'] = $values["curExpDate"];
		
		logModuleCall("dnsgateway", "RenewDomain", $params, $domain_info, $values, ["eppcode", "authinfo"]);
		
		return $values;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}	
}

function dnsgateway_RequestDelete($params) {
	error_log("dnsgateway_RequestDelete: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);

	$tld = $params["tld"];
	$sld = $params["sld"];
	
	try {
		$domain_info = dnsgateway_domainInfo($creds, $sld . "." . $tld);

		$domain = array(
			"name" => $sld . "." . $tld,
		);
		
		$res = dnsgateway_domainDelete($creds, $domain_info["wid"], $domain);
		
		logModuleCall("dnsgateway", "RequestDelete", $params, $domain_info, $res, ["eppcode", "authinfo"]);
		
		return $res;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

function dnsgateway_GetContactDetails($params) {
	error_log("dnsgateway_GetContactDetails: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	$values = array();
	try {
		$domain_info = dnsgateway_domainInfo($creds, $domainname);
		
		if (array_key_exists("contacts", $domain_info)) {
			for ($x = 0; $x < count($domain_info["contacts"]); $x++) {
				$contact = $domain_info["contacts"][$x];
				$key = ucfirst($contact["type"]); // Capitalise the contact type, maps 1:1 with WHMCS requirements
				$name_array = explode(' ', $contact["contact_address"][0]["real_name"], 2); // Split real name on the first space
				$values[$key] = array(
					"First Name" => $name_array[0],
					"Last Name" => $name_array[1],
					"Organization Name" => $contact["contact_address"][0]["org"],
					"Address" => $contact["contact_address"][0]["street"],
					"Address1" => $contact["contact_address"][0]["street2"],
					"City" => $contact["contact_address"][0]["city"],
					"State" => $contact["contact_address"][0]["province"],
					"Postcode" => $contact["contact_address"][0]["code"],
					"Country" => $contact["contact_address"][0]["country"],
					"Phone" => $contact["contact_address"]["phone"],
					"Fax" => $contact["contact_address"]["fax"],
					"Email" => $contact["contact_address"]["email"],
				);
			}
		}
		
		logModuleCall("dnsgateway", "GetContactDetails", $params, $domain_info, $values, ["eppcode", "authinfo"]);
		
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
	
	return $values;
}

function dnsgateway_SaveContactDetails($params) {
	error_log("dnsgateway_SaveContactDetails: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	try {
		$RegistrantFields = array(
			"phone" => $params["contactdetails"]["Registrant"]["Phone"],
			"fax" => !empty($params["contactdetails"]["Registrant"]["Fax"]) ? $params["contactdetails"]["Registrant"]["Fax"] : "",
			"email" => $params["contactdetails"]["Registrant"]["Email"],
			"contact_address" => array(
				array(
					"real_name" => $params["contactdetails"]["Registrant"]["First Name"] . " " . $params["contactdetails"]["Registrant"]["Last Name"],
					"street" => $params["contactdetails"]["Registrant"]["Address"],
					"street2" => $params["contactdetails"]["Registrant"]["Address1"],
					"city" => $params["contactdetails"]["Registrant"]["City"],
					"province" => $params["contactdetails"]["Registrant"]["State"],
					"code" => $params["contactdetails"]["Registrant"]["Postcode"],
					"country" => $params["contactdetails"]["Registrant"]["Country"], // This needs to be converted to two letter country code
					"type" => "loc"
				)
			)
		);
		
		$AdminFields = array(
			"phone" => $params["contactdetails"]["Admin"]["Phone"],
			"fax" => !empty($params["contactdetails"]["Admin"]["Fax"]) ? $params["contactdetails"]["Admin"]["Fax"] : "",
			"email" => $params["contactdetails"]["Admin"]["Email"],
			"contact_address" => array(
				array(
					"real_name" => $params["contactdetails"]["Admin"]["First Name"] . " " . $params["contactdetails"]["Admin"]["Last Name"],
					"street" => $params["contactdetails"]["Admin"]["Address"],
					"street2" => $params["contactdetails"]["Admin"]["Address1"],
					"city" => $params["contactdetails"]["Admin"]["City"],
					"province" => $params["contactdetails"]["Admin"]["State"],
					"code" => $params["contactdetails"]["Admin"]["Postcode"],
					"country" => $params["contactdetails"]["Admin"]["Country"], // This needs to be converted to two letter country code
					"type" => "loc"
				)
			)
		);
		
		$registrant_id = dnsgateway_getContactID($creds, $params["contactdetails"]["Registrant"]["Email"], $RegistrantFields);
		$admin_id = dnsgateway_getContactID($creds, $params["contactdetails"]["Admin"]["Email"], $AdminFields);
		
		$domain_info = dnsgateway_domainInfo($creds, $domainname);
		
		if (array_key_exists("contacts", $domain_info)) {
			$domain_info["contacts"] = array(
				array(
					"type" => "registrant",
					"contact" => array(
						"id" => $registrant_id,	
					),						
				),
				array(
					"type" => "admin",
					"contact" => array(
						"id" => $admin_id,
					),
				),
				array(
					"type" => "tech",
					"contact" => array(
						"id" => $admin_id,
					),
				),
				array(
					"type" => "billing",
					"contact" => array(
						"id" => $admin_id,
					),
				),
			);
		}
	
		$res = dnsgateway_domainUpdate($creds, $domain_info['wid'], $domain_info);

		logModuleCall("dnsgateway", "SaveContactDetails", $params, $domain_info, $res, ["eppcode", "authinfo"]);
		
		return ["status" => $res["detail"]];
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

function dnsgateway_GetEPPCode($params) {
	error_log("dnsgateway_GetEPPCode: " . print_R($params, TRUE) );
	
	$creds = dnsgateway_getCreds($params);
	
	$domainname = $params["sld"] . "." . $params["tld"];
	
	try {
		$values = dnsgateway_domainInfo($creds, $domainname);
		
		$values["eppcode"] = $values["authinfo"];
		
		logModuleCall("dnsgateway", "GetEPPCode", $params, $values, $values, [$values["authinfo"]]);
		
		return $values;
	} catch (Exception $e) {
		return array("error" => $e->getMessage()); // Return the underlying error
	}
}

// // Nameserver functionality it attached to domain updates
// function dnsgateway_RegisterNameserver($params) {
//		$username = $params["Username"];
// 	$password = $params["Password"];
// 	$testmode = $params["TestMode"];
// 	$tld = $params["tld"];
// 	$sld = $params["sld"];
//		$nameserver = $params["nameserver"];
//		$ipaddress = $params["ipaddress"];
//		# Put your code to register the nameserver here
//		# If error, return the error message in the value below
//		$values["error"] = $error;
//		return $values;
// }
// 
// function dnsgateway_ModifyNameserver($params) {
//		$username = $params["Username"];
// 	$password = $params["Password"];
// 	$testmode = $params["TestMode"];
// 	$tld = $params["tld"];
// 	$sld = $params["sld"];
//		$nameserver = $params["nameserver"];
//		$currentipaddress = $params["currentipaddress"];
//		$newipaddress = $params["newipaddress"];
//		# Put your code to update the nameserver here
//		# If error, return the error message in the value below
//		$values["error"] = $error;
//		return $values;
// }
// 
// function dnsgateway_DeleteNameserver($params) {
//		$username = $params["Username"];
// 	$password = $params["Password"];
// 	$testmode = $params["TestMode"];
// 	$tld = $params["tld"];
// 	$sld = $params["sld"];
//		$nameserver = $params["nameserver"];
//		# Put your code to delete the nameserver here
//		# If error, return the error message in the value below
//		$values["error"] = $error;
//		return $values;
// }

?>