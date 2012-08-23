<?php

$base = realpath(dirname(__FILE__) . '/..');
require "$base/lib/TopsyApi.php";

/**
 * Test class for TopsyApi
 */
class TopsyApiTest extends PHPUnit_Framework_TestCase {

	const API_KEY = 'YOUR_API_KEY_HERE';
	const HOST = 'YOUR_HOST_NAME.com';

	public function testConstructor() {
		$topsy = new TopsyApi(self::API_KEY, self::HOST);
		$this->assertStringStartsWith(self::HOST, $topsy->curl_opts[CURLOPT_USERAGENT]);
	}

	public function test_authorinfo() {
		$topsy = new TopsyApi(self::API_KEY, self::HOST);
		$result = $topsy->api('authorinfo', array('url' => 'http://twitter.com/thesmart'));
		$this->assertNotEmpty($result);
		$this->assertEquals('John Smart', $result['name']);
	}

	public function test_invalid() {
		$this->setExpectedException('TopsyApiException', '', 400);

		$topsy = new TopsyApi(self::API_KEY, self::HOST);
		$result = $topsy->api('foobar');

		$this->assertFalse(true, 'TopsyApi should have thrown TopsyApiException');
	}

	public function testRateLimit() {
		$topsy = new TopsyApi(self::API_KEY, self::HOST);
		$this->assertNull($topsy->getLastLimit());

		try {
			$topsy->api('foobar');
		} catch (TopsyApiException $tae) {
			// ignore
		}

		$limit = $topsy->getLastLimit();
		$this->assertNotNull($limit);

		try {
			$topsy->api('foobar');
		} catch (TopsyApiException $tae) {
			// ignore
		}

		$this->assertEquals($limit - 1, $topsy->getLastLimit(), 'last limits do not match');
	}
}
