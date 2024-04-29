<?php
    /*
     * @copyright Copyright (c) DNS Africa 2023
     * @author  David Peall <david@dns.business>
     */
    namespace WHMCS\Module\Registrar\dns_gateway;

    use Throwable;

    class RestAPIException extends \Exception
    {
        /** @var array|null stores the output array from the API even though a error is retuned there can be helpful
         * information int the contents.
         */
        protected $api_output;

        public function __construct($message, $code, array $output = null, Throwable $previous = null)
        {
            /** @var output set from constructor values*/
            $this->api_output = $output;

            /** call the parent constructor */
            parent::__construct($message, $code, $previous);
        }

        /**
         * Return the contents of the api output
         * @return array|null
         */
        public function get_api_output(): array
        {
            return $this->api_output;
        }
    }
