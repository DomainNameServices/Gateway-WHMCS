<?php

namespace WHMCS\Module\Registrar\dns_gateway;
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);
class DNSAPI {

    var $username;
    var $password;
    var $api_url;
    var $auth;
    protected $results = array();
    protected $lock_statusses = [
        'clientTransferProhibited',
        'clientUpdateProhibited',
        'clientDeleteProhibited'
    ];

    /*
     * Uses Setup->Product/Services->DomainRegistrars->ModulesName->Configure configuration.
     * 
     * Function is used to connect to the api.
     */

    public function setcreds($username, $password, $dev_mode, $ote_username, $ote_password) {
        $API_URL = 'https://gateway-epp.dns.net.za/api';
        $DEV_API_URL = 'https://gateway-otande.dns.net.za:8443/api';

        $this->username = ($dev_mode == 'on' ? $ote_username : $username);
        $this->password = ($dev_mode == 'on' ? $ote_password : $password);
        $this->api_url = ($dev_mode == 'on' ? $DEV_API_URL : $API_URL);
        $this->auth = base64_encode(($dev_mode == 'on' ? $ote_password : $password));
    }

    /*
     * Function generates a random password using the domain, username, and md5 encryption. 
     * We're also adding a # at the end, to ensure that all domain standards are met.
     */

    public function generate_password($domain) {
        //  Generate 
        $epp_key = substr(md5($domain . $this->username), 0, 6).'#A4';
        return $epp_key;
    }

    /*
     * Function generates a random ID using the domain, username, and md5 encryption. 
     */
    
