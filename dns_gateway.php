<?php
/*
 * This modules allows you to transfer, register, check availability and 
 * use other fucntions for domains within whmcs.
 * 
 * The modules files must be stored in: modules/registrars/
 * The module name must always be lowercase and start with a letter
 * and only use letter and numbers for the module name.
 * 
 * The modules functions all must start with the module name followed by an understcore
 * for example ModuleName_FunctionName(){
 * 
 * }
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Module\Registrar\dns_gateway\DNSAPI;

// Require any libraries needed for the module to function.

/**
 * Define module related metadata
 *
 * Provide some module information including the display name and API Version to
 * determine the method of decoding the input values.
 *
 * @return array
 */
function dns_gateway_MetaData()
{
    return array(
        'DisplayName' => 'DNS Gateway: Start provisioning domains with ease!',
        'APIVersion' => '0.1',
    );
}

/**
 * Define registrar configuration options.
 *
 * configuration file can be found in whmcs->Setup->Products/Services->Domain Registrars->Your ModuleName -> Configure
 * 
 * The following field types are supported:
 *  * Text
 *  * Password
 *  * Yes/No Checkboxes
 *  * Dropdown Menus
 *  * Radio Buttons
 *  * Text Areas
 *
 * The following configuration used here are Text, Password, yes/no.
 */
function dns_gateway_getConfigArray()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'DNS Gateway: Start provisioning domains with ease!',
        ),
        'API_Username' => array(
            "FriendlyName" => "API Username",
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'DNS Gateway API Username',
        ),
        'API_Password' => array(
            "FriendlyName" => "API Password",
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'DNS Gateway API Password',
        ),
        'OTE_API_Username' => array(
            "FriendlyName" => "OTE API Username",
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'DNS Gateway OTE API Username',
        ),
        'OTE_API_Password' => array(
            "FriendlyName" => "OTE API Password",
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'DNS Gateway OTE API Password',
        ),
        'Dev_Mode' => array(
            "FriendlyName" => "API OTE Mode",
            'Type' => 'yesno',
            'Description' => 'Enable OTE Testing Mode',
        )
    );
}

# Function to register domain
/*
 * Called when the registration of a new domain is initiated within WHMCS.
 * 
 * Function will triger a payment is made or a new domain is registered
 */
