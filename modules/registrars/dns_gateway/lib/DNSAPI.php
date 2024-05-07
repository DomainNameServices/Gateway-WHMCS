<?php
    /*
     * @copyright Copyright (c) DNS Africa 2023
     * @author  David Peall <david@dns.business>
     *
     */
    namespace WHMCS\Module\Registrar\dns_gateway;

    use PHPUnit\Logging\Exception;
    use function PHPUnit\Framework\isJson;
    use function PHPUnit\Framework\lessThanOrEqual;

    class DNSAPI
    {
        /**
         * @var string
         */
        protected $username;
        /**
         * @var string
         */
        protected $password;
        /**
         * @var string
         */
        protected $epp_username;
        /**
         * @var string
         */
        protected $registry_api_url;
        /**
         * @var string
         */
        protected $portal_api_url;
        /**
         * @var string
         */
        protected $mode;
        /**
         * @var bool
         */
        public $debug=false;
        /**
         * @var bool
         */
        protected $curl_auth=true;
        /**
         * @var bool
         */
        protected $throw_http_error=true;
        protected $curl_timeout=60;
        protected $curl_connect_timeout=20;
        protected $curl_followlocation=1;
        protected $curl_sslverifypeer=true;
        protected $curl_sslverifyhost=true;
        /**
         * @var mixed[]
         */
        protected $curl_headers = array(
            'Content-Type: application/json'
        );
        /**
         * @var string
         */
        protected $bearer = '';
        protected $lock_statusses = [
            'clientTransferProhibited',
            'clientUpdateProhibited',
        ];

        public function __construct(array $params)
        {
            $this->username = $params["Portal_Username"];
            $this->password = $params["Portal_Password"];
            $this->mode = $params["AccountMode"];

            if ($this->mode == 'live')
            {
                $this->registry_api_url = "https://srs-epp.dns.net.za/api/";
                $this->portal_api_url = "https://srs-epp.dns.net.za/portal/";
            } else {
                $this->registry_api_url = "https://gateway-otande.dns.net.za:8443/api/";
                $this->portal_api_url = "https://srs-epp.dns.net.za/portal/";
            }

            if (key_exists("debug", $params))
                $this->debug = (bool)$params["debug"];

            $this->doLogin();
        }

        function doLogin()
        {
            session_start();
            if(isset($_SESSION['apitokentime']) or isset($_SESSION['appbearer']) or isset($_SESSION['appbearer'])) {
                $dateTimeObject1 = date_create($_SESSION['apitokentime']);
                $dateTimeObject2 = date_create(date('H:i:s'));
                $interval = date_diff($dateTimeObject1, $dateTimeObject2);

                $minutes = $interval->days * 24 * 60;
                $minutes += $interval->h * 60;
                $minutes += $interval->i;

                # only valid for 24 hours so just shy of 1440
                if ($minutes < 1400) {
                    $this->bearer = $_SESSION['appbearer'];
                    $this->epp_username = $_SESSION['epp_username'];
                    return;
                }
            }

            $login_details = array("username" => $this->username, "password" => $this->password);
            $login = $this->postCH($this->portal_api_url."auth-jwt/", $login_details);
            $this->bearer = $login['token'];
            $this->epp_username = $this->getCH($this->portal_api_url."auth-epp-username/")["epp_username"];
            $_SESSION['apitokentime']=date('H:i:s');
            $_SESSION['appbearer']=$this->bearer;
            $_SESSION['epp_username']=$this->epp_username;

            syslog(LOG_DEBUG, "New API bearer token valid from: " . $_SESSION['apitokentime'] );
        }

        /**
         * Prepare the basic curl object for the API call
         * @param string $url
         * @return resource
         */
        private function initCH(string $url)
        {
            $ch = curl_init();

            $authorization = "Authorization: Bearer " . $this->bearer; // Prepare the authorisation token
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($this->curl_headers, $authorization));
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->curl_sslverifypeer);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->curl_sslverifyhost);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->curl_connect_timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->curl_followlocation);
            curl_setopt($ch, CURLOPT_XOAUTH2_BEARER, $this->bearer);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);

            return $ch;
        }


        /**
         * Generic function to use a curl http DEL command on the RestAPI
         * @param string $url - additional url information for the API call
         * @return array dictionary of key value pairs containing the curl output and http_code
         * @throws RestAPIException
         */
        protected function delCH($url): array
        {
            $ch = $this->initCH($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            $output = $this->execCH($ch);

            return $output;
        }

        /**
         * Generic function to use a curl http GET command on the RestAPI
         * @param string $url - additional url information for the API call
         * @return array dictionary of key value pairs containing the curl output and http_code
         * @throws RestAPIException
         */
        protected function getCH($url): array
        {

            $ch = $this->initCH($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $return = $this->execCH($ch);
            if ($this->debug) {
            //    logModuleCall('dns_gateway',"getdata",$url,$return);
            }
            return $return;
        }

        /**
         * Generic function to use a curl http PUT command on the RestAPI
         * @param string $url additional url information for the API call
         * @param array $post_data dictionary of key/values to be posted
         * @return array dictionary of key value pairs containing the curl output and http_code
         * @throws RestAPIException
         */
        protected function putCH($url, $post_data): array
        {
            
            $data_json = json_encode($post_data);
            $authorization = "Authorization: Bearer " . $this->bearer; // Prepare the authorisation token
            $curl_headers = array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_json),
                $authorization
            );

            $ch = $this->initCH($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);

            $return = $this->execCH($ch);

            if($this->debug) 
            {
               // logModuleCall('dns_gateway',"putCH",$post_data,$return);
            }
            return $return;
        }

        /**
         * Generic function to use a curl http POST command on the RestAPI
         * @param string $url additional url information for the API call
         * @param array $post_data dictionary of key/values to be posted
         * @return array dictionary of key value pairs containing the curl output and http_code
         * @throws RestAPIException
         */
        protected function postCH($url, $post_data): array
        {
            $authorization = "Authorization: Bearer " . $this->bearer; // Prepare the authorisation token
            $ch = $this->initCH($url);

            if(!empty($post_data))
            {
                $data_json = json_encode($post_data);
                $curl_headers = array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_json),
                    $authorization
                );

                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
            }
            else
            {
                $curl_headers = array(
                    'Content-Type: application/json',
                    $authorization
                );
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

            $return = $this->execCH($ch);
            if ($this->debug) {
                //logModuleCall('dns_gateway',"postCH".$url,$post_data,$return);
            }
            return $return;
        }

        /**
         * This function executes the curl request
         *
         * @param $ch : Curl object
         * @return string|array
         * @throws RestAPIException
         */
        private function execCH($ch)
        {
            $verbose = NULL;
            if ($this->debug) {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_STDERR, $verbose = fopen('php://temp', 'rw+'));
            }

            $output = curl_exec($ch);
            $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            /** Add error to error log */
            if ($this->debug) {
                logModuleCall('dns_gateway',"execCH1", static::class . '::execCH >> ' .
                    curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ' up:' .
                    curl_getinfo($ch, CURLINFO_SIZE_UPLOAD) . ' down:' .
                    curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) .
                    ' - ' . $response_code, $output);
            }

            if ($this->debug) {
                logModuleCall('dns_gateway',"execCH2", static::class . '::execCH >> ' . $output . " Headers:" . curl_getinfo($ch, CURLINFO_HEADER_OUT ), $output);
            }

            syslog(LOG_DEBUG, "dns_gateway:". static::class . '::execCH >> ' .
                curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ' up:' .
                curl_getinfo($ch, CURLINFO_SIZE_UPLOAD) . ' down:' .
                curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) .
                ' - ' . $response_code . ' : ' . $output);

            $output = json_decode($output, true);

            /** if the API is set to throw http errors then check for non 2xx response */
            if ($this->throw_http_error && ($response_code < 200 || $response_code >= 300)){

                /** If detail for error exists, add message to error */
                if(isset($output['detail'])){

                    throw new RestAPIException($output['detail'], $response_code, $output);
                }else{
                    if (is_array($output))
                        $output_string = json_encode($output);
                    else
                        $output_string = $output;
                    throw new RestAPIException("Error from the API ".curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)." $response_code: $output_string", $response_code,
                    $output);
                }
            }

            $output['http_code'] = $response_code;

            curl_close($ch);
            return $output;
        }

        /**
         * @param string $url
         * @param int $limit
         * @return mixed
         * @throws RestAPIException
         */
        protected function _fetch_all(string $url, int $limit = 100)
        {
            /** Set the limit on the number of entries to get in one request the max is typically 100 */
            $url .= '&limit=' . $limit;

            $output = $this->getCH($url);

            $count = intval($output['count']);

            /** If there is more than $limit entries we need to fetch them in batches of $limit */
            if ($count > $limit) {
                $offset = 0;
                while ($count > $offset) {
                    $offset += $limit;
                    $loop_url = $url . '&offset=' . $offset;
                    $more = $this->getCH($loop_url);

                    /** add the array to the collection for return */
                    $output['results'] = array_merge($output['results'], $more['results']);
                }
            }
            return $output['results'];
        }

        static private function endsWith(string $needle, string $haystack): bool
        {
            $length = strlen( $needle );
            if( !$length ) {
                return true;
            }
            return substr( $haystack, -$length ) === $needle;
        }

        /*
         * Function generates a random password using the domain,
         * username, and md5 encryption.
         * We're also adding a # at the end,
         * to ensure that all domain standards are met.
         */
        static public function generate_password($domain): string
        {
            // Generate
            if (DNSAPI::endsWith('.na',$domain)) {
                $epp_key = DNSAPI::RandomString(16);
            } elseif (DNSAPI::endsWith('co.za',$domain))
            {
                $epp_key = 'coza';
            } else {
                $epp_key = DNSAPI::RandomString(16, true);
            }
            return $epp_key;
        }

        /*
         * Function generates a random ID using
         * the domain, username, and md5 encryption.
         */
        /**
         * @param bool $use_special_chars
         */
        static public function RandomString($length, $use_special_chars = False): string
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            if ($use_special_chars)
                $characters .= "?!@#\$%^&*()";
            $random_string = '';
            while (strlen($random_string)<$length) {
                $random_string .= $characters[rand(0, strlen($characters))];
            }

            // Ensure special characters are included.
            if ($use_special_chars){
                if (preg_match('/[a-zA-Z0-9]+$/', $random_string)){
                    $random_string = DNSAPI::RandomString($length, $use_special_chars);
                }
            }
            return $random_string;
        }

        public function generate_id($domain): string
        {
            return "DNS_GW_" . substr($domain, 0, 3) . DNSAPI::RandomString(6);
        }

        #Contact Operation

        /*
         * Create Contact
         */

        public function setcontact($contact_info)
        {
            $url = $this->registry_api_url . 'registry/contacts/';
            $result = $this->postCH($url, $contact_info);
            return $result['id'];
        }

        /*
         * Update Existing Contact
         */

        public function update_contact($contact_info, $existing_id)
        {
            // Check For Existing Contact
            $url = $this->registry_api_url . 'registry/contacts/' . $existing_id . '/';
            $result = $this->putCH($url, $contact_info);
            return true;
        }

        /*
         * Pulls all Existing Contacts
         */
        public function contact_listing()
        {
            $url = $this->registry_api_url . 'registry/contacts/';
            $result = $this->getCH($url);
            return $result['wid'];
        }

        /*
         * Get the conatcs wid id
         */
        public function get_contact_wid($contact_id)
        {
            $url = $this->registry_api_url . 'registry/contacts/?id=' . $contact_id;
            $result = $this->getCH($url);
            return $result['results'][0]['wid'];
        }

        /*
         * Pulls a called contacts info
         */
        public function contact_info($contact_id): array
        {
            $wid = $this->get_contact_wid($contact_id);
            $url = $this->registry_api_url . 'registry/contacts/' . $wid;
            return $this->getCH($url);
        }

        /*
         * Checks if a contact exists
         */

        public function contact_check($check, $contact_name = '')
        {
            $url = $this->registry_api_url . 'registry/contacts/check/' . $contact_name;
            $result = $this->postCH($url, $check);
            return $result['results'];
        }

        #Domain Operations

        /*
         * Registers a new domain
         */

        public function register_domain($domain_info)
        {
            $url = $this->registry_api_url . 'registry/domains/';
            $result = $this->postCH($url, $domain_info);
            return $result['wid'];
        }

        /*
         * Pulls all Existing Domains
         *  - Can Be Filtered By Domain Name
         */

        public function list_domains($domain_name = '')
        {
            $url = $this->registry_api_url.
                    'registry/domains/'.
                    ($domain_name!='' ? '?name=' . $domain_name : '');

            $result = $this->getCH($url);
            return $result['results'];
        }

        /*
         * Pulls Domains Transferring In
         *  - Can Be Filtered By Domain Name
         */

        public function list_domain_transfer_in($domain_name = '')
        {
            $url = $this->registry_api_url.
                'registry/transfersin/'.
                ($domain_name!='' ? '?name=' . $domain_name : '');

            $result = $this->getCH($url);
            return $result['results'];
        }

        /*
         * Pulls Domains Transferring Out
         *  - Can Be Filtered By Domain Name
         */

        public function list_domain_transfer_out($domain_name = '')
        {
            $url = $this->registry_api_url.
                'registry/transfersout/'.
                ($domain_name!='' ? '?name=' . $domain_name : '');

            $result = $this->getCH($url);
            return $result['results'];
        }

        /*
         * Get's Domain's info
         */

        public function view_domain($domain_wid)
        {
            $url = $this->registry_api_url . 'registry/domains/' . $domain_wid;
            $result = $this->getCH($url);
            return $result;
        }

        /*
         * Updates Existing Domain
         */

        public function update_domain($domain_info, $domain_id = '')
        {
            $url = $this->registry_api_url . 'registry/domains/' . $domain_id.'/';
            $result = $this->putCH($url, $domain_info);
            return $result['wid'];
        }

        /*
         * Delete Existing domain
         */

        /**
         * @throws RestAPIException
         */
        public function delete_domain($domain_id)
        {
            $url = $this->registry_api_url . 'registry/domains/' . $domain_id;
            $result = $this->delCH($url);
            if ($result["http_code"] >= 200 and $result["http_code"]  < 300)
                return true;
            else
                if (key_exists("details", $result))
                    throw new RestAPIException("Failed to delete domain: ". $result["details"], $result["http_code"], $result);
                else
                    throw new RestAPIException("Failed to delete domain: ", $result["http_code"], $result);
        }

        /*
         */

        /**
         * Renew Existing Domain
         *
         * @param array $domain
         * Required values
         *                  'name' => Domain Name
         *                  'curExpDate' => The current expiry date [yyyy-mm-dd] in UTC
         * Optional values
         *                  'period' => number of years,
         *                  'period_unit' => 'y', for years
         *
         * @return int
         * @throws RestAPIException
         */
        public function renew_domain($domain): int
        {
            $url = $this->registry_api_url . 'registry/WHMCS/domains/' . $domain["name"] . '/renew/';
            $result = $this->postCH($url, $domain);
            return (int)$result['wid'];
        }

        /*
         * Transfers Existing domain
         */

        public function transfer_domain($domain)
        {
            $url = $this->registry_api_url . 'registry/domains/transfer-request/';
            $result = $this->postCH($url, $domain);
            return $result['name'];
        }

        /*
         * Cancels Domain Transfer Request
         */

        public function cancel_domain_transfer($domain)
        {
            $url = $this->registry_api_url . 'registry/domains/transfer-cancel/';
            $result = $this->postCH($url, $domain);
            return true;
        }

        /*
         * Reject Domain Transfer
         */

        public function reject_transfer_domain($domain)
        {
            $url = $this->registry_api_url.
                    'registry/domains/'.
                    $domain.
                    '/transfer-reject/';

            $result = $this->postCH($url, $domain);
            return true;
        }

        /*
         * Approve Domain Transfer
         */

        public function approve_transfer_domain($domain, $domain_id)
        {
            $url = $this->registry_api_url.
                    'registry/domains/'.
                    $domain_id .
                    '/transfer-approve/';

            $result = $this->postCH($url, $domain);
            return true;
        }

        /**
         * Pulls Information about Current Domain from the registry
         * @param $domain
         * @return mixed
         * @throws RestAPIException
         */
        public function domain_info($domain)
        {
            $url = $this->registry_api_url . 'registry/domains/info/';
            $result = $this->postCH($url, array("name" => $domain));

            if ($this->epp_username != $result['results']['rar']) {
                throw new RestAPIException('Domain owned by: ' . $result['results']['rar'], null);
            }

            return $result['results'];
        }

        /*
         * Pulls Information about Current Domain
         */

        public function domain_sync($domain)
        {
            $url = $this->registry_api_url . "registry/WHMCS/domains/$domain/";

            return $this->getCH($url);
        }

        /*
         * Locks selected domain
         *
         * will be unable to -
         * Update
         * Delete
         * or Transfer
         */
        /**
         * @param string $domain_name
         */
        public function get_domain_wid($domain_name): int
        {
            $url = $this->registry_api_url . "registry/WHMCS/domains/$domain_name/wid/";
            $result = $this->getCH($url);

            return $result["wid"];
        }

        /**
         * @param string $domain_name
         */
        public function get_domain_nameservers($domain_name): array
        {
            $url = $this->registry_api_url . "registry/WHMCS/domains/$domain_name/nameservers/";

            return $this->getCH($url);
        }

        public function get_domain_contacts($domain_name): array
        {
            $url = $this->registry_api_url . "registry/WHMCS/domains/$domain_name/contacts/";

            return $this->getCH($url);
        }
        /*
         * Check if the Domain exists
         */

        public function domain_check($domain)
        {
            $url = $this->registry_api_url . 'registry/domains/check/';
            $result = $this->postCH($url, array("name" => $domain));
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
            $url = $this->registry_api_url.
                    'registry/hosts/'.
                    ($hostname!='' ? '?hostname=' . $hostname : '');

            $result = $this->getCH($url);
            return $result['results'];
        }

        /*
         * Create Host
         */

        public function create_host($hostname, $ip, $ip_class = 'v4')
        {
            $url = $this->registry_api_url . 'registry/hosts/';

            $host_record = [
                'hostname'  => $hostname,
                'glue'      => [
                    [
                        'ip'            => $ip,
                        'class_field'   => $ip_class
                    ]
                ]
            ];

            $result = $this->postCH($url, $host_record);
            return $result['wid'];
        }

        /*
         * Update Host
         */

        public function update_host($wid, $hostname, $ip, $ip_class = 'v4')
        {
            $url = $this->registry_api_url . 'registry/hosts/' . $wid;

            $host_record = [
                'hostname'  => $hostname,
                'glue'      => [
                    [
                        'ip'            => $ip,
                        'class_field'   => $ip_class
                    ]
                ]
            ];

            $result = $this->putCH($url, $host_record);
            return true;
        }

        /*
         * Delete Host
         */

        public function delete_host($wid)
        {
            $url = $this->registry_api_url . 'registry/hosts/' . $wid;
            $result = $this->delCH($url);
            return true;
        }

        public function tld_pricing()
        {
            $url = $this->portal_api_url . 'billing/pricelist/?';
            $result = $this->_fetch_all($url);
            return $result;
        }

        public function suspend($domain)
        {
            $url = $this->registry_api_url . 'registry/WHMCS/domains/'.$domain.'/suspend/';
            $result = $this->postCH($url,array());

            if($result['http_code']==200)
                return true;

            return false;
        }

        public function unsuspend($domain)
        {
            $url = $this->registry_api_url . 'registry/WHMCS/domains/'.$domain.'/unsuspend/';
            $result = $this->postCH($url,array());
            
            if($result['http_code']==200)
                return true;

            return false;
        }

        public function lock($domain)
        {
            $url = $this->registry_api_url . 'registry/WHMCS/domains/'.$domain.'/lock/';
            $result = $this->postCH($url,array());

            if($result['http_code']==200)
                return true;

            return false;
        }

        public function unlock($domain)
        {
            $url = $this->registry_api_url . 'registry/WHMCS/domains/'.$domain.'/unlock/';
            $result = $this->postCH($url,array());

            if($result['http_code']==200)
                return true;

            return false;
        }

        public function block($domain)
        {
            $url = $this->registry_api_url . 'registry/WHMCS/domains/'.$domain.'/block/';
            $result = $this->postCH($url,array());

            if($result['http_code']==200)
                return true;

            return false;
        }

        public function unblock($domain)
        {
            $url = $this->registry_api_url . 'registry/WHMCS/domains/'.$domain.'/unblock/';
            $result = $this->postCH($url,array());

            if($result['http_code']==200)
                return true;

            return false;
        }
    }
