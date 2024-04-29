<?php

    if (!defined('WHMCS'))
        die('You cannot access this file directly.');

    use WHMCS\View\Menu\Item as MenuItem;
    use WHMCS\Database\Capsule;
    use \WHMCS\Module\Registrar\dns_gateway\DNSAPI;

    add_hook('ClientAreaPrimarySidebar', 1, function(MenuItem $primarySidebar) {

        $domain = Menu::context('domain');
        $blockedtlds = array('eu','nl','co.za');
        if (in_array($domain->tld, $blockedtlds) && !is_null($primarySidebar->getChild('Domain Details Management'))) {
            $primarySidebar->getChild('Domain Details Management')->removeChild('Registrar Lock Status');
            function domain_registrar_lock_link_removal_hook($vars) {
                $managementoptions = $vars['managementoptions'];
                $managementoptions['locking'] = false;
                return array ("managementoptions" => $managementoptions, "lockstatus" => "locked");
            }
            add_hook("ClientAreaPageDomainDetails",1,"domain_registrar_lock_link_removal_hook");
        }
    });

    function domain_validation($vars) {
        syslog(LOG_INFO, "HOOKS: DomainValidation \$vars: " . json_encode($vars));
        syslog(LOG_INFO, "HOOKS: DomainValidation \$_POST: " . json_encode($_POST));
        if ($_POST["a"] == "addDomainTransfer")
            $module = new WHMCS\Module\Registrar();
            if($module)
            {
                if (!$module->load('dns_gateway'))
                {
                    throw new Exception("Could not load dns_gateway registrar.");
                }
                $settings = $module->getSettings();
                syslog(LOG_INFO, "HOOKS: DomainValidation \$_POST: " . json_encode($settings));
            }
            
    };

    add_hook('DomainValidation', 1, 'domain_validation');


        add_hook('ShoppingCartValidateDomainsConfig', 1, function($vars) {
        ########### Example $vars ###########
        /*
        {
          "a": "confdomains",
          "token": "3aecae443dd0a4dd71b6c9443178abcf4671b7fa",
          "update": "true",
          "epp": [
            "af1231245df"
          ],
          "domainns1": "ns1.spindoctor.mobi",
          "domainns2": "ns2.spindoctor.mobi",
          "domainns3": "",
          "domainns4": "",
          "domainns5": "",
          "domainfield": [
            []
          ]
        }
         */

        ########### Exapmle $_SESSION ###########
        /*
         {
          ...
          "cart": {
            "domains": [
              {
                "type": "transfer",
                "domain": "testing123.africa",
                "regperiod": 1,
                "eppcode": "af1231245df",
                "idnLanguage": "",
                "isPremium": false,
                "dnsmanagement": null,
                "emailforwarding": null,
                "idprotection": null,
                "fields": null
              }
            ],
            "ns1": "ns1.spindoctor.mobi",
            "ns2": "ns2.spindoctor.mobi",
            "user": {
              "country": "ZA"
            },
            "products": [],
            "locations": {
              "viewcart": false,
              "checkout": false
            }
          },
          "cartdomain": [],
        ...
        }
         */


        syslog(LOG_INFO, "RHOOKS: ShoppingCartValidateDomainsConfig " . json_encode($vars));
    });

    add_hook('DomainTransferCompleted', 1, function($vars) {
        
        $command = 'GetClientsDomains';
        $postData = array(
            'domainid' => $vars['domainId'],
        );

        $results = localAPI($command, $postData);
        $userid=$results['domains']['domain']['0']['userid'];

        $command = 'GetClientsDetails';
        $postData = array(
            'clientid' => $userid,
        );
        $result = localAPI($command, $postData);

        $registrarModuleName = 'dns_gateway';

        foreach (Capsule::table('tblregistrars')
            ->where('registrar', $registrarModuleName)
            ->get() as $option) {

            $command = 'DecryptPassword';
            $postData = array(
                'password2' => $option->value,
            );
            $res = localAPI($command, $postData);
            $data[$option->setting]=$res['password'];
            
        }
        $api = new DNSAPI($data);
        $registrant_info=array(
            'id' =>  $api->generate_id($vars['domain']),
            'phone'=>$result['phonenumberformatted'],
            'fax'=> NULL,
            'email'=>$result['email'],
            'contact_address'=>[
                [
                        'real_name' => $result['fullname'],
                        'org' => $result['companyname'],
                        'street' => $result['address1'],
                        'street2' => $result['address2'],
                        'street3' => NULL,
                        'city' => $result['city'],
                        'province' => $result['state'],
                        'code' => $result['postcode'],
                        'country' => $result['countrycode'],
                        'type' => 'int'
                    ]
                ]
            );

        $registrant_contact = $api->setcontact($registrant_info);
        logModuleCall('dns_gateway','hook','contact set - '.$vars['domain'],$registrant_contact);
    });


