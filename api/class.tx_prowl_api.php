<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Frank Nägler <typo3@naegler.net>
*  (c) 2009 Nathan Brock (http://github.com/Fenric/ProwlPHP)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/***************************************************************
*  This class is a fork of ProwlPHP, a PHP Prowl API class
*  written by Nathan Brock. Many thanks to Nathan for this
*  class and his permission to publish it with this extension.
*  http://github.com/Fenric/ProwlPHP
***************************************************************/

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   64: class tx_prowl_api extends t3lib_svbase
 *  104:     public function init()
 *  137:     protected function verify($apikey, $provkey)
 *  154:     public function sendProwlMessage($message, $priority = null, $application = '', $event = '', $apiKey = '', $providerKey = '', $is_post = false)
 *  221:     public function getError($code=null)
 *  243:     public function getRemainingApiCalls()
 *  256:     public function getRamainingApiCallsResetDate()
 *  271:     protected function _execute($url, $is_post=false, $params=null)
 *  301:     protected function _response($return)
 *  329:     protected function _setProxy($proxy, $userpwd=null)
 *
 * TOTAL FUNCTIONS: 9
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(PATH_t3lib.'class.t3lib_svbase.php');


/**
 * Service "Prowl" for the "prowl" extension.
 *
 * @author	Frank Nägler <mail@naegler.net>
 * @package	TYPO3
 * @subpackage	tx_prowl
 */
class tx_prowl_api extends t3lib_svbase {
	const PRIORITY_VERY_LOW		= -2;
	const PRIORITY_MODERATE		= -1;
	const PRIORITY_NORMAL		= 0;
	const PRIORITY_HIGH			= 1;
	const PRIORITY_EMERGENCY	= 2;

	public $prefixId = 'tx_prowl_sv1';		// Same as class name
	public $scriptRelPath = 'api/class.tx_prowl_api.php';	// Path to this script relative to the extension dir.
	public $extKey = 'prowl';	// The extension key.

	protected $_obj_curl = null;
	protected $_return_code;
	protected $_remaining;
	protected $_resetdate;

	protected $_use_proxy = false;
	protected $_proxy = null;
	protected $_proxy_userpwd = null;

	protected $_api_key = null;
	protected $_prov_key = null;
	protected $_api_domain = 'https://prowl.weks.net/publicapi/';
	protected $_url_verify = 'verify?apikey=%s&providerkey=%s';
	protected $_url_push = 'add';

	protected $_params = array(			// Accessible params [key => maxsize]
		'apikey' 		=> 		204,		// User API Key.
		'providerkey' 	=>		40,		// Provider key.
		'priority' 		=> 		2,		// Range from -2 to 2.
		'application' 	=> 		254,	// Name of the app.
		'event' 		=> 		1024,	// Name of the event.
		'description' 	=> 		10000,	// Description of the event.
	);

	/**
	 * initialize this service and return the availability of the service
	 *
	 * @return	[type]		...
	 */
	public function init()	{
		$available = parent::init();

		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['prowl']);
		
		switch ($this->configuration['defaultPriority']) {
			case 'very_low':
				$this->configuration['defaultPriority'] = -2;
			break;
			case 'moderate':
				$this->configuration['defaultPriority'] = -1;
			break;
			case 'high':
				$this->configuration['defaultPriority'] = 1;
			break;
			case 'emergency':
				$this->configuration['defaultPriority'] = 2;
			break;
			case 'normal':
			default:
				$this->configuration['defaultPriority'] = 0;
			break;
		}

		$curl_info = curl_version();	// Checks for cURL function and SSL version. Thanks Adrian Rollett!
		if(!function_exists('curl_exec') || empty($curl_info['ssl_version'])) {
			die($this->getError(10000));
		}

		if($this->configuration['useProxy']) {
			$this->_setProxy($this->configuration['proxy'], $this->configuration['proxyUsername'].':'.$this->configuration['proxyPassword']);
		}
		
		if(strlen($this->configuration['apiKey']) && $this->configuration['verify']) {
			if (!$this->verify($this->configuration['apiKey'], $this->configuration['providerKey'])) {
				// @TODO: change this, use exceptions...
				die('prowl verify failed');
			}
		}

		$this->_api_key 	= $this->configuration['apiKey'];
		$this->_prov_key	= $this->configuration['providerKey'];

		return $available;
	}

	/**
	 * this method verify the given api key and provider key
	 *
	 * @param	String		$apikey: the prowl API key
	 * @param	String		$provkey: the prowl provider key
	 * @return	Boolean		true or false
	 */
	protected function verify($apikey, $provkey) {
		$return = $this->_execute(sprintf($this->_url_verify, $apikey, $provkey));
		return $this->_response($return);
	}

	/**
	 * this method sends the message...
	 *
	 * @param	String		$message: the message to send
	 * @param	Integer		$priority: -2,-1,0,1,2 use class constants!
	 * @param	String		$application: application name
	 * @param	String		$event: event name
	 * @param	String		$apiKey: the api key
	 * @param	String		$providerKey: the provider key
	 * @param	String		$is_post: use POST or GET?
	 * @return	Boolesn		the result of the request
	 */
	public function sendProwlMessage($message, $priority = null, $application = '', $event = '', $apiKey = '', $providerKey = '', $is_post = false) {
		if($is_post) {
			$post_params = '';
		}

		$url = $is_post ? $this->_url_push : $this->_url_push . '?';

		$params = array(
			'apikey' 		=> 		$this->_api_key,
			'providerkey' 	=>		$this->_prov_key,
			'priority' 		=> 		$this->configuration['defaultPriority'],
			'application' 	=> 		$this->configuration['application'],
			'event' 		=> 		$this->configuration['event'],
			'description' 	=> 		$message,
		);

		if ($priority !== null && in_array($priority, array(-2,-1,0,1,2))) {
			$params['priority'] = $priority;
		}

		if (strlen($application) > 0) {
			$params['application'] = $application;
		}

		if (strlen($event) > 0) {
			$params['event'] = $event;
		}

		if (strlen($apiKey) > 0) {
			$params['apikey'] = $apiKey;
		}

		if (strlen($providerKey) > 0) {
			$params['providerkey'] = $providerKey;
		}

		foreach($params as $k => $v) {
			$v = str_replace("\\n","\n",$v);	// Fixes line break issue! Cheers Fr3d!
			if (strlen($v) > $this->_params[$k]) {
				$this->_return_code = 10001;
				return false;
			}

			if($is_post) {
				$post_params .= $k . '=' . urlencode($v) . '&';
			} else {
				$url .= $k . '=' . urlencode($v) . '&';
			}
		}

		if ($is_post) {
			$params = substr($post_params, 0, strlen($post_params)-1);
		} else {
			$url = substr($url, 0, strlen($url)-1);
		}

		$return = $this->_execute($url, $is_post ? true : false, $params);

		return $this->_response($return);
	}

	/**
	 * public methode to recieve error messages
	 *
	 * @param	Integer		$code: the error code
	 * @return	Mixed		String or Boolean if error code not defined
	 */
	public function getError($code=null) {
		$code = (empty($code)) ? $this->_return_code : $code;
		switch($code) {
			case 200: 	return 'Request Successful.';	break;
			case 400:	return 'Bad request, the parameters you provided did not validate.';	break;
			case 401: 	return 'The API key given is not valid, and does not correspond to a user.';	break;
			case 405:	return 'Method not allowed, you attempted to use a non-SSL connection to Prowl.';	break;
			case 406:	return 'Your IP address has exceeded the API limit.';	break;
			case 500:	return 'Internal server error, something failed to execute properly on the Prowl side.';	break;
			case 10000:	return 'cURL library missing vital functions or does not support SSL. cURL w/SSL is required to execute ProwlPHP.';	break;
			case 10001:	return 'Parameter value exceeds the maximum byte size.';	break;
			default:	return false;	break;
		}
	}

	/**
	 * method to get the remaining API calls, there is
	 * a limit to 1000 calls per hour. this method returns
	 * only the a number if a API call was made before.
	 *
	 * @return	Mixed		Interger, the number of remaining calls or false
	 */
	public function getRemainingApiCalls() {
		if(!isset($this->_remaining)) {
			return false;
		}
		return $this->_remaining;
	}

	/**
	 * method to get the remaining API calls reset date. this method returns
	 * only the a number if a API call was made before.
	 *
	 * @return	Mixed		Interger, the number of remaining calls or false
	 */
	public function getRamainingApiCallsResetDate() {
		if(!isset($this->_resetdate)) {
			return false;
		}
		return $this->_resetdate;
	}

	/**
	 * methode to execute the API call
	 *
	 * @param	String		$url: API URL
	 * @param	Boolean		$is_post: use POST or GET?
	 * @param	array		$params: an array with the needed fields
	 * @return	String		returns the response, should be XML string
	 */
	protected function _execute($url, $is_post=false, $params=null) {
		$this->_obj_curl = curl_init($this->_api_domain . $url);
		curl_setopt($this->_obj_curl, CURLOPT_HEADER, 0);
		curl_setopt($this->_obj_curl, CURLOPT_USERAGENT, "ProwlPHP/" . $this->_version);
		curl_setopt($this->_obj_curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($this->_obj_curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->_obj_curl, CURLOPT_RETURNTRANSFER, 1);

		if($is_post) {
			curl_setopt($this->_obj_curl, CURLOPT_POST, 1);
			curl_setopt($this->_obj_curl, CURLOPT_POSTFIELDS, $params);
		}

		if($this->_use_proxy) {
			curl_setopt($this->_obj_curl, CURLOPT_HTTPPROXYTUNNEL, 1);
			curl_setopt($this->_obj_curl, CURLOPT_PROXY, $this->_proxy);
			curl_setopt($this->_obj_curl, CURLOPT_PROXYUSERPWD, $this->_proxy_userpwd);
		}

		$return = curl_exec($this->_obj_curl);
		curl_close($this->_obj_curl);
		return $return;
	}

	/**
	 * thie methode parse the API call result and returns true or false
	 *
	 * @param	String		$return: The returned XML string
	 * @return	Boolean		returns true or false
	 */
	protected function _response($return) {
		if ($return === false) {
			$this->_return_code = 500;
			return false;
		}

		$response = new SimpleXMLElement($return);

		if(isset($response->success)) {
			$this->_return_code = (int) $response->success['code'];
			$this->_remaining = (int) $response->success['remaining'];
			$this->_resetdate = (int) $response->success['resetdate'];
		} else {
			$this->_return_code = $response->error['code'];
		}

		unset($response);

		return ($this->_return_code == 200) ? true : false;
	}

	/**
	 * this method sets the proxy data.
	 *
	 * @param	String		$proxy: The proxy hostname
	 * @param	String		$userpwd: The username/password string. format: username:password
	 * @return	void
	 */
	protected function _setProxy($proxy, $userpwd=null) {
		if(strlen($proxy) > 0) {
			$this->_use_proxy = true;
			$this->_proxy = $proxy;
			$this->_proxy_userpwd = $userpwd;
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/prowl/sv1/class.tx_prowl_api.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/prowl/sv1/class.tx_prowl_api.php']);
}

?>