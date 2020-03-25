<?php

namespace WHMCS\Module\Registrar\dns_gateway;

/*
 * Approved by ModulesGarden
 *
 * DNS API
 *
 */

class DNSAPI
{
    var $username;
    var $password;
    var $api_url;
    var $auth;

    protected $results = [];
    protected $lock_statusses = [
        'clientTransferProhibited',
        'clientUpdateProhibited',
        'clientDeleteProhibited'
    ];

    /*
     * Uses Setup->
     * Product/Services->
     * DomainRegistrars->
     * ModulesName->
     * Configure configuration.
     *
     * Function is used to connect to the api.
     */

    public function setcreds(
            $username,
            $password,
            $dev_mode,
            $ote_username,
            $ote_password
    ) {
        // API URLs
        $API_URL         = 'https://gateway-epp.dns.net.za/api';
        $DEV_API_URL     = 'https://gateway-otande.dns.net.za:8443/api';

        // API credentials
        $this->username  = ($dev_mode == 'on' ? $ote_username : $username);
        $this->password  = ($dev_mode == 'on' ? $ote_password : $password);
        $this->api_url   = ($dev_mode == 'on' ? $DEV_API_URL : $API_URL);
        $this->auth      = base64_encode(
            ($dev_mode == 'on' ? $ote_password : $password)
        );
    }

    /*
     * Function generates a random password using the domain,
     * username, and md5 encryption.
     * We're also adding a # at the end,
     * to ensure that all domain standards are met.
     */

    public function generate_password($domain)
    {
        // Generate
        $epp_key = substr(md5($domain . $this->username), 0, 6).'#A4';
        return $epp_key;
    }

    /*
     * Function generates a random ID using
     * the domain, username, and md5 encryption.
     */

