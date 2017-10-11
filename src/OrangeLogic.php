<?php

namespace OdieMiranda\OrangeLogic;

/**s
 * Simple OrangeLogic API wrapper
 *
 * @author Odie Miranda <odie.miranda@gmail.com>
 * @version 1.0
 */
class OrangeLogic
{
    private $domain;
    private $loginID;
    private $password;
    private $token = null;
    private $api_endpoint = 'https://<domain>/API';

    /*  SSL Verification
        Read before disabling: 
        http://snippets.webaware.com.au/howto/stop-turning-off-curlopt_ssl_verifypeer-and-fix-your-php-config/
    */
    public $verify_ssl = true;

    private $request_successful = false;
    private $last_error         = '';
    private $last_response      = array();
    private $last_request       = array();

    private $countPerPage       = 20;
    private $searchInfo         = array();

    /**
     * Create a new instance
     * @param string domain Your OrangeLogic Asset Manager Domain
     * @param string $loginID Your OrangeLogic API LoginID
     * @param string $password Your OrangeLogic API Password 
     * @throws \Exception
     */
    public function __construct($domain, $loginID, $password)
    {
        $this->domain = trim($domain);
        $this->loginID = $loginID;
        $this->password = $password;

        if (strlen($this->domain) <= 0) {
            throw new \Exception('Domain cannot be empty.');
        }

        if (!$this->is_valid_domain()) {
            throw new \Exception('Invalid domain format.');
        }

        $this->api_endpoint     = str_replace('<domain>', $this->domain, $this->api_endpoint);
        $this->last_response    = array('header' => null, 'body' => null);
        $this->searchInfo       = array('TotalCount' => 0, 'Sort' => '', 'NextPage' => false, 'PrevPage' => false, 'Items' => array());
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $this->getToken();

    }

    /**
     * Check if Token is present if not request a new token from OrangeLogic API
     */
    public function getToken()
    {
        if (is_null($this->token)) {
            // check if token is in session
            if (isset($_SESSION['ol_token'])) {
                // check if token is expired
                if ($_SESSION['ol_token_timeout'] < time()) {
                    // token is timed out
                    $this->getNewToken();
                } else {
                    $this->token = $_SESSION['ol_token'];
                }
            } else {
                $this->getNewToken();
            }
        }
        return $this->token;
    }

    /**
     * Search Media from OrangeLogic Search API 
     *
     * @param string $text Filter by partial content in title, description, keywords, artist, and other searchable fields
     * @param string $keyword Filter by specific Keyword
     * @param string $mediaType Filter by media type, default '' for all. 
     *                          Media Type values 'Image', 'Video', 'Audio', 'Album', 'Story', 'Graphic'
     * @param int $page Page number of the search request
     * @param string $sortBy Sort result 'Newest', 'Oldest', 'Ranking', 'Relevancy'
     * @param string $fields Fields that will be included in the search result
     */
    public function search($text = '', $keyword = '', $mediaType = '', $page = 1, $sortBy = 'Newest', $fields = 'Path_WebHigh,Path_CMS1,Path_TR7,Path_TR3,Path_TR2,Path_TR1,SystemIdentifier,MediaIdentifier,MediaEncryptedIdentifier,MediaNumber,Title,Caption,CaptionLong,MediaDate,CreateDate,EditDate,copyright,Photographer,Artist,MediaType,Link,MaxWidth,MaxHeight')
    {
        $query = '';
        $text = trim($text);
        $keyword = trim($keyword);
        $mediaType = trim($mediaType);
        $mediaType = in_array($mediaType, ['Image', 'Video', 'Audio', 'Album', 'Story', 'Graphic']) ? $mediaType : '';
        
        if (strlen($text) > 0) {
            $query .= 'Text:"' . $text . '" ';
        }

        if (strlen($keyword) > 0) {
            $query .= 'Keyword:"' . $keyword . '" ';
        }

        if (strlen($mediaType) > 0) {
            $query .= 'MediaType:"' . ucfirst($mediaType) . '" ';
        }


        $response = $this->post('search/v3.0/search', [
                        'query' => $query, 
                        'fields' => $fields,
                        'sort' => $sortBy,
                        'countperpage' => $this->countPerPage,
                        'pagenumber' => $page,
                        'token' => $this->getToken()
                    ]);
        
        $this->searchInfo = array('TotalCount' => 0, 'Sort' => '', 'NextPage' => false, 'PrevPage' => false, 'Items' => array());
        if (isset($response['APIResponse'])) {
            $this->searchInfo['TotalCount'] = intval($response['APIResponse']['GlobalInfo']['TotalCount']);
            $this->searchInfo['Sort'] = $response['APIResponse']['GlobalInfo']['Sort'];
            $this->searchInfo['NextPage'] =  isset($response['APIResponse']['GlobalInfo']['NextPage']) ? $response['APIResponse']['GlobalInfo']['NextPage'] : false;
            $this->searchInfo['PrevPage'] =  isset($response['APIResponse']['GlobalInfo']['PrevPage']) ? $response['APIResponse']['GlobalInfo']['PrevPage'] : false;
            $this->searchInfo['Items'] = $response['APIResponse']['Items'];
            return true;
        }
        return false;
    }

