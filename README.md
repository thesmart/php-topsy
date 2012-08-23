php-topsy
=======

A php rest client for Topsy's Otter API with support for rate limiting and error handling.

Brought to you by the fine developers @ [Telly.com](http://telly.com/)

Usage
-----

Usage is very simple, similar to Facebook's SDK:

```php
$topsy = new TopsyApi('my api key', 'example.com');
try {
	$result = $topsy->api('authorinfo', array('url' => 'http://twitter.com/thesmart'));
} catch (TopsyApiException $tae) {
	error_log('Topsy error message: ' . $tae->getMessage());
	error_log('Topsy status code: ' . $tae->getCode());
}
```

Prints this:

```php
array (
	'name' => 'John Smart',
	'nick' => 'thesmart',
	'description' => 'Chief Architect and CoFounder of Zoosk.com. Geeky and profound, enjoying the ride.',
	'influence_level' => 0,
	'url' => 'http://twitter.com/thesmart',
	'image_url' => 'http://a0.twimg.com/profile_images/1508630405/Screen_shot_2011-02-23_at_3.10.25_PM.png_normal.jpg',
	'type' => 'twitter',
	'topsy_author_url' => 'http://topsy.com/twitter/thesmart',
);
```