    public function RandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randstring = '';
        while (strlen($randstring)<$length) {
            $randstring .= $characters[rand(0, strlen($characters))];
        }
        return $randstring;
    }

    public function generate_id($domain)
    {
        return substr($domain, 0, 3).
                substr($this->username, 0,3).
                $this->RandomString(7);
    }

    #Contact Operation

    /*
     * Create Contact
     */

    public function setcontact($contact_info)
    {
        $url = $this->api_url . '/registry/contacts/';
        $result = $this->call($url, $contact_info, 'POST');
        return $result['id'];
    }

    /*
     * Update Existing Contact
     */

    public function update_contact($contact_info, $existing_id)
    {
        // Check For Existing Contact
        $url = $this->api_url . '/registry/contacts/' . $existing_id . '/';
        $result = $this->call($url, $contact_info, 'PUT');
        return true;
    }

    /*
     * Pulls all Existing Contacts
     */

    public function contact_listing()
    {
        $url = $this->api_url . '/registry/contacts/';
        $result = $this->call($url, [], 'GET');
        return $result['wid'];
    }

    /*
     * Get the conatcs wid id
     */

    public function get_contact_wid($contact_id)
    {
        $url = $this->api_url . '/registry/contacts/?id=' . $contact_id;
        $result = $this->call($url, [], 'GET');
        return $result['results'][0]['wid'];
    }

    /*
     * Pulls a called contacts info
     */

    public function contact_info($contact_id)
    {
        $wid = $this->get_contact_wid($contact_id);
        $url = $this->api_url . '/registry/contacts/' . $wid;
        $result = $this->call($url, [], 'GET');
        return $result;
    }

    /*
     * Checks if a contact exists
     */

    public function contact_check($check, $contact_name = '')
    {
        $url = $this->api_url . '/registry/contacts/check/' . $contact_name;
        $result = $this->call($url, $check, 'POST');
        return $result['results'];
    }

    #Domain Operations

    /*
     * Registers a new domain
     */

    public function register_domain($domain_info, $domain_id = '')
    {
        $url = $this->api_url . '/registry/domains/' . $domain_id;
        $result = $this->call($url, $domain_info, 'POST');
        return $result['wid'];
    }

    /*
     * Pulls all Existing Domains
     *  - Can Be Filtered By Domain Name
     */

    public function list_domains($domain_name = '') 
    {
        $url = $this->api_url.
                '/registry/domains/'.
                ($domain_name!='' ? '?name=' . $domain_name : '');

        $result = $this->call($url, [], 'GET');
        return $result['results'];
    }

    /*
     * Get's Domain's info
     */

    public function view_domain($domain_wid)
    {
        $url = $this->api_url . '/registry/domains/' . $domain_wid;
        $result = $this->call($url, [], 'GET');
        return $result;
    }

    /*
     * Updates Existing Domain
     */

    public function update_domain($domain_info, $domain_id = '')
    {
        $url = $this->api_url . '/registry/domains/' . $domain_id;
        $result = $this->call($url, $domain_info, 'PUT');
        return $result['wid'];
    }

    /*
     * Delete Existing domain
     */

    public function delete_domain($domain_id)
    {
        $url = $this->api_url . '/registry/domains/' . $domain_id;
        $result = $this->call($url, [], 'DELETE');
        return true;
    }

    /*
     * Renews Existing Domain
     */

    public function renew_domain($domain, $domain_id)
    {
        $url = $this->api_url . '/registry/domains/' . $domain_id . '/renew/';
        $result = $this->call($url, $domain, 'POST');
        return $result['wid'];
    }

    /*
     * Transfers Existing domain
     */

    public function transfer_domain($domain)
    {
        $url = $this->api_url . '/registry/domains/transfer-request/';
        $result = $this->call($url, $domain, 'POST');
        return $result['name'];
    }

    /*
     * Cancels Domain Transfer Request
     */

    public function cancel_domain_transfer($domain)
    {
        $url = $this->api_url . '/registry/domains/transfer-cancel/';
        $result = $this->call($url, $domain, 'POST');
        return true;
    }

    /*
     * Reject Domain Transfer
     */

    public function reject_transfer_domain($domain)
    {
        $url = $this->api_url.
                '/registry/domains/'.
                $domain.
                '/transfer-reject/';

        $result = $this->call($url, $domain, 'POST');
        return true;
    }

    /*
     * Approve Domain Transfer
     */

    public function approve_transfer_domain($domain, $domain_id)
    {
        $url = $this->api_url.
                '/registry/domains/'.
                $domain_id .
                '/transfer-approve/';

        $result = $this->call($url, $domain, 'POST');
        return true;
    }

    /*
     * Pulls Information about Current Domain
     */

    public function domain_info($domain)
    {
        $url = $this->api_url . '/registry/domains/info/';
        $result = $this->call($url, $domain, 'POST');

        if ($this->username != $result['results']['rar']) {
            throw new \Exception('Domain owned by: ' . $result['results']['rar']);
        }

        return $result['results'];
    }

    /*
     * Locks selected domain
     *
     * will be unable to -
     * Update
     * Delete
     * or Transfer
     */

    public function domain_Lock($domain_name)
    {
        $domain_info = $this->list_domains($domain_name);
        $domain = [
            'name' => $domain_name
        ];

        $url = $this->api_url.
                '/registry/domains/'.
                $domain_info[0]['wid'].
                '/lock/';

        $result = $this->call($url, $domain, 'POST');
        return true;
    }

    /*
     * Unlocks selected domain
     *
     * will be able to -
     * Update
     * Delete
     * or Transfer
     */

    public function domain_unlock($domain_name)
    {
        $domain_info = $this->list_domains($domain_name);
        $domain = [
            'name' => $domain_name
        ];

        $url = $this->api_url.
                '/registry/domains/'.
                $domain_info[0]['wid'].
                '/unlock/';

        $result = $this->call($url, $domain, 'POST');
        return true;
    }

    /*
     * Check if the Domain exists
     */

    public function domain_check($domain)
    {
        $url = $this->api_url . '/registry/domains/check/';
        $result = $this->call($url, $domain, 'POST');
        return $result;
    }

    /*
     * Function checks if a Domain is locked or unlocked
     */

    public function check_domain_lock($domain)
    {
        // Grab Domain Info
        $domain_arr = [
            'name'       => $domain,
            'authinfo'   => $this->generate_password($domain)
        ];

        $domain_info = $this->domain_info($domain_arr);
        $locked = false;
        foreach ($domain_info['statuses'] as $status) {
            if (in_array($status['code'], $this->lock_statusses)) {
                $locked = true;
            }
        }

        return $locked;
    }


    /*
     * Manage Hosts
     *
     * Create, Update & Delete hosts
     *
     */


    /*
     * List Hosts
     */

    public function list_hosts($hostname = '')
    {
        $url = $this->api_url.
                '/registry/hosts/'.
                ($hostname!='' ? '?hostname=' . $hostname : '');

        $result = $this->call($url, $host_record, 'GET');
        return $result['results'];
    }

    /*
     * Create Host
     */

    public function create_host($hostname, $ip, $ip_class = 'v4')
    {
        $url = $this->api_url . '/registry/hosts/';

        $host_record = [
            'hostname'  => $hostname,
            'glue'      => [
                [
                    'ip'            => $ip,
                    'class_field'   => $ip_class
                ]
            ]
        ];

        $result = $this->call($url, $host_record, 'POST');
        return $result['wid'];
    }

    /*
     * Update Host
     */

    public function update_host($wid, $hostname, $ip, $ip_class = 'v4')
    {
        $url = $this->api_url . '/registry/hosts/' . $wid;

        $host_record = [
            'hostname'  => $hostname,
            'glue'      => [
                [
                    'ip'            => $ip,
                    'class_field'   => $ip_class
                ]
            ]
        ];

        $result = $this->call($url, $host_record, 'PUT');
        return true;
    }

    /*
     * Delete Host
     */

    public function delete_host($wid)
    {
        $url = $this->api_url . '/registry/hosts/' . $wid;
        $result = $this->call($url, $host_record, 'DELETE');
        return true;
    }

    /**
     * Make external API call to registrar API.
     *
     * @param string $action
     * @param array $postfields
     *
     * @throws \Exception Connection error
     * @throws \Exception Bad API response
     *
     * @return array
     */

    public function call($url, $data, $method)
    {
        if (!strpos($url, '?') > 0) {
            $url = rtrim($url, '/') . '/';
        }

        // Convert it to JSON format
        $jsonformat = json_encode($data); 

        // A cURL command which can issue the request to the remote server
        $process = curl_init($url); 

        // Set the data type to be JSON
        curl_setopt($process, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic '.
            base64_encode($this->username . ':' . $this->password)
        ]);
        curl_setopt($process, CURLOPT_HEADER, true);

        // The timeout for this connection attempt,
        // if not results are received within 60 seconds, disconnect
        curl_setopt($process, CURLOPT_TIMEOUT, 60);
        // Let the invoker select the method
        curl_setopt($process, CURLOPT_CUSTOMREQUEST, $method);
        // If method is GET then POSTFIELDS should be PARAMS
        // The payload we're sending to the server
        curl_setopt($process, CURLOPT_POSTFIELDS, $jsonformat);
        // Allows us to use the resulting output as a string variable
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        // Don't verify the peer certificate, without this the command
        // will fail because gateway-otande uses a self-signed certificate
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE);
        // Don't verify the peer host information against the provided
        // certificate because it's a self-signed certificate
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($process, CURLINFO_HEADER_OUT, true);
        curl_setopt($process, CURLOPT_VERBOSE, true);

        $returnall = curl_exec($process); // Run the cURL command
        $code = curl_getinfo($process, CURLINFO_HTTP_CODE);
        $curlinfo = curl_getinfo($process);

        $return = substr($returnall, $curlinfo['header_size']);

        logModuleCall(
            'DNSAPI',
            'API CALL : ' . $method,
            $curlinfo['request_header'].$jsonformat,
            $returnall
        );

        $error_message = curl_error($process);
        $error_code = curl_errno($process);
        // Close the connection
        curl_close($process);
        // Decode the JSON string to an associative array for processing
        $results = json_decode($return, true);

        try {

            if ($code >= 300 && array_key_exists('message', $results)) {
                throw new \Exception(
                    'Server error (' . $code . '): '.
                    $results['message']
                );
            } else if (
                !array_key_exists('results', $results) &&
                array_key_exists('message', $results) &&
                $code >= 300
            ) {
                throw new \Exception(
                    'Server error (' . $code . '): '.
                    $results['message']
                );
            } else if (
                !array_key_exists('results', $results) &&
                array_key_exists('detail', $results) &&
                $code >= 300
            ) {
                throw new \Exception(
                    'Server error (' . $code . '): '.
                    $results['detail']
                );
            } else if ($code >= 300) {
                throw new \Exception(
                    'Server error (' . $code . '): '.
                    $return
                );
            } else if ($code == 0) {
                if ($error_code > 0) {
                    throw new \Exception(
                        'Connection Error ('. $error_message .'), URL: '.
                        $url
                    );
                } else {
                    throw new \Exception('Connection Error (0), URL: ' . $url);
                }
            }

        }
        catch (\Exception $e) {
            logModuleCall(
                'DNSAPI',
                'API: CALL Error: ' .$error_code . ' ' . $error_message,
                $data,
                $return
            );
            throw $e;
        }

        $results['success'] = true;

        return $results;
    }
}