    /**
     * Returns the search result items
     */
    public function getItems()
    {
        return $this->searchInfo['Items'];
    }

    /**
     * Returns the total search result count
     */
    public function getTotalCount()
    {
        return $this->searchInfo['TotalCount'];
    }

    /**
     * Set the returned result per page 
     */
    public function setCountPerPage($count)
    {
        $this->countPerPage = intval($count);
    }

    /**
     * Returns the current result per page count
     */
    public function getCountPerPage()
    {
        return $this->countPerPage;
    }

    /**
     * Get current token
     */
    public function getCurrentToken()
    {
        return $this->token;
    }

    /**
     * Get New Token from OrangeLogic API
     */
    private function getNewToken()
    {
        $response = $this->get('Authentication/v1.0/Login', [
                        'login' => $this->loginID, 
                        'password' => $this->password
                    ]);
        if (!$this->request_successful) {
            $this->token = null;
        } else {
            $timeout = intval($response['APIRequestInfo']['TimeoutPeriodMinutes']); // in minutes
            $this->token = $response['APIResponse']['Token'];
            // save token to session for future request
            $_SESSION['ol_token'] = $this->token;
            $_SESSION['ol_token_timeout'] = time() + ($timeout * 60);
        }
        
    }

    /**
     * Make an HTTP GET request - for retrieving data
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function get($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('get', $method, $args, $timeout);
    }

    /**
     * Make an HTTP POST request - for creating and updating items
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function post($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('post', $method, $args, $timeout);
    }

    /**
     * Performs the underlying HTTP request. Not very exciting.
     * @param  string $http_verb The HTTP verb to use: get, post, put, patch, delete
     * @param  string $method The API method to be called
     * @param  array $args Assoc array of parameters to be passed
     * @param int $timeout
     * @return array|false Assoc array of decoded result
     * @throws \Exception
     */
    private function makeRequest($http_verb, $method, $args = array(), $timeout = 10)
    {
        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }

        $url = $this->api_endpoint . '/' . $method;

        $this->last_error         = '';
        $this->request_successful = false;
        $response                 = array('headers' => null, 'body' => null);
        $this->last_response      = $response;

        $this->last_request = array(
            'method'  => $http_verb,
            'path'    => $method,
            'url'     => $url,
            'body'    => '',
            'timeout' => $timeout,
        );

        // force response to JSON
        $args['format'] = 'json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'OdieMiranda/OrangeLogic-API/1.0 (github.com/odiemiranda/orangelogic-api)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        switch ($http_verb) {
            case 'post':
                curl_setopt($ch, CURLOPT_POST, true);
                $this->attachRequestPayload($ch, $args);
                break;

            case 'get':
                $query = http_build_query($args);
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
                break;
        }

        $response['body']    = curl_exec($ch);
        $response['headers'] = curl_getinfo($ch);

        if (isset($response['headers']['request_header'])) {
            $this->last_request['headers'] = $response['headers']['request_header'];
        }

        if ($response['body'] === false) {
            $this->last_error = curl_error($ch);
        }

        curl_close($ch);

        // update token timeout
        if (isset($_SESSION['ol_token_timeout'])) {
            $_SESSION['ol_token_timeout'] = time() + ($timeout * 60);
        }

        return $this->formatResponse($response);
    }

    /**
     * Encode the data and attach it to the request
     * @param   resource $ch cURL session handle, used by reference
     * @param   array $data Assoc array of data to attach
     */
    private function attachRequestPayload(&$ch, $data)
    {
        $encoded = http_build_query($data);
        $this->last_request['body'] = $encoded;
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }

    /**
     * Decode the response and format any error messages for debugging
     * @param array $response The response from the curl request
     * @return array|false     The JSON decoded into an array
     */
    private function formatResponse($response)
    {
        $this->last_response = $response;

        if (!empty($response['body'])) {

            $d = json_decode($response['body'], true);

            if (!isset($d['APIResponse'])) {
                $this->last_error = 'Invalid response.';
            } else if (isset($d['APIResponse']['Code']) && strtolower($d['APIResponse']['Code']) != 'success') {
                $this->last_error = $d['APIResponse']['Code'];
            } else {
                $this->request_successful = true;
            }

            return $d;
        }

        return false;
    }

    /**
     * Private function to validate if domain is valid format
     * @param string $url 
     */
    private function is_valid_domain() 
    {
        if (!preg_match("/^([-a-z0-9]{2,100})\.([a-z\.]{2,8})$/i", $this->domain)) {
            return false;
        }
        return true;
    }

}