<?php namespace Torann\GeoIP;

use GeoIp2\Database\Reader;

use Cookie;
use Config;

class GeoIP {

	/**
	 * Remote Machine IP address.
	 *
	 * @var float
	 */
	protected $remote_ip = null;

	/**
	 * Reserved IP address.
	 *
	 * @var array
	 */
	protected $reserved_ips = array (
		array('0.0.0.0','2.255.255.255'),
		array('10.0.0.0','10.255.255.255'),
		array('127.0.0.0','127.255.255.255'),
		array('169.254.0.0','169.254.255.255'),
		array('172.16.0.0','172.31.255.255'),
		array('192.0.2.0','192.0.2.255'),
		array('192.168.0.0','192.168.255.255'),
		array('255.255.255.0','255.255.255.255')
	);

	/**
	 * Default Location data.
	 *
	 * @var array
	 */
	protected $default_location = array (
		"ip" 			=> "127.0.0.0",
		"isoCode" 		=> "US",
		"country" 		=> "United States",
		"city" 			=> "New Haven",
		"state" 		=> "CT",
		"postal_code" 	=> "06510",
		"lat" 			=> 41.28,
		"lon" 			=> -72.88
	);

	/**
	 * Location data.
	 *
	 * @var array
	 */
	protected $location = null;

	/**
	 * Create a new instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->remote_ip = $this->default_location['ip'] = $_SERVER["REMOTE_ADDR"];
	}

	/**
	 * Save location data in a cookie.
	 *
	 * @return void
	 */
	function saveLocation() {
		Cookie::make('geoip-location', $this->location, 10080);
	}

	/**
	 * Get location from IP.
	 *
	 * @param  string $ip Optional
	 * @return array
	 */
	function getLocation( $ip = null ) {
		// Get location data
		$this->location = $this->find( $ip );

		// Save user's location
		if( $ip === null ) {
			$this->saveLocation();
		}

		return $this->location;
	}

	private function find( $ip = null ) {
		// Check cookies
		if ( $ip === null && $position = Cookie::get('geoip-location') ) {
			if($position['ip'] === $this->remote_ip) {
				return $position;
			}
		}

		// If IP not set, user remote IP
		if ( $ip === null ) {
			$ip = $this->remote_ip;
		}

		// Check if the ip is not local or empty
		if( $this->checkIp( $ip ) ) {

			// Call default service
			$service = 'locate_'.Config::get('geoip::service');
			return $this->$service($ip);
		}

		return $this->default_location;
	}

	// Maxmind
	private function locate_maxmind( $ip ) {

		$settings = Config::get('geoip::maxmind');

		if($settings['type'] === 'web_service') {
			$maxmind = new Client($settings['user_id'], $settings['license_key']);
		}
		else {
			$maxmind = new Reader(dirname(__FILE__).'/app/database/maxmind/GeoIP2-City.mmdb');
		}

		$record = $maxmind->city($ip);

		$location = array(
			"ip"			=> $ip,
			"isoCode" 		=> $record->country->isoCode,
			"country" 		=> $record->country->name,
			"city" 			=> $record->city->name,
			"state" 		=> $record->mostSpecificSubdivision->isoCode,
			"postal_code" 	=> $record->postal->code,
			"lat" 			=> $record->location->latitude,
			"lon" 			=> $record->location->longitude
		);

		unset($record);

		return $location;
	}

	//Checks if the ip is not local or empty
	private function checkIp( $ip ) {
		$longip = ip2long($ip);

		if ( !empty($ip) ) {

			foreach ($this->reserved_ips as $r) {
				$min = ip2long($r[0]);
				$max = ip2long($r[1]);

				if ($longip >= $min && $longip <= $max) {
					return false;
				}
			}
			return true;
		}

		return false;
	}

}