    public function RandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randstring = '';
        while (strlen($randstring)<$length){
            $randstring .= $characters[rand(0, strlen($characters))];
        }
        return $randstring;
    }

    public function generate_id($domain) {
        return substr($domain, 0, 3).substr($this->username, 0,3).$this->RandomString(7);
    }
    
    #Contact Operation

    /*
     * Create Contact
     */

    public function setcontact($contact_info) {
        $url = $this->api_url . '/registry/contacts/';
        logModuleCall('DNSAPI', 'setcontact', $contact_info, $url);
        $result = $this->call($url, $contact_info, "POST");
        logModuleCall('DNSAPI', 'setcontact', $contact_info, $result);
        return $result['id'];
    }

    /*
     * Update Existing Contact
     */

    public function update_contact($contact_info, $existing_id) {
        //  Check For Existing Contact
        $url = $this->api_url . '/registry/contacts/' . $existing_id . '/';
        logModuleCall('DNSAPI', 'Pre : Update Contact', $contact_info, $url);
        $result = $this->call($url, $contact_info, "PUT");
        logModuleCall('DNSAPI', 'Update Contact', $contact_info, $result);
        return true;
    }

    /*
     * Pulls all Existing Contacts
     */

    public function contact_listing() {
        $url = $this->api_url . '/registry/contacts/';
        logModuleCall('DNSAPI', 'Pre : Contact Listing', [], $result);
        $result = $this->call($url, [], "GET");
        logModuleCall('DNSAPI', 'Contact Listing', [], $result);
        return $result['wid'];
    }

    /*
     * get the conatcs wid id
     */

    public function get_contact_wid($contact_id) {
        $url = $this->api_url . '/registry/contacts/?id=' . $contact_id;
        logModuleCall('DNSAPI', 'Pre : Get Contact WID', ['id' => $contact_id], $url);
        $result = $this->call($url, [], "GET");
        logModuleCall('DNSAPI', 'Get Contact WID', ['id' => $contact_id], $result);
        return $result['results'][0]['wid'];
    }

    /*
     * Pulls a called contacts info
     */

    public function contact_info($contact_id) {

        $wid = $this->get_contact_wid($contact_id);
        $url = $this->api_url . '/registry/contacts/' . $wid;
        
        logModuleCall('DNSAPI', 'Pre : Contact Info', ['id' => $wid], $url);
        $result = $this->call($url, [], "GET");
        logModuleCall('DNSAPI', 'Contact Info', ['id' => $wid], $result);
        
        return $result;
    }

    /*
     * Checks if a contact exists
     */

    public function contact_check($check, $contact_name = '') {
        $url = $this->api_url . '/registry/contacts/check/' . $contact_name;
        logModuleCall('DNSAPI', 'Pre : Contact Check', $check, $url);
        $result = $this->call($url, $check, "POST");
        logModuleCall('DNSAPI', 'Contact Check', $check, $result);
        return $result['results'];
    }

    ////////////////////
    #Domain Operations
    ////////////////////
    /*
     * Registers a new domain
     */
    public function register_domain($domain_info, $domain_id = '') {
        $url = $this->api_url . '/registry/domains/' . $domain_id;
        logModuleCall('DNSAPI', 'Pre : Register Domain', $domain_info, $url);
        $result = $this->call($url, $domain_info, "POST");
        logModuleCall('DNSAPI', 'Register Domain', $domain_info, $result);
        return $result['wid'];
    }

    /*
     * Pulls all Existing Domains
     *  - Can Be Filtered By Domain Name
     */

    public function list_domains($domain_name='') {
        $url = $this->api_url . '/registry/domains/'.($domain_name!='' ? '?name=' . $domain_name : '');
        logModuleCall('DNSAPI', 'Pre : List Domain', ['name' => $domain_name], $url);
        $result = $this->call($url, [], "GET");
        logModuleCall('DNSAPI', 'List Domain', ['name' => $domain_name], $result);
        return $result['results'];
    }

    /*
     * Get's Domain's info
     */

    public function view_domain($domain_wid) {
        $url = $this->api_url . '/registry/domains/'.$domain_wid;
        logModuleCall('DNSAPI', 'Pre : View Domain', ['name' => $domain_wid], $url);
        $result = $this->call($url, [], "GET");
        logModuleCall('DNSAPI', 'View Domain', ['name' => $domain_wid], $result);
        return $result;
    }

    /*
     * Updates Existing Domain
     */ 

    public function update_domain($domain_info, $domain_id = '') {
        $url = $this->api_url . '/registry/domains/' . $domain_id;
        logModuleCall('DNSAPI', 'Pre : Update Domain', $domain_info, $url);
        $result = $this->call($url, $domain_info, "PUT");
        logModuleCall('DNSAPI', 'Update Domain', $domain_info, $result);
        return $result['wid'];
    }

    /*
     * Delete Existing domain
     */

    public function delete_domain($domain_id) {
        $url = $this->api_url . '/registry/domains/' . $domain_id;
        logModuleCall('DNSAPI', 'Pre : Delete Domain', ['domain_wid' => $domain_id], $url);
        $result = $this->call($url, [], "DELETE");
        logModuleCall('DNSAPI', 'Delete Domain', ['domain_wid' => $domain_id], $result);
        return true; 
    }

    /*
     * Renews Existing Domain
     */

    public function renew_domain($domain, $domain_id) {
        $url = $this->api_url . '/registry/domains/' . $domain_id . '/renew/';
        logModuleCall('DNSAPI', 'Pre : Renew Domain', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Renew Domain', $domain, $result);
        return $result['wid'];
    }

    /*
     * transfers Existing domain
     */

    public function transfer_domain($domain) {
        $url = $this->api_url . '/registry/domains/transfer-request/';
        logModuleCall('DNSAPI', 'Pre : Transfer Domain', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Transfer Domain', $domain, $result);
        return $result['name'];
    }

    /*
     * Cancels Domain Transfer Request
     */

    public function cancel_domain_transfer($domain) {
        $url = $this->api_url . '/registry/domains/transfer-cancel/';
        logModuleCall('DNSAPI', 'Pre : Cancel Domain Transfer', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Cancel Domain Transfer', $domain, $result);
        return true;
    }

    /*
     * 
     */
    public function reject_transfer_domain($domain) {
        $url = $this->api_url . '/registry/domains/'.$domain.'/transfer-reject/';
        logModuleCall('DNSAPI', 'Pre : Reject Domain Transfer', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Reject Domain Transfer', $domain, $result);
        return true;
    }
    /*
     * 
     */
    public function approve_transfer_domain($domain, $domain_id) {
        $url = $this->api_url . '/registry/domains/'.$domain_id.'/transfer-approve/';
        logModuleCall('DNSAPI', 'Pre : Approve Domain Transfer', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Approve Domain Transfer', $domain, $result);
        return true;
    }
    /*
     * Pulls Information about Current Domain
     */

    public function domain_info($domain) {
        $url = $this->api_url . '/registry/domains/info/';
        logModuleCall('DNSAPI', 'Pre : Domain Info', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Domain Info', $domain, $result);
            
        if($this->username != $result['results']['rar']){
            throw new \Exception("Domain owned by: ".$result['results']['rar']);
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

    public function domain_Lock($domain_name) {
        $domain_info = $this->list_domains($domain_name);
        $domain = [
            'name' => $domain_name
        ];

        $url = $this->api_url . '/registry/domains/' . $domain_info[0]['wid'] . '/lock/';
        logModuleCall('DNSAPI', 'Pre : Lock Domain', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Lock Domain', $domain, $result);
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

    public function domain_unlock($domain_name) {
        $domain_info = $this->list_domains($domain_name);
        $domain = [
            'name' => $domain_name
        ];

        $url = $this->api_url . '/registry/domains/' . $domain_info[0]['wid'] . '/unlock/';
        logModuleCall('DNSAPI', 'Pre : Unlock Domain', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Unlock Domain', $domain, $result);
        return true;
    }

    /*
     * Check if the Domain exists
     */

    public function domain_check($domain) {
        $url = $this->api_url . '/registry/domains/check/';
        logModuleCall('DNSAPI', 'Pre : Domain Check', $domain, $url);
        $result = $this->call($url, $domain, "POST");
        logModuleCall('DNSAPI', 'Domain Check', $domain, $result);
        return $result;
    }

    /*
     * Function checks if a Domain is locked or unlocked
     */

    public function check_domain_lock($domain) {
        logModuleCall('DNSAPI', 'Pre : Domain Lock Check', $domain, ['locked' => (bool) $locked]);
            
        // Grab Domain Info
            $domain_arr =   [
                "name"  =>  $domain,
                "authinfo"  =>  $this->generate_password($domain)
            ];
        
            $domain_info = $this->domain_info($domain_arr);
            $locked = false;
            foreach ($domain_info['statuses'] as $status) {
                if (in_array($status['code'], $this->lock_statusses)) {
                    $locked = true;
                }
            }

            logModuleCall('DNSAPI', 'Domain Lock', $domain, ['locked' => (bool) $locked]);
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

    public function list_hosts($hostname='') {
        $url = $this->api_url . '/registry/hosts/'.($hostname!='' ? '?hostname='.$hostname : '');
        
        logModuleCall('DNSAPI', 'Pre : List Hosts', $host_record, $url);
        $result = $this->call($url, $host_record, "GET");
        logModuleCall('DNSAPI', 'List Hosts', $host_record, $result);
        return $result['results'];
    }

    /*
     * Create Host
     */

    public function create_host($hostname, $ip, $ip_class = 'v4') {
        $url = $this->api_url . '/registry/hosts/';
        
        $host_record = [
            'hostname'  =>  $hostname,
            "glue"  =>  [
                [
                    "ip"    =>  $ip,
                    "class_field"   =>  $ip_class
                ]
            ]
        ];
        
        logModuleCall('DNSAPI', 'Pre : Create Host', $host_record, $url);
        $result = $this->call($url, $host_record, "POST");
        logModuleCall('DNSAPI', 'Create Host', $host_record, $result);
        return $result['wid'];
    }
    
    /*
     * Update Host
     */

    public function update_host($wid, $hostname, $ip, $ip_class = 'v4') {
        $url = $this->api_url . '/registry/hosts/'.$wid;
        
        $host_record = [
            'hostname'  =>  $hostname,
            "glue"  =>  [
                [
                    "ip"    =>  $ip,
                    "class_field"   =>  $ip_class
                ]
            ]
        ];
        
        logModuleCall('DNSAPI', 'Pre : Update Host', $host_record, $url);
        $result = $this->call($url, $host_record, "PUT");
        logModuleCall('DNSAPI', 'Update Host', $host_record, $result);
        return true;
    }
    
    /*
     * Delete Host
     */

    public function delete_host($wid) {
        $url = $this->api_url . '/registry/hosts/'.$wid;
        
        logModuleCall('DNSAPI', 'Pre : Delete Host', $host_record, $url);
        $result = $this->call($url, $host_record, "DELETE");
        logModuleCall('DNSAPI', 'Delete Host', $host_record, $result);
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
    public function call($url, $data, $method) {
        if (!strpos($url, '?') > 0)
            $url = rtrim($url, '/') . '/';

        $jsonformat = json_encode($data); // Convert it to JSON format

        $process = curl_init($url); // A cURL command which can issue the request to the remote server
        
        curl_setopt($process, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Basic " . base64_encode($this->username . ":" . $this->password))); // Set the data type to be JSON
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_TIMEOUT, 30); // The timeout for this connection attempt, if not results are received within 30 seconds, disconnect
        curl_setopt($process, CURLOPT_CUSTOMREQUEST, $method); // Let the invoker select the method
        // If method is GET then POSTFIELDS should be PARAMS
        curl_setopt($process, CURLOPT_POSTFIELDS, $jsonformat); // The payload we're sending to the server
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE); // Allows us to use the resulting output as a string variable
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE); // Don't verify the peer certificate, without this the command will fail because gateway-otande uses a self-signed certificate
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE); // Don't verify the peer host information against the provided certificate because it's a self-signed certificate


        $return = curl_exec($process); // Run the cURL command
        //logModuleCall('DNSAPI', 'API CALL : ' . $method, $data, $return);
        $code = curl_getinfo($process, CURLINFO_HTTP_CODE);
        curl_close($process); // Close the connection
        $results = json_decode($return, true); // Decode the JSON string to an associative array for processing
        


        if ($code >= 300 && array_key_exists("message", $results)) {
            throw new \Exception("Server error (" . $code . "): " . $results["message"]);
        } else if (!array_key_exists("results", $results) && array_key_exists("message", $results) && $code >= 300) {
            throw new \Exception("Server error (" . $code . "): " . $results["message"]);
        } else if (!array_key_exists("results", $results) && array_key_exists("detail", $results) && $code >= 300) {
            throw new \Exception("Server error (" . $code . "): " . $results["detail"]);
        } else if ($code >= 300) {
            throw new \Exception("Server error (" . $code . "): " . $return);
        } else if ($code == 0) { 
            throw new \Exception("Connection Error (0), URL: ".$url);
        } 

        $results["success"] = true;

        return $results;
    }

}
