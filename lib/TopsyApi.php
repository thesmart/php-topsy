<?php

if (!function_exists('curl_init')) {
  throw new Exception('TopsyApi needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
  throw new Exception('TopsyApi needs the JSON PHP extension.');
}

/**
 * A php rest client for Topsy's Otter API with support for rate limiting and error handling
 * @link http://code.google.com/p/otterapi/
 * @link http://code.google.com/p/otterapi/wiki/Resources
 *
 * @author John Smart <smart@telly.com>
 */
class TopsyApi {

	/**
	 * Version.
	 */
	const VERSION = '0.1.0';

	/**
	 * The url for the otter api
	 */
	const OTTER_URL = 'http://otter.topsy.com/';

	/**
	 * Default options for curl.
	 */
	public $curl_opts = array(
		CURLOPT_CONNECTTIMEOUT	=> 10,
		CURLOPT_RETURNTRANSFER	=> true,
		CURLOPT_TIMEOUT			=> 60,
		CURLOPT_USERAGENT		=> 'topsy-php-0.1.0',
		CURLOPT_HEADER			=> 1,
		CURLOPT_HTTPHEADER		=> array(
			// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
			// for 2 seconds if the server does not support this header.
			'Expect:'
		)
	);

	/**
	 * Api key to use
	 * @var string
	 */
	protected $apiKey;

	/**
	 * The last known rate limit remaining
	 * @var int|null
	 */
	protected $lastLimit = null;

	/**
	 * Construct an API handler
	 * @param string $apiKey		Your API key
	 * @param string $hostName		Optional. Set to your application's host / domain name if you want more stability.
	 */
	public function __construct($apiKey, $hostName = '') {
		$this->apiKey	= $apiKey;
		if (!empty($hostName)) {
			$this->curl_opts[CURLOPT_USERAGENT]	= $hostName . ' ' . $this->curl_opts[CURLOPT_USERAGENT];
		}
	}

	/**
	 * Make an api call
	 * @param string $resource		The procedure name to call
	 * @param array $params			Optional. Parameters to send.
	 * @return array		The result data
	 * @throws TopsyApiException
	 */
	public function api($resource, array $params = array()) {
		$params['apikey']	= $this->apiKey;
		$url = self::OTTER_URL . $resource . '.json';
		$url .= '?' . self::queryToStr($params, true);

		$ch = curl_init($url);
		$opts = $this->curl_opts;
		curl_setopt_array($ch, $opts);
		$result = curl_exec($ch);

		curl_close($ch);

		if ($result === false) {
			// API returned non-200 response
			$ex = TopsyApiException::CreateFromCurl($ch);
			throw $ex;
		}

		// parse headers
		list($headers, $content) = explode("\r\n\r\n", $result, 2);
		$headers	= self::stringToHeader($headers);

		// detect rate limits
		$this->warnRateLimit($headers);

		$json = json_decode($content, true, 32);
		if ($json === false) {
			throw new TopsyApiException('Topsy response is invalid, unexpected', 500);
		} else if (!isset($json['response'])) {
			throw new TopsyApiException('Topsy response is empty', 500);
		} else if (isset($json['response']['errors'])) {
			throw new TopsyApiException(implode("\n", $json['response']['errors']), $json['response']['status']);
		}

		return $json['response'];
	}

	/**
	 * Get the last known rate limit
	 * NOTE: must have called api() at least once
	 * @return int|null
	 */
	public function getLastLimit() {
		return $this->lastLimit;
	}

	/**
	 * Log an error message
	 * @param string $msg
	 */
	protected function log($msg) {
		error_log($msg);
	}

	/**
	 * Detect if we're within x% of the rate limit
	 * @param array $headers
	 * @param float $warnLevel		Optional. The percentage at which we hit the warning. Default .2
	 * @return bool
	 */
	protected function warnRateLimit(array $headers, $warnLevel = .2) {
		if (!isset($headers['X-RateLimit-Limit'])) {
			return false;
		}

		// turn data to to ints
		foreach (array('X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset') as $key) {
			$headers[$key] = intval($headers[$key]);
		} unset($key);

		$this->lastLimit	= $headers['X-RateLimit-Remaining'];

		// warn when at 20% or below
		$warnLevel = $warnLevel * $headers['X-RateLimit-Limit'];

		if ($headers['X-RateLimit-Remaining'] < $warnLevel) {
			// close to rate limit

			$warnChance = intval(.05 * $warnLevel);
			$rand = mt_rand(0, $warnChance);
			if ($rand === $warnChance) {
				// only log 5% of the time
				$this->log(sprintf('WARNING: TopsyApi approaching rate limit of %s, %s calls remaining', $headers['X-RateLimit-Limit'], $headers['X-RateLimit-Remaining']));
			}
			return true;
		}

		return false;
	}

	/**
	 * Turns associative array into a query string
	 * @param array $query
	 * @param bool $sort		Set true to sort the query string by key
	 * @return string
	 */
	protected static function queryToStr(array $query, $sort = false) {
		$queryStr = array();
		foreach ($query as $key => $val) {
			$key	= rawurlencode($key);
			$val	= rawurlencode($val);
			$queryStr[$key] = $key . '=' . $val;
		} unset($val);

		if ($sort) {
			ksort($queryStr);
		}

		return implode('&', $queryStr);
	}

	/**
	 * Parse a header string to an associatvie array
	 * @static
	 * @param string $headerStr
	 * @return array
	 */
	protected static function stringToHeader($headerStr) {
		$headers	= array();

		$headerStr	= explode("\r\n", $headerStr);
		foreach ($headerStr as $header) {
			$header = explode(": ", $header, 2);
			if (count($header) === 2) {
				$headers[$header[0]]	= $header[1];
			}
		}

		return $headers;
	}
}

/**
 * An exception thrown when a call to the Topsy API results in an error
 */
class TopsyApiException extends Exception {
	protected static $ERROR_CODES = array(
		400 => 'Parameter check failed.', // This error indicates that a required parameter is missing or a parameter has a value that is out of bounds.
		403 => 'Forbidden. You don\'t have access to this action.',
		404 => 'Action not supported.', // This indicates you have requested a resource that does not exist.
		500 => 'Unexpected internal error.',
		503 => 'Temporarily unavailable.' // This is an important error condition and your app MUST handle it. A 503 is returned for two different reasons:
										// 1) The client has run out of its token allocation. See RateLimit section for details.
										// 2) The API is unavailable for scheduled or unscheduled downtime. If something is wrong on our end, we'll try our best to return a 503 error code so you know whatever it is will be fixed shortly.
	);
	/**
	 * Construct a TopsyApiException from a curl handle
	 * @static
	 * @param mixed $ch		A Curl Handle
	 * @return TopsyApiException
	 */
	public static function CreateFromCurl($ch) {
		$errorCode = curl_errno($ch);
		$errorMessage = curl_error($ch);
		if (empty($errorMessage) && isset(self::$ERROR_CODES[$errorCode])) {
			$errorMessage	= self::$ERROR_CODES[$errorCode];
		}

		return new TopsyApiException($errorMessage, $errorCode);
	}
}