<?php
    /*
     * Approved by ModulesGarden
     *
     * This modules allows you to transfer, register, check availability and
     * use other fucntions for domains within whmcs.
     *
     * The modules files must be stored in: modules/registrars/
     * The modules name must always be lowercase and start with a letter
     * and only use letter and numbers for the modules name.
     *
     * The modules functions all must start with
     * the modules name followed by an understcore
     * for example ModuleName_FunctionName(){
     *}
     * @copyright Copyright (c) DNS Africa 2023
     * @author  David Peall <david@dns.business>
     */

    if (!defined('WHMCS')) {
        die('This file cannot be accessed directly');
    }

    use \WHMCS\Domains\DomainLookup\ResultsList;
    use \WHMCS\Domains\DomainLookup\SearchResult;
    use \WHMCS\Exception\Module\InvalidConfiguration;
    use \WHMCS\Module\Registrar\dns_gateway\DNSAPI;
    use \WHMCS\Database\Capsule;
    use \WHMCS\Domain\TopLevel\ImportItem;


    // Require any libraries needed for the modules to function.

    /**
     * Define modules related metadata
     *
     * Provide some modules information including
     * the display name and API Version to
     * determine the method of decoding the input values.
     *
     * @return array
     */

    function dns_gateway_MetaData()
    {
        $meta = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "whmcs.json"), true);
        return [
            'DisplayName' => $meta["description"]["name"],
            'APIVersion' => '1.1',
        ];
    }

    /**
     * Define registrar configuration options.
     *
     * configuration file can be found in whmcs->
     * Setup->Products/Services->Domain Registrars->Your ModuleName -> Configure
     *
     * The following field types are supported:
     *  * Text
     *  * Password
     *  * Yes/No Checkboxes
     *  * Dropdown Menus
     *  * Radio Buttons
     *  * Text Areas
     */

    function dns_gateway_getConfigArray()
    {
        $meta = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "whmcs.json"), true);

        return [
            'FriendlyName' => [
                'Type' => 'System',
                'Value' => $meta['description']['name']
            ],
            'Description' => [
                'Type' => 'System',
                'Value' => $meta['description']['tagline']
            ],
            'Portal_Username' => [
                'FriendlyName' => 'Portal Username',
                'Type' => 'text',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Username for https://portal.dns.business/'
            ],
            'Portal_Password' => [
                'FriendlyName' => 'Password',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => ''
            ],
            // the dropdown field type renders a select menu of options
            'AccountMode' => [
                'FriendlyName' => 'Account Mode',
                'Type' => 'dropdown',
                'Size' => '25',
                'Options' => [
                    'live' => 'Live',
                    'ote' => 'Testing (OTE Account)'
                ],
                'Description' => 'Choose one',
            ],
            'debug' => [
                'FriendlyName' => 'Debug',
                'Type' => 'yesno',
                'Description' => 'Tick to enable',
            ],
        ];
    }

    /**
     * @throws InvalidConfiguration
     */
    function dns_gateway_config_validate($params)
    {
        //throw new InvalidConfiguration("to alert the UI if not correct.");
        try {

            new DNSAPI($params);
        } catch (Exception $e) {
            throw new InvalidConfiguration($e->getMessage());
        }
        return NULL;
    }


    # Function to register domain
    /*
     * Called when the registration of a new domain is initiated within WHMCS.
     *
     * Function will trigger a payment is made or a new domain is registered
     */

    function dns_gateway_RegisterDomain($params)
    {
        try {
            // Registration parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;
            $registrationPeriod = $params['regperiod'];

            //Nameservers
            $nameservers = [];
            for ($n = 1; $n <= 5; $n++) {
                if (isset($params['ns' . $n]) && $params['ns' . $n] != '') {
                    $nameservers[] = [
                        'hostname' => $params['ns' . $n],
                    ];
                }
            }

            // Connect To API
            $api = new DNSAPI($params);

            // Registrant information
            $registrant_info = [
                'id' => $api->generate_id($domain),
                'phone' => $params['fullphonenumber'],
                'fax' => NULL,
                'email' => $params['email'],
                'contact_address' => [
                    [
                        'real_name' => $params['fullname'],
                        'org' => $params['companyname'],
                        'street' => $params['address1'],
                        'street2' => $params['address2'],
                        'street3' => NULL,
                        'city' => $params['city'],
                        'province' => $params['state'],
                        'code' => $params['postcode'],
                        'country' => $params['countrycode'],
                        'type' => 'loc'
                    ],
                    [
                        'real_name' => iconv("UTF-8", "ASCII//TRANSLIT", $params['fullname']),
                        'org' => iconv("UTF-8", "ASCII//TRANSLIT", $params['companyname']),
                        'street' => iconv("UTF-8", "ASCII//TRANSLIT", $params['address1']),
                        'street2' => iconv("UTF-8", "ASCII//TRANSLIT", $params['address2']),
                        'street3' => NULL,
                        'city' => iconv("UTF-8", "ASCII//TRANSLIT", $params['city']),
                        'province' => iconv("UTF-8", "ASCII//TRANSLIT", $params['state']),
                        'code' => $params['postcode'],
                        'country' => $params['countrycode'],
                        'type' => 'int'
                    ]
                ]
            ];

            // Admin contact information.
            $admin_info = [
                'id' => $api->generate_id($domain),
                'phone' => $params['adminfullphonenumber'],
                'fax' => NULL,
                'email' => $params['adminemail'],
                'contact_address' => [
                    [
                        'real_name' => $params['adminfirstname'] .
                            ' ' . $params['adminlastname'],
                        'org' => $params['admincompanyname'],
                        'street' => $params['adminaddress1'],
                        'street2' => $params['adminaddress2'],
                        'street3' => NULL,
                        'city' => $params['admincity'],
                        'province' => $params['adminstate'],
                        'code' => $params['adminpostcode'],
                        'country' => $params['admincountry'],
                        'type' => 'loc'
                    ],
                    [
                        'real_name' => iconv("UTF-8", "ASCII//TRANSLIT", $params['adminfirstname']) .
                            ' ' . iconv("UTF-8", "ASCII//TRANSLIT", $params['adminlastname']),
                        'org' => iconv("UTF-8", "ASCII//TRANSLIT", $params['admincompanyname']),
                        'street' => iconv("UTF-8", "ASCII//TRANSLIT", $params['adminaddress1']),
                        'street2' => iconv("UTF-8", "ASCII//TRANSLIT", $params['adminaddress2']),
                        'street3' => NULL,
                        'city' => iconv("UTF-8", "ASCII//TRANSLIT", $params['admincity']),
                        'province' => iconv("UTF-8", "ASCII//TRANSLIT", $params['adminstate']),
                        'code' => $params['adminpostcode'],
                        'country' => $params['admincountry'],
                        'type' => 'int'
                    ]
                ]
            ];

            // Billing contact information.
            $billing_info = $admin_info;
            $billing_info["id"] = $api->generate_id($domain);

            // Tech contact information.
            $tech_info = $admin_info;
            $tech_info["id"] = $api->generate_id($domain);

            /**
             * Premium domain parameters.
             *
             * Premium domains enabled informs you
             * if the admin user has enabled
             * the selling of premium domain names.
             * If this domain is a premium name,
             * `premiumCost` will contain the cost
             * price retrieved at the time of
             * the order being placed. The premium
             * order should only be processed
             * if the cost price now matches the
             * previously fetched amount.
             */
            $premiumDomainsEnabled = (bool)$params['premiumEnabled'];
            $premiumDomainsCost = $params['premiumCost'];

            // Create Contacts
            $registrant_contact = $api->setcontact($registrant_info);
            $admin_contact = $api->setcontact($admin_info);
            $billing_contact = $api->setcontact($billing_info);
            $tech_contact = $api->setcontact($tech_info);

            // Register Domain
            $domain = [
                'name' => $domain,
                'period' => $registrationPeriod,
                'period_unit' => 'y',
                'autorenew' => false,
                'authinfo' => $api->generate_password($domain),
                'hosts' => $nameservers,
                'contacts' => [
                    [
                        'type' => 'registrant',
                        'contact' => [
                            'id' => $registrant_contact
                        ]
                    ],
                    [
                        'type' => 'admin',
                        'contact' => [
                            'id' => $admin_contact
                        ]
                    ],
                    [
                        'type' => 'billing',
                        'contact' => [
                            'id' => $billing_contact
                        ]
                    ],
                    [
                        'type' => 'tech',
                        'contact' => [
                            'id' => $tech_contact
                        ]
                    ]
                ]
            ];

            //  Add Premium Charge If Applicable
            if ($premiumDomainsEnabled && $premiumDomainsCost) {
                $domain['charge'] = array('price' => $premiumDomainsCost);
            }

            //  Call The Register Domain Function
            $api->register_domain($domain);

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage() . " " . $e->getTraceAsString()
            ];
        }
    }

    # Function to transfer a domain
    /*
     * Called when a domain transfer request is initiated within WHMCS.
     *
     * Function is triggered when a peding domain trasfer is accpeted,
     * when a payment is recieved for a domains transfer,
     * manual transfer by admin.
     */

    function dns_gateway_TransferDomain($params)
    {
        // Registration parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;
        $registrationPeriod = $params['regperiod'];
        $eppCode = htmlspecialchars_decode($params['eppcode']);
        if ($tld == 'co.za') {
            $eppCode = 'coza';
        }

        // Connect To API
        $api = new DNSAPI($params);

        /**
         * Premium domain parameters.
         *
         * Premium domains enabled informs you if
         * the admin user has enabled
         * the selling of premium domain names.
         * If this domain is a premium name,
         * `premiumCost` will contain the cost price
         * retrieved at the time of
         * the order being placed.
         * The premium order should
         * only be processed
         * if the cost price now
         * matches the previously fetched amount.
         */
        $premiumDomainsEnabled = (bool)$params['premiumEnabled'];
        $premiumDomainsCost = $params['premiumCost'];

        // Transfer Domain
        $domain = [
            'name' => $domain,
            'authinfo' => $eppCode,
            'period' => $registrationPeriod
        ];

        // Add Premium Charge If Applicable
        if ($premiumDomainsEnabled && $premiumDomainsCost) {
            $domain['charge']['price'] = $premiumDomainsCost;
        }

        try {
            //  Call The Register Domain Function
            $api->transfer_domain($domain);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    # Function to renew domain
    /*
     * Called when a request to renew a domain is initiated within WHMCS.
     *
     * Function is trigered when a payment is recvied for a domain renew order,
     * pending renew order is accepted,
     * manual renewal by admin.
     *
     * Attempt to renew/extend a domain for a given number of years.
     */

    function dns_gateway_RenewDomain($params)
    {
        try {
            // Registration parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;
            $registrationPeriod = $params['regperiod'];


            $api = new DNSAPI($params);


            // Get Domain info
            $domain_list = $api->list_domains($domain);

            // Renew Domain
            $renew_array = [
                'name' => $domain,
                'period' => $registrationPeriod,
                'period_unit' => 'y',
                'curExpDate' => $domain_list[0]['curExpDate']
            ];

            $api->renew_domain($renew_array);

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch current nameservers.
     *
     * This function should return an array of nameservers for a given domain.
     *
     * Called when a domain is viewed within WHMCS. It can
     * return up to 5 nameservers that are set for the domain.
     *
     * $sld = eg. yourdomain
     * $tld = eg. .com
     */

    function dns_gateway_GetNameservers($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);


            $domain_info = $api->get_domain_nameservers($domain);

            $count = 0;
            $nameservers = [];
            foreach ($domain_info['nameservers'] as $namserver) {
                $count++;
                $nameservers['ns' . $count] = $namserver['hostname'];
            }
            logModuleCall('dns_gateway', 'GetNameservers', $params, $domain_info);
            $nameservererror = 'Some message here!';
            return $nameservers;

        } catch (\Exception $e) {
            if (strpos($e->getMessage(), '404')) {
                return NULL;
            }
            return [
                'error' => $e->getMessage()
            ];
        }
    }


    # Function to save set of nameservers
    /*
     * Called when a change is submitted for a domains nameservers.
     *
     * $sld = eg. yourdomain
     * $tld = eg. .com
     */

    function dns_gateway_SaveNameservers($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;
            $registrationPeriod = (
            $params['regperiod'] > 0 ?
                $params['regperiod'] :
                1
            );

            // Connect To API
            $api = new DNSAPI($params);


            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);


            // Handle Nameservers
            $hosts = $domain_view['hosts'];
            $new_hosts = [];

            for ($n = 1; $n <= 5; $n++) {
                if (isset($params['ns' . $n]) && $params['ns' . $n] != '') {
                    // Compare Nameservers
                    $host_record = [
                        'hostname' => $params['ns' . $n]
                    ];
                    foreach ($hosts as $host_key => $host) {
                        if ($host['hostname'] == $params['ns' . $n]) {
                            $host_record['glue'] = $host['glue'];
                        }
                    }
                    // Add Nameserver
                    $new_hosts[] = $host_record;
                }
            }

            // Update Domain
            $update_array = [
                'name' => $domain,
                'period' => $registrationPeriod,
                'period_unit' => 'y',
                'authinfo' => $api->generate_password($domain),
                'hosts' => $new_hosts,
                'contacts' => $domain_view['contacts'],
            ];

            $api->update_domain($update_array, $domain_list[0]['wid']);

            return true;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }


    # Function to grab contact details
    /*
     * Called when the WHOIS information is displayed within WHMCS.
     *
     * Should return a multi-level array of the contacts and name/address
     * fields that be modified.
     */

    function dns_gateway_GetContactDetails($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);


            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);

            // Contacts Section
            $contacts = [];
            foreach ($domain_view['contacts'] as $contact) {
                // Grab Contact Info
                $contact_info = $api->contact_info($contact['contact']['id']);

                // Grab Contact Name And Divide It
                $name_parts = explode(
                    ' ',
                    $contact_info['contact_address'][0]['real_name']
                );
                $last_name = end($name_parts);
                array_pop($name_parts);
                $first_name = implode(' ', $name_parts);

                // Build Contact ID
                $contacts[ucfirst($contact['type'])] = [
                    'First Name' => $first_name,
                    'Last Name' => $last_name,
                    'Company Name' => $contact_info['contact_address'][0]['org'],
                    'Email Address' => $contact_info['email'],
                    'Address 1' => $contact_info['contact_address'][0]['street'],
                    'Address 2' => $contact_info['contact_address'][0]['street2'],
                    'City' => $contact_info['contact_address'][0]['city'],
                    'State' => $contact_info['contact_address'][0]['province'],
                    'Postcode' => $contact_info['contact_address'][0]['code'],
                    'Country' => $contact_info['contact_address'][0]['country'],
                    'Phone Number' => $contact_info['phone'],
                    'Fax Number' => $contact_info['fax']
                ];
            }
            return $contacts;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to save contact details
    /*
     * Called when revised WHOIS information is submitted.
     * Update the WHOIS Contact Information for a given domain.
     */

    function dns_gateway_SaveContactDetails($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);


            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);

            // Loop Contacts
            foreach ($domain_view['contacts'] as $contact) {
                // Updated Contact information
                $new_details = $params['contactdetails'][ucfirst($contact['type'])];

                // Contact information.
                $contact_info = [
                    'phone' => $new_details['Phone Number'],
                    'email' => $new_details['Email Address'],
                    'contact_address' => [
                        [
                            'real_name' => $new_details['First Name'] .
                                ' ' . $new_details['Last Name'],
                            'org' => $new_details['Company Name'],
                            'street' => $new_details['Address 1'],
                            'street2' => $new_details['Address 2'],
                            'street3' => NULL,
                            'city' => $new_details['City'],
                            'province' => $new_details['State'],
                            'code' => $new_details['Postcode'],
                            'country' => $new_details['Country'],
                            'type' => 'loc'
                        ],
                        [
                            'real_name' => iconv("UTF-8", "ASCII//TRANSLIT", $new_details['First Name']) .
                                ' ' . iconv("UTF-8", "ASCII//TRANSLIT", $new_details['Last Name']),
                            'org' => iconv("UTF-8", "ASCII//TRANSLIT", $new_details['Company Name']),
                            'street' => iconv("UTF-8", "ASCII//TRANSLIT", $new_details['Address 1']),
                            'street2' => iconv("UTF-8", "ASCII//TRANSLIT", $new_details['Address 2']),
                            'street3' => NULL,
                            'city' => iconv("UTF-8", "ASCII//TRANSLIT", $new_details['City']),
                            'province' => iconv("UTF-8", "ASCII//TRANSLIT", $new_details['State']),
                            'code' => $new_details['Postcode'],
                            'country' => $new_details['Country'],
                            'type' => 'int'
                        ]
                    ]
                ];

                if (isset($new_details['Fax Number']) && !empty($new_details['Fax Number'])) {
                    $contact_info['fax'] = $new_details['Fax Number'];
                }

                // Grab API Side Contact Info
                $api_contact = $api->contact_info($contact['contact']['id']);

                // Update Contact
                $api->update_contact($contact_info, $api_contact['wid']);
            }

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to check a domains availability and premium status
    /*
     * Check Domain Availability.
     * Determine if a domain is availability for registration or transfer.
     *
     * Check is the domain name is premium
     */
    function dns_gateway_CheckAvailability($params)
    {
        try {
            // Connect To API
            $api = new DNSAPI($params);

            // Initiate Results List
            $results = new ResultsList();

            // Loop Through Domains To Check Availability
            foreach ($params['tldsToInclude'] as $tld) {
                $searchResult = new SearchResult($params['sld'], $tld);
                $domain_check = $api->domain_check($params['sld'] . $tld);

                $reason = strtolower($domain_check['results'][0]['reason']);
                $available = (int)$domain_check['results'][0]['avail'];

                $status = SearchResult::STATUS_NOT_REGISTERED;
                if ($available == 0) {
                    if ($reason == "in use" or $reason == "domain exists") {
                        $status = SearchResult::STATUS_REGISTERED;

                    } elseif (strpos($reason, "domain reserved") !== false) {
                        $status = SearchResult::STATUS_RESERVED;
                    } else {
                        $status = SearchResult::STATUS_UNKNOWN;
                    }
                } elseif ($available > 1) {
                    $status = SearchResult::STATUS_UNKNOWN;
                }

                $searchResult->setStatus($status);
                $results->append($searchResult);

                // Return premium information if applicable
                if ($domain_check['charge']['category'] != "standard") {
                    $searchResult->setPremiumDomain(true);
                    $domaincheckaction = $domain_check['charge']['action'];
                    $searchResult->setPremiumCostPricing([
                        'register' => $domaincheckaction['create'],
                        'renew' => $domaincheckaction['renew'],
                        'transfer' => $domaincheckaction['transfer'],
                        'CurrencyCode' => 'USD'
                    ]);
                }

                if (strtolower($domain_check['results'][0]['reason']) == 'in use') {
                    $domain_check['results'][0]['reason'] = 'Domain exists';
                }
            }

            return $results;

        } catch (\Exception $e) {
            return array(
                'error' => $e->getMessage()
            );
        }
    }

    # Function to Check if Registrar is locked
    /*
     * Called when a domains details are viewed within WHMCS.
     * It should return the current lock status of a domain.
     *
     * If a domain is locked it will be unable to-
     * Update
     * Transfer or
     * Delete
     */

    function dns_gateway_GetRegistrarLock($params)
    {

        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Call API
            $api = new DNSAPI($params);

            $domain_sync = $api->domain_sync($domain);
            if (isset($domain_sync['statuses'])) {
                foreach ($domain_sync['statuses'] as $val) {
                    if ($val['code'] == 'clientTransferProhibited') {
                        $domain_lock = true;
                    }
                }
            }

            logModuleCall('dns_gateway', 'GetRegistrarLock', $params, $domain_sync);

            if ($domain_lock == true) {
                return 'locked';
            } else {
                return 'unlocked';
            }

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to save Registrar Lock
    /*
     * Called when the lock status setting is toggled within WHMCS.
     */

    function dns_gateway_SaveRegistrarLock($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            $api = new DNSAPI($params);

            if ($params['lockenabled'] == 'locked') {
                $api->domain_lock($domain);
            } else {
                $api->domain_unlock($domain);
            }

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Get epp code
    /*
     * Called when the EPP Code is requested for a transfer out.
     *
     * Can display EPP code directly to the user or
     * indicate that the code will be emailed to the registrant.
     */

    function dns_gateway_GetEPPCode($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            if (
                $tld != 'co.za' &&
                $tld != 'net.za' &&
                $tld != 'org.za' &&
                $tld != 'web.za'
            ) {
                // Call api
                $values["eppcode"] = DNSAPI::generate_password($domain);

                return $values;
            } else {
                return [
                    'error' => 'This TLD Does Not Support EPP Codes'
                ];
            }

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Request deletion of the domain name
    /*
     * Trigers when a domain deletion request comes from WHMCS or
     * manual deletion from admin. *
     */

    function dns_gateway_RequestDelete($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Call api
            $api = new DNSAPI($params);
            $domain_wid = $api->get_domain_wid($domain);

            $api->delete_domain($domain_wid);

            return array(
                'success' => 'success',
            );

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to register nameserver
    /*
     * Called when a child nameserver registration request comes from WHMCS
     */

    function dns_gateway_RegisterNameserver($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;
            $registrationPeriod = (
            $params['regperiod'] > 0 ?
                $params['regperiod'] :
                1
            );

            // Nameserver parameters
            $nameserver = $params['nameserver'];
            $ipAddress = $params['ipaddress'];

            // Connect To API
            $api = new DNSAPI($params);


            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);

            // Prepare Hosts
            $hosts = $domain_view['hosts'];
            $hosts[] = [
                'hostname' => $params['nameserver'],
                'glue' => [
                    [
                        'ip' => $params['ipaddress'],
                        'class_field' => 'v4'
                    ]
                ]
            ];

            // Update Domain & Hosts
            $update_array = [
                'name' => $domain,
                'period' => $registrationPeriod,
                'period_unit' => 'y',
                'authinfo' => $api->generate_password($domain),
                'hosts' => $hosts,
                'contacts' => $domain_view['contacts'],
            ];

            $api->update_domain($update_array, $domain_list[0]['wid']);

            return true;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Modify nameserver
    /*
     * Called when a child nameserver modification request comes from WHMCS
     *
     * Modifies the IP of a child nameserver.
     */

    function dns_gateway_ModifyNameserver($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;
            $registrationPeriod = (
            $params['regperiod'] > 0 ?
                $params['regperiod'] :
                1
            );

            // Nameserver parameters
            $nameserver = $params['nameserver'];
            $CurrentIpAddress = $params['currentipaddress'];
            $NewIpAddress = $params['newipaddress'];

            // Connect To API
            $api = new DNSAPI($params);

            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);

            // Prepare Hosts
            $hosts = $domain_view['hosts'];
            foreach ($hosts as $host_key => $host) {
                if ($host['hostname'] == $nameserver) {
                    // Go Through Glue Records
                    foreach ($host['glue'] as $glue_key => $glue) {
                        if ($glue['ip'] == $CurrentIpAddress) {
                            // Update Domain & Hosts
                            $hosts[$host_key]['glue'][$glue_key]['ip'] = $NewIpAddress;

                            $update_array = [
                                'name' => $domain,
                                'period' => $registrationPeriod,
                                'period_unit' => 'y',
                                'authinfo' => $api->generate_password($domain),
                                'hosts' => $hosts,
                                'contacts' => $domain_view['contacts'],
                            ];

                            $api->update_domain($update_array, $domain_list[0]['wid']);

                            return true;
                        }
                    }
                }
            }

            // Return Error If No Matching Hostname Was Found
            return [
                'error' => 'No Matching Hostname Was Found'
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Delete nameserver
    /*
     * Called when a child nameserver deletion request comes from WHMCS.
     */

    function dns_gateway_DeleteNameserver($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;


            // Nameserver parameters
            $nameserver = $params['nameserver'];

            // Connect To API
            $api = new DNSAPI($params);

            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);

            // Prepare Hosts
            $hosts = $domain_view['hosts'];
            $new_hosts = [];
            foreach ($hosts as $host_key => $host) {
                if ($host['hostname'] != $nameserver) {
                    $new_hosts[] = $host;
                }
            }
            // Update Domain & Hosts
            $update_array = [
                'name' => $domain,
                'period_unit' => 'y',
                'authinfo' => $api->generate_password($domain),
                'hosts' => $new_hosts,
                'contacts' => $domain_view['contacts'],
            ];
            $api->update_domain($update_array, $domain_list[0]['wid']);

            return true;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync Domain Status & Expiration Date.
     *
     * To ensure expiry date and status changes made
     * directly at the domain register are in sync with WHMCS.
     */

    function dns_gateway_Sync($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);

            // Grab Domain Info
            $domain_sync = $api->domain_sync($domain);

            // Build Return Result
            $result = [
                'expirydate' => NULL,
                'active' => false,
                'expired' => false,
                'transferredAway' => false
            ];

            if ($domain_sync['detail'] == 'Not found.') {
                $result['transferredAway'] = true;
                return $result;
            }

            if (isset($domain_sync['expiry'])) {
                $result['expirydate'] = date("Y-m-d", strtotime($domain_sync['expiry']));
            }

            $expiryTime = strtotime($domain_sync['expiry']);
            $currentTime = strtotime(date("Y-m-d\TH:i:s\Z"));

            if ($expiryTime > $currentTime) {
                $result['active'] = true;
            } else {
                $result['expired'] = true;
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /*
     * Update Domain Auto-renew
     */

    function update_autorenew($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);


            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);

            // Update Domain
            $update_array = [
                'name' => $domain,
                'period' => 1,
                'period_unit' => 'y',
                'hosts' => $domain_view['hosts'],
                'contacts' => $domain_view['contacts'],
                'autorenew' => false
            ];
            $api->update_domain($update_array, $domain_list[0]['wid']);

            return true;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /*
     * Update EPP Key
     */

    function dns_gateway_Update_EPP_key($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;
            $registrationPeriod = (
            $params['regperiod'] > 0 ?
                $params['regperiod'] :
                1
            );

            // Connect To API
            $api = new DNSAPI($params);

            // Grab Domain Info
            $domain_list = $api->list_domains($domain);
            $domain_view = $api->view_domain($domain_list[0]['wid']);

            // Update Domain
            $update_array = [
                'name' => $domain,
                'period' => $registrationPeriod,
                'period_unit' => 'y',
                'authinfo' => $api->generate_password($domain),
                'hosts' => $domain_view['hosts'],
                'contacts' => $domain_view['contacts'],
            ];
            $api->update_domain($update_array, $domain_list[0]['wid']);

            return true;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }


    /*
     * Incoming Domain Transfer Sync.
     *
     * This function will be called for every domain in the
     * Pending Transfer status when the domain sync cron runs.
     *
     * Check status of incoming domain transfers and notify end-user upon
     * completion. This function is called daily for incoming domains.
     */

    function dns_gateway_TransferSync($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);

            // Grab Domain
            $domain_list = $api->list_domains($domain);

            // Grab Domain Transfer In
            $domain_transfer_in = $api->list_domain_transfer_in($domain);

            // Grab Domain Transfer out
            $domain_transfer_out = $api->list_domain_transfer_out($domain);

            if (isset($domain_transfer_in[0]['wid']) or isset($domain_transfer_out[0]['wid'])) {
                // Still pending transfer
                return [];
            }

            if (isset($domain_list[0]['wid'])) {

                $domain_view = $api->view_domain($domain_list[0]['wid']);
                $result = [
                    'completed' => true,
                    'expirydate' => $domain_view['expiry']
                ];

                if ($tld != 'co.za') {
                    dns_gateway_Update_EPP_key($params);
                }
                update_autorenew($params);

                return $result;

            } else {
                return [
                    'failed' => true
                ];
            }

        } catch (\Exception $e) {
            return dns_gateway_TransferStatusUpdate($params['domainid'], $e->getMessage());
        }
    }

    # Function to cancel domain transfer
    /*
     *
     */

    function dns_gateway_Cancel_Transfer($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);


            // Build post data
            $domain_info = [
                'name' => $domain
            ];

            $api->cancel_domain_transfer($domain_info);

            return true;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to reject domain transfer
    /*
     *
     */

    function dns_gateway_Reject_Transfer($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);

            // Get WID
            $domain_search = $api->list_domains($domain);
            $domain_wid = $domain_search[0]['wid'];

            // Submit Rejection
            $domain_info = [
                'name' => $domain
            ];
            $domain_reject = $api->reject_transfer_domain($domain_info, $domain_wid);

            return true;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to Approve domain transfer
    /*
     *
     */

    function dns_gateway_Approve_Transfer($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Connect To API
            $api = new DNSAPI($params);


            // Get WID
            $domain_search = $api->list_domains($domain);
            $domain_wid = $domain_search[0]['wid'];

            // Submit Rejection
            $domain_info = [
                'name' => $domain
            ];

            $domain_approve = $api->approve_transfer_domain(
                $domain_info,
                $domain_wid
            );

            return $domain_approve;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to lock domain
    /*
     *
     */

    function dns_gateway_Lock_Domain($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Call API
            $api = new DNSAPI($params);

            $api->lock($domain);


        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    # Function to unlock domain
    /*
     *
     */

    function dns_gateway_Unlock_Domain($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            //  Call API
            $api = new DNSAPI($params);

            $api->unlock($domain);

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    function dns_gateway_Block_Domain($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Call API
            $api = new DNSAPI($params);

            $api->block($domain);


        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    function dns_gateway_Unblock_Domain($params)
    {
        try {
            // Domain parameters
            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            // Call API
            $api = new DNSAPI($params);

            $api->unblock($domain);


        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }
    # Function custom button in admin area
    /*
     *
     */

    function dns_gateway_AdminCustomButtonArray($params)
    {
        $buttonarray = [
            'Update EPP Key' => 'Update_EPP_key'
        ];

        //print_r($params);exit;
        // Domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;

        $pdo = WHMCS\Database\Capsule::connection()->getPdo();

        // Replace 'registrar_module_name' with the name of the registrar module
        $registrarModuleName = $params['registrar'];

        // Prepare and execute the query to fetch registrar configuration options
        $query = $pdo->prepare("
                SELECT *
                FROM tblregistrars r                
                WHERE registrar = :registrarModuleName
            ");
        $query->execute(['registrarModuleName' => $registrarModuleName]);
        $RegistrarConfigOptions = $query->fetchAll(PDO::FETCH_ASSOC);

        // Output or process the configuration options as needed
        foreach ($RegistrarConfigOptions as $RegistrarConfigOption) {

            $command = 'DecryptPassword';
            $postData = array(
                'password2' => $RegistrarConfigOption['value'],
            );

            $results = localAPI($command, $postData);
            $params[$RegistrarConfigOption['setting']] = $results['password'];
        }

        // Call API
        $api = new DNSAPI($params);

        $domain_sync = $api->domain_sync($domain);
        //print_r($domain_lock);
        if (isset($domain_sync['statuses'])) {
            foreach ($domain_sync['statuses'] as $val) {
                if ($val['code'] == 'clientTransferProhibited') {
                    $domain_lock = true;
                }
                if ($val['code'] == 'pendingTransfer') {
                    $pendingTransfer = true;
                }
                if ($val['code'] == 'clientUpdateProhibited') {
                    $domain_block = true;
                }
                if ($val['code'] == 'clientHold') {
                    $domain_suspend = true;
                }
            }
        }
        //clientTransferProhibited
        if ($pendingTransfer == true) {
            $buttonarray['Approve Transfer'] = 'Approve_Transfer';
            $buttonarray['Reject Transfer'] = 'Reject_Transfer';
            $buttonarray['Cancel Transfer'] = 'Cancel_Transfer';
        }

        if ($domain_lock == true) {
            $buttonarray['Unlock Domain'] = 'Unlock_Domain';
        } else {
            $buttonarray['Lock Domain'] = 'Lock_Domain';
        }

        if ($domain_suspend == true) {
            $buttonarray['Unsuspend'] = 'Unsuspend_Domain';
        } else {
            $buttonarray['Suspend'] = 'Suspend_Domain';
        }

        if ($domain_block == true) {
            $buttonarray['Unblock Domain'] = 'Unblock_Domain';
        } else {
            $buttonarray['Block Domain'] = 'Block_Domain';
        }


        return $buttonarray;
    }

    # Function update domain status for transfer
    /*
     * Called when API returns "transferPending".
     *
     * Function will update domain status in WHMCS database.
     */

    function dns_gateway_Suspend_Domain($params)
    {
        try {

            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            $api = new DNSAPI($params);
            $result = $api->suspend($domain);

            return $result;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    function dns_gateway_Unsuspend_Domain($params)
    {
        try {

            $sld = $params['sld'];
            $tld = $params['tld'];
            $domain = $sld . '.' . $tld;

            $api = new DNSAPI($params);
            $result = $api->unsuspend($domain);

            return $result;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    function dns_gateway_TransferStatusUpdate($domainID, $message)
    {
        if (strpos($message, 'pendingTransfer')) {
            updateDomainDatabaseStatus($domainID, 'Pending Transfer');
            return ['error' => 'Domain status "Pending Transfer"'];
        }
        return ['error' => $message];
    }

    function dns_gateway_GetTldPricing($params)
    {
        $results = new ResultsList();

        $api = new DNSAPI($params);
        $tld_pricing = $api->tld_pricing();

        foreach ($tld_pricing as $key => $value) {

            foreach ($value['prices'] as $k => $v) {

                if ($v['action'] == 'new' and $v['product_code'] == $v['tld']. '_' . $v['action'])
                    $registrationPrice = $v['price'];

                if ($v['action'] == 'renew')
                    $renewalPrice = $v['price'];

                if ($v['action'] == 'transfer')
                    $transferPrice = $v['price'];

                if ($v['action'] == 'redeem')
                    $redemptionFee = $v['price'];

                $currencyCode = $v['currency'];
            }

            //->setRedemptionFeeDays()

            $item = (new ImportItem)
                ->setExtension($value['tld'])
                ->setMinYears(1)
                ->setMaxYears(1)
                ->setRegisterPrice($registrationPrice)
                ->setRenewPrice($renewalPrice)
                ->setTransferPrice($transferPrice)
                ->setRedemptionFeePrice($redemptionFee)
                ->setCurrency($currencyCode)
                ->setEppRequired(true);

            $results[] = $item;
        }
        return $results;
    }

    # Function update domain status in WHMCS database
    /*
     *
     */

    function updateDomainDatabaseStatus($domainId, $status)
    {
        return Capsule::table('tbldomains')
            ->where('id', $domainId)
            ->update(array(
                    'status' => $status
                )
            );
    }