function dns_gateway_RegisterDomain($params)
{
    try {
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // registration parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld.'.'.$tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);

        //Nameservers
        $nameservers = [];
        for ($n = 1; $n <= 5; $n++) {
            if(isset($params['ns'.$n]) && $params['ns'.$n]!=''){
                $nameservers[] = [
                    'hostname'  =>  $params['ns'.$n],
                    'glue'      =>  []
                ];
            }
        }
        
        // Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

        // registrant information
        $registrant_info    =   [
            "id"        =>  $api->generate_id($domain),
            "phone"     =>  $params["phonenumberformatted"],
            "fax"       =>  null,
            "email"     =>  $params["email"],
            "contact_address"   =>  [
                [
                    "real_name" =>  $params["fullname"],
                    "org" =>  $params["companyname"],
                    "street" =>  $params["address1"],
                    "street2" =>  $params["address2"],
                    "street3" =>  null,
                    "city" =>  $params["city"],
                    "province" =>  $params["state"],
                    "code" =>  $params["postcode"],
                    "country" =>  $params["countrycode"],
                    "type"  =>  "loc"
                ]
            ]

        ];

        //  Admin contact information.
        $admin_info     =   [
            "id"        =>  $api->generate_id($domain),
            "phone"     =>  $params["adminfullphonenumber"],
            "fax"       =>  null,
            "email"     =>  $params["adminemail"],
            "contact_address"   =>  [
                [
                    "real_name" =>  $params["adminfirstname"].' '.$params["adminlastname"],
                    "org" =>  $params["admincompanyname"],
                    "street" =>  $params["adminaddress1"],
                    "street2" =>  $params["adminaddress2"],
                    "street3" =>  null,
                    "city" =>  $params["admincity"],
                    "province" =>  $params["adminstate"],
                    "code" =>  $params["adminpostcode"],
                    "country" =>  $params["admincountry"],
                    "type"  =>  "loc"
                ]
            ]

        ];
        
        //  Billing contact information.
        $billing_info     =   [
            "id"        =>  $api->generate_id($domain),
            "phone"     =>  $params["adminfullphonenumber"],
            "fax"       =>  null,
            "email"     =>  $params["adminemail"],
            "contact_address"   =>  [
                [
                    "real_name" =>  $params["adminfirstname"].' '.$params["adminlastname"],
                    "org" =>  $params["admincompanyname"],
                    "street" =>  $params["adminaddress1"],
                    "street2" =>  $params["adminaddress2"],
                    "street3" =>  null,
                    "city" =>  $params["admincity"],
                    "province" =>  $params["adminstate"],
                    "code" =>  $params["adminpostcode"],
                    "country" =>  $params["admincountry"],
                    "type"  =>  "loc"
                ]
            ]

        ];
        
        //  tech contact information.
        $tech_info     =   [
            "id"        =>  $api->generate_id($domain),
            "phone"     =>  $params["adminfullphonenumber"],
            "fax"       =>  null,
            "email"     =>  $params["adminemail"],
            "contact_address"   =>  [
                [
                    "real_name" =>  $params["adminfirstname"].' '.$params["adminlastname"],
                    "org" =>  $params["admincompanyname"],
                    "street" =>  $params["adminaddress1"],
                    "street2" =>  $params["adminaddress2"],
                    "street3" =>  null,
                    "city" =>  $params["admincity"],
                    "province" =>  $params["adminstate"],
                    "code" =>  $params["adminpostcode"],
                    "country" =>  $params["admincountry"],
                    "type"  =>  "loc"
                ]
            ]

        ];

        /**
         * Premium domain parameters.
         *
         * Premium domains enabled informs you if the admin user has enabled
         * the selling of premium domain names. If this domain is a premium name,
         * `premiumCost` will contain the cost price retrieved at the time of
         * the order being placed. The premium order should only be processed
         * if the cost price now matches the previously fetched amount.
         */
        $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
        $premiumDomainsCost = $params['premiumCost'];


        // Create Contacts    
        $registrant_contact = $api->setcontact($registrant_info);
        $admin_contact = $api->setcontact($admin_info);
        $billing_contact = $api->setcontact($billing_info);
        $tech_contact = $api->setcontact($tech_info);

        // Register Domain
        $domain = [
            "name" => $domain,
            "period" => $registrationPeriod,
            "period_unit" => "y",
            "autorenew" => false,
            "authinfo" => $api->generate_password($domain),
            "hosts" => $nameservers,
            "contacts" => [
                [
                    "type" => "registrant",
                    "contact" => [
                        "id" => $registrant_contact
                    ]
                ], 
                [
                    "type" => "admin",
                    "contact" => [
                        "id" => $admin_contact
                    ]
                ], 
                [
                    "type" => "billing",
                    "contact" => [
                        "id" => $billing_contact
                    ]
                ], 
                [
                    "type" => "tech",
                    "contact" => [
                        "id" => $tech_contact
                    ]
                ]
            ]
        ];

        //  Add Premium Charge If Applicable
        if ($premiumDomainsEnabled && $premiumDomainsCost) {
            $domain['charge']['price'] = $premiumDomainsCost;
        }

        //  Call The Register Domain Function
        $api->register_domain($domain);
    
        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array("error" => $e->getMessage());
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
    try {
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // registration parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld.'.'.$tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);
        $eppCode = $params['eppcode'];
        
        // Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

        /**
         * Premium domain parameters.
         *
         * Premium domains enabled informs you if the admin user has enabled
         * the selling of premium domain names. If this domain is a premium name,
         * `premiumCost` will contain the cost price retrieved at the time of
         * the order being placed. The premium order should only be processed
         * if the cost price now matches the previously fetched amount.
         */
        $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
        $premiumDomainsCost = $params['premiumCost'];

        // Transfer Domain
        $domain = [
            "name" => $domain,
            "authinfo" => ($eppCode=="" && $tld=="co.za" ? "coza" : $eppCode)
        ];

        //  Add Premium Charge If Applicable
        if ($premiumDomainsEnabled && $premiumDomainsCost) {
            $domain['charge']['price'] = $premiumDomainsCost;
        }

        //  Call The Register Domain Function
        $api->transfer_domain($domain);
    
        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array("error" => $e->getMessage());
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
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // registration parameters
        $sld = $params['sld']; 
        $tld = $params['tld'];
        $domain = $sld.'.'.$tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);

        
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
            
        //  Get Domain info
            $domain_list = $api->list_domains($domain);
            
        //  Renew Domain
            $renew_array=[
                "name"  => $domain,
                "period" => $registrationPeriod,
                "period_unit" => 'y',
                "curExpDate" => date('Y-m-d', strtotime($domain_list[0]['expiry']))
            ];
            
            $api->renew_domain($renew_array, $domain_list[0]['wid']);
    

        return [
            'success' => true,
        ];

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Fetch current nameservers.
 *
 * This function should return an array of nameservers for a given domain.
 * 
 * Called when a domain is viewed within WHMCS. It can return up to 5 nameservers that are set for the domain.
 * 
 * $sld = eg. yourdomain
 * $tld = eg. .com
 */
function dns_gateway_GetNameservers($params)
{
    try {
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld.'.'.$tld;       
    
    //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        
    //  Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
        $nameservers = [
            "success"   =>   true
        ];
        $count = 0;
        foreach($domain_view['hosts'] as $namserver){
            $count ++;
            $nameservers["ns$count"] = $namserver['hostname'];
        }
        
        return $nameservers;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
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
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
    
    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);
    
    //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
    
    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
    //  Handle Nameservers
        $hosts  =   $domain_view['hosts'];
        $new_hosts = [];
        
        for ($n = 1; $n <= 5; $n++) {
            if(isset($params['ns'.$n]) && $params['ns'.$n]!=''){
                //  Compare Nameservers
                    $host_record = [
                        'hostname'  =>  $params['ns'.$n]
                    ];
                    foreach($hosts as $host_key =>   $host){
                        if($host['hostname']==$params['ns'.$n]){
                            $host_record['glue'] = $host['glue'];
                        }
                    }
                
                //  Add Nameserver
                $new_hosts[] = $host_record;
            }
        } 
        
    //  Update Domain
        $update_array   =   [
            "name"  =>  $domain,
            "period"    =>  $registrationPeriod,
            "period_unit"   =>  "y",
            "authinfo"  =>  $api->generate_password($domain),
            "hosts" =>  $new_hosts,
            "contacts"  =>  $domain_view["contacts"],
            "autorenew" => false
        ];  
        
        $api->update_domain($update_array, $domain_list[0]['wid']);
        
        return true;
    
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
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
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];    
        $domain = $sld . '.' . $tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);

    try {
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        
        // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
        // Contacts Section
        $contacts = [];
        foreach($domain_view['contacts'] as $contact){
            // Grab Contact Info 
            $contact_info = $api->contact_info($contact['contact']['id']);
            // Grab Contact Name And Divide It
            $name_parts = explode(' ', $contact_info['contact_address'][0]['real_name']);
                $last_name = end($name_parts);
                array_pop($name_parts);
                $first_name = implode(' ', $name_parts);
            // Build Contact ID
            $contacts[ucfirst($contact["type"])]   =   [
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
        return array(
            'error' => $e->getMessage(),
        );
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
    
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];    
        $domain = $sld . '.' . $tld;

        
    // Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        
    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
    // Loop Contacts
        foreach($domain_view['contacts'] as $contact){
            // Updated Contact information
                $new_details = $params['contactdetails'][ucfirst($contact["type"])];
            //  Contact information.
                $contact_info     =   [
                    "phone"     =>  $new_details["Phone Number"],
                    "fax"       =>  null,
                    "email"     =>  $new_details["Email Address"],
                    "contact_address"   =>  [
                        [
                            "real_name" =>  $new_details["First Name"].' '.$new_details["Last Name"],
                            "org" =>  $new_details["Company Name"],
                            "street" =>  $new_details["Address 1"],
                            "street2" =>  $new_details["Address 2"],
                            "street3" =>  null,
                            "city" =>  $new_details["City"],
                            "province" =>  $new_details["State"],
                            "code" =>  $new_details["Postcode"],
                            "country" =>  $new_details["Country"],
                            "type"  =>  "loc"
                        ]
                    ]

                ];
                
            //  Grab API Side Contact Info
                $api_contact = $api->contact_info($contact['contact']['id']);
                
            //  Update Contact
                $api->update_contact($contact_info, $api_contact['wid']);
        } 

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

#Function to check a domains availability and premium status
/*
 * Check Domain Availability.
 * Determine if a domain is availability for registration or transfer.
 * 
 * Check is the domain name is premium
 */
function dns_gateway_CheckAvailability($params)
{    
    try {
    
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

    // availability check parameters
        $searchTerm = $params['searchTerm'];
        $punyCodeSearchTerm = $params['punyCodeSearchTerm'];
        $tldsToInclude = $params['tldsToInclude'];
        $isIdnDomain = (bool) $params['isIdnDomain'];
        $premiumEnabled = (bool) $params['premiumEnabled'];
        

    // Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

    //  Initiate Results List
        $results = new ResultsList();
        
    //  loop Through Domains To Check Availability
        
        foreach($params['tlds'] as $tld){
            $searchResult = new SearchResult($domain['sld'], $tld);
            
            $domain_info = [
                "name"  =>   $params['sld'] . $tld
            ];
            
            $domain_check = $api->domain_check($domain_info);
            
            // Determine the appropriate status to return
                if ($domain_check['results']['avail'] == '1') {
                    $status = SearchResult::STATUS_NOT_REGISTERED;
                } elseif ($domain_check['results']['avail'] == '0' && $domain_check['results']['reason'] == "In Use") {
                    $status = SearchResult::STATUS_REGISTERED;
                } elseif ($domain_check['results']['avail'] == '0' && $domain_check['results']['reason'] != "In Use") {
                    $status = SearchResult::STATUS_RESERVED;
                }  else {
                    $status = SearchResult::STATUS_TLD_NOT_SUPPORTED;
                }
                $searchResult->setStatus($status);

            // Return premium information if applicable
                if ($domain_check['charge']['category'] != "standard") {
                    $searchResult->setPremiumDomain(true);
                    $searchResult->setPremiumCostPricing(
                        array(
                            'register' => $domain_check['charge']['action']['create'],
                            'renew' => $domain_check['charge']['action']['renew'],
                            'transfer' => $domain_check['charge']['action']['transfer'],
                            'CurrencyCode' => 'USD',
                        )
                    );
                }

            // Append to the search results list
                $results->append($searchResult);
                    
        }
        
        return $results;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
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
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
        
    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;
        
    //Call API
        $api = new DNSAPI();  
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        $domain_lock = $api->check_domain_lock($domain);
    
        if ($domain_lock == true) {
            return 'locked';
        } else {
            return 'unlocked';
        }
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

# Function to save Registrar Lock
/*
 * Called when the lock status setting is toggled within WHMCS.  
 */
function dns_gateway_SaveRegistrarLock($params)
{
    try {
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;    

    // Build post data
        $postfields = [
            'domain' => $domain
        ];
    
        $api = new DNSAPI();        
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        if($params['lockenabled'] == "locked"){
            $api->domain_lock($domain);
        }else{
            $api->domain_unlock($domain);
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

#Get epp code
/*
 * Called when the EPP Code is requested for a transfer out.
 * 
 * Can display EPP code directly to the user or
 * indicate that the code will be emailed to the registrant.
 */
function dns_gateway_GetEPPCode($params)
{
    try{
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;      
        
        if($tld!='co.za' && $tld!='net.za' && $tld!='org.za' && $tld!='web.za'){
            //Call api
                $api = new DNSAPI();
                $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);        
                $values["eppcode"] = $api->generate_password($domain);

                return $values;
        }else{
            return [
                'error' => 'This TLD Does Not Support EPP Codes',
            ];
        }
        
    } catch (\Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }
}

#request deletion of the domain name 
/*
 * Trigers when a domain deletion request comes from WHMCS or
 * manual deletion from admin. *  
 */
function dns_gateway_RequestDelete($params)
{
    try{
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;      
        
    
    //Call api
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password); 
        $domain_info = $api->list_domains($domain);
        
        $api->delete_domain($domain_info[0]['wid']);

        return true;
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

# Function to register nameserver
/*
 * Called when a child nameserver registration request comes from WHMCS
 */
function dns_gateway_RegisterNameserver($params)
{
    try {
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
        
    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];    
        $domain = $sld . '.' . $tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);
        
    // nameserver parameters
        $nameserver = $params['nameserver'];
        $ipAddress = $params['ipaddress'];

    // Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
    //  Prepare Hosts
        $hosts  =   $domain_view['hosts'];
        $hosts[] = [
            "hostname" => $params['nameserver'],
            "glue" => [
                [
                    "ip" => $params['ipaddress'],
                    "class_field" => "v4"
                ]
            ]
        ];

        //  Update Domain & Hosts
        $update_array   =   [
            "name"  =>  $domain,
            "period"    =>  $registrationPeriod,
            "period_unit"   =>  "y",
            "authinfo"  =>  $api->generate_password($domain),
            "hosts" =>  $hosts, 
            "contacts"  =>  $domain_view["contacts"],
            "autorenew" => false
        ];  
        
        $api->update_domain($update_array, $domain_list[0]['wid']);
        
        return true;
            
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
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
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
        
    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];    
        $domain = $sld . '.' . $tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);
        
    // nameserver parameters
        $nameserver = $params['nameserver'];
        $CurrentIpAddress = $params['currentipaddress'];
        $NewIpAddress = $params['newipaddress'];

    // Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
    //  Prepare Hosts
        $hosts  =   $domain_view['hosts'];
        foreach($hosts as $host_key => $host){
            if($host['hostname']==$nameserver){
                //  Go Through Glue Records
                    foreach($host['glue'] as $glue_key  =>  $glue){
                        if($glue['ip'] == $CurrentIpAddress){
                            //  Update Domain & Hosts
                                $hosts[$host_key]["glue"][$glue_key]["ip"] = $NewIpAddress;
                                
                                $update_array   =   [
                                    "name"  =>  $domain,
                                    "period"    =>  $registrationPeriod,
                                    "period_unit"   =>  "y",
                                    "authinfo"  =>  $api->generate_password($domain),
                                    "hosts" =>  $hosts,
                                    "contacts"  =>  $domain_view["contacts"],
                                    "autorenew" => false
                                ];

                                $api->update_domain($update_array, $domain_list[0]['wid']);

                                return true;
                            
                        }
                    }
            }
        }
        
        //  Return Error If No Matching Hostname Was Found
        return  array(
            'error' => 'No Matching Hostname Was Found',
        );
        
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

# Delete nameserver
/*
 * Called when a child nameserver deletion request comes from WHMCS.
 */
function dns_gateway_DeleteNameserver($params)
{
    try {
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
        
    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];    
        $domain = $sld . '.' . $tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);
        
    // nameserver parameters
        $nameserver = $params['nameserver'];

    // Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
    //  Prepare Hosts
        $hosts  =   $domain_view['hosts'];
        $new_hosts  =   [];
        foreach($hosts as $host_key => $host){
            if($host['hostname']!=$nameserver){
                $new_hosts[] = $host;
            }
        }
        //  Update Domain & Hosts
        $update_array = [
            "name" => $domain,
            "period" => $registrationPeriod,
            "period_unit" => "y",
            "authinfo" => $api->generate_password($domain),
            "hosts" => $new_hosts,
            "contacts" => $domain_view["contacts"],
            "autorenew" => false
        ];
        $api->update_domain($update_array, $domain_list[0]['wid']);

        return true;    
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Sync Domain Status & Expiration Date.
 * 
 * To ensure expiry date and status changes made directly at the domain register are in sync with WHMCS.
 */
function dns_gateway_Sync($params)
{
    try {
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
        
    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];        
        $domain = $sld.'.'.$tld;        
    
    //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
    
    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
    
    //  Build Return Result
        $result =   [
            'expirydate'    =>  $domain_view['expiry'],
            'active'    =>  false,
            'expired'   =>  false,
            'transferredAway'   =>  false,
        ];
        
        
        
        if($api_username != $domain_view['rar']){
            $result['transferredAway'] = true;
        }else if($domain_view['expiry']>date()){
            $result['active'] = true;
            
            //  Update auth key just to be sure that it's always correct (should standards change)
                dns_gateway_Update_EPP_key($params);
        }else{
            $result['expired'] = true;
        } 
        
        return $result;
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}
/*
 * Update EPP Key
 */

function dns_gateway_Update_EPP_key($params){
    try {
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
    
    // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;
        $registrationPeriod = ($params['regperiod'] > 0 ? $params['regperiod'] : 1);

    //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
    
    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
        
    //  Update Domain
        $update_array   =   [
            "name"  =>  $domain,
            "period"    =>  $registrationPeriod,
            "period_unit"   =>  "y",
            "authinfo"  =>  $api->generate_password($domain),
            "hosts" =>  $domain_view["hosts"],
            "contacts"  =>  $domain_view["contacts"],
            "autorenew" => false
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
    // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];
        
    // domain parameters       
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;
        
    //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        
    // Grab Domain Info
        $domain_list = $api->list_domains($domain);
        $domain_view = $api->view_domain($domain_list[0]['wid']);
               
    //  Return Results
        
        if($api_username == $domain_view['rar']){
            $result = [
                "completed" =>   true,
                "expirydate"    =>  $domain_view['expiry']
            ];
            
            if($tld!='co.za' && $tld!='net.za' && $tld!='org.za' && $tld!='web.za'){
                dns_gateway_Update_EPP_key($params);
            }
            
            return $result;
        }
    
        // No status change, return empty array
            return array();
    

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function dns_gateway_Cancel_Transfer($params) {
    try {
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;

        //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        
        // Build post data
        $domain_info = [
            'name' => $domain
        ];
        
        $api->cancel_domain_transfer($domain_info);

        return true;
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}
#Function to reject domain transfer
/*
 * 
 */
function dns_gateway_Reject_Transfer($params) {
    try {
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;

        //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

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
        return array(
            'error' => $e->getMessage(),
        );
    }
}
#Function to Approve domain transfer
/*
 * 
 */
function dns_gateway_Approve_Transfer($params) {
    try {
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;

        //  Connect To API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);

        // Get WID
        $domain_search = $api->list_domains($domain);
        $domain_wid = $domain_search[0]['wid'];
        
        // Submit Rejection 
        $domain_info = [
            'name' => $domain
        ];
        
        $domain_approve = $api->approve_transfer_domain($domain_info, $domain_wid);

        return $domain_approve;
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function dns_gateway_Lock_Domain($params) {
    try {
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;
        
        // Call API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        $api->domain_lock($domain);
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function dns_gateway_Unlock_Domain($params) {
    try {
        // user defined configuration values
        $api_username = $params['API_Username'];
        $api_password = $params['API_Password'];
        $ote_api_username = $params['OTE_API_Username'];
        $ote_api_password = $params['OTE_API_Password'];
        $api_dev_mode = $params['Dev_Mode'];

        // domain parameters
        $sld = $params['sld'];
        $tld = $params['tld'];
        $domain = $sld . '.' . $tld;
        
        //  Call API
        $api = new DNSAPI();
        $api->setcreds($api_username, $api_password, $api_dev_mode, $ote_api_username, $ote_api_password);
        $api->domain_unlock($domain);
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function dns_gateway_AdminCustomButtonArray() {
    $buttonarray = array(
        "Approve Transfer" => "Approve_Transfer",
        "Reject Transfer" => "Reject_Transfer",
        "Cancel Transfer" => "Cancel_Transfer",
        "Lock Domain" => "Lock_Domain",
        "Unlock Domain" => "Unlock_Domain",
        "Update EPP Key" => "Update_EPP_key"
    );
    return $buttonarray;
}

