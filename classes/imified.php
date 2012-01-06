<?php defined('SYSPATH') or die('No direct script access.');

class IMified
{
	const ENDPOINT = 'https://www.imified.com/api/bot/';

	public static $instance;

	/**
	 * Returns an instance of the IMified class.
	 *
	 * @return  IMified
	 */
	public static function instance()
	{
		if ( ! isset(self::$instance))
		{
			$config = Kohana::$config->load('imified');

			self::$instance = new IMified($config);
		}

		return self::$instance;
	}

	/**
	 * @var  Config
	 */
	protected $_config = array();

	/**
	 * Ensures singleton pattern is observed, loads the default config
	 *
	 * @param  array     configuration
	 */
	protected function __construct($config = array())
	{
		$this->config($config->as_array());
	}

	/**
	 * Getter and setter for the configuration. If no argument provided, the
	 * current configuration is returned. Otherwise the configuration is set
	 * to this class.
	 *
	 * @param   mixed    key to set to array, either array or config path
	 * @param   mixed    value to associate with key
	 * @return  mixed
	 */
	public function config($key = NULL, $value = NULL)
	{
		if ($key === NULL)
			return $this->_config;

		if (is_array($key))
		{
			$this->_config = $key;
		}
		else
		{
			if ($value === NULL)
				return Arr::get($this->_config, $key);

			$this->_config[$key] = $value;
		}

		return $this;
	}

	/**
	 * Makes an API request and returns the response.
	 *
	 * @param   string  method to call
	 * @param   array   query parameters
	 * @return  array
	 */
	protected function _request($method, array $params = array())
	{
		$params = Arr::merge($params, array(
			'apimethod' => $method,
			'botkey'    => $this->config('botkey')
			));

		try
		{
			// Make an API request
			$request = Request::factory(self::ENDPOINT)
				->method('POST')
				->post($params);

			$request->client()->options(array(
				CURLOPT_USERPWD => $this->config('username').':'.$this->config('password')
			));

			$response = $request
				->execute()
				->body();
		}
		catch (Kohana_Exception $e)
		{
			throw new IMified_Exception('API :method request failed, API may be offline',
				array(':method' => $method));
		}

		return new SimpleXMLElement($response);
	}

	/**
	 * Pushes a message to a bot user or list of users.
	 *
	 * @param   string            The message you'd like to send
	 * @param   mixed             Userkey, comma-separated list of userkeys, or user/network combination array
	 * @return  SimpleXMLElement
	 */
	public function send_message($message, $recipient = NULL)
	{
		$params = array('msg' => $message);

		if ( ! $recipient)
		{
			// No recipient passed, use config
			$recipient = $this->config('recipient');
		}

		if (is_array($recipient))
		{
			// We have a user/network combination
			$params = Arr::merge($params, $recipient);
		}
		else
		{
			// We have a userkey
			$params['userkey'] = $recipient;
		}

		return $this->_request('send', $params);
	}

	/**
	 * Receive the user details for all users of a bot as well as a user count.
	 *
	 * @param   string            Return users from a specified network (Jabber, AIM, MSN, Yahoo, Gtalk, Twitter or SMS)
	 * @return  SimpleXMLElement
	 */
	public function get_all_users($network = NULL)
	{
		$params = array();

		if ($network)
		{
			$params['network'] = $network;
		}

		return $this->_request('getAllUsers', $params);
	}

	/**
	 * Sends a message to all users.
	 *
	 * @param   string            Message
	 * @return  SimpleXMLElement
	 */
	public function send_message_to_all_users($message)
	{
		$response = $this->get_all_users();

		$keys = array();
		foreach ($response->users->children() as $child)
		{
			$keys[] = $child->userkey;
		}

		$this->send_message($message, implode(',', $keys));
	}
}
