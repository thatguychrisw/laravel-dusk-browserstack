<?php
/**
 * Copyright (c) 2017 International Association of Certified Home Inspectors.
 */

namespace Galahad\BrowserStack;

use BrowserStack\Local;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use RuntimeException;

/**
 * Run BrowserStack from your tests.
 */
trait SupportsBrowserStack
{
	/**
	 * The BrowserStack process instance.
	 *
	 * @var Local
	 */
	protected static $browserStackProcess;
	
	/**
	 * The BrowserStack username.
	 *
	 * @var string
	 */
	protected $browserStackUsername;
	
	/**
	 * The API key for BrowserStack.
	 *
	 * @var string
	 */
	protected $browserStackKey;
	
	/**
	 * Extra configuration for BrowserStack local.
	 *
	 * @var array
	 */
	protected $browserStackLocalConfig = [];
	
	/**
	 * Capabilities requested for tests.
	 *
	 * @var array
	 */
	protected $browserStackCapabilities = [];
	
	/**
	 * Whether the services.browserstack config settings were loaded yet.
	 *
	 * @var bool
	 */
	protected $browserStackLoadedConfig = false;
	
	/**
	 * Whether BrowserStack Local is running in a separate process.
	 *
	 * @var bool
	 */
	protected $browserStackRunningExternally;
	
	/**
	 * Stop the BrowserStack process.
	 *
	 * @return void
	 */
	public static function stopBrowserStack()
	{
		if (static::$browserStackProcess && static::$browserStackProcess->isRunning()) {
			static::$browserStackProcess->stop();
		}
	}
	
	/**
	 * Create the BrowserStack WebDriver instance.
	 *
	 * @param array $config
	 * @return RemoteWebDriver
	 */
	public function createBrowserStackDriver(array $config = null) : RemoteWebDriver
	{
		$this->loadApplicationConfig();
		
		if (null !== $config) {
			$this->setBrowserStackConfig($config);
		}
		
		$this->startBrowserStack();
		
		return RemoteWebDriver::create(
			$this->browserStackSeleniumUrl(),
			$this->browserStackSeleniumCaps()
		);
	}
	
	/**
	 * Set the configuration for BrowserStack.
	 *
	 * @param array $config
	 * @return $this
	 */
	public function setBrowserStackConfig(array $config)
	{
		$shortcuts = [
			'username' => 'setBrowserStackUsername',
			'key' => 'setBrowserStackKey',
			'api_key' => 'setBrowserStackKey',
			'local_config' => 'setBrowserStackLocalConfig',
			'capabilities' => 'setBrowserStackCapabilities',
		];
		
		foreach ($config as $key => $value) {
			if (!isset($shortcuts[$key])) {
				throw new \InvalidArgumentException("Unknown BrowserStack configuration: '$key'");
			}
			
			$method = $shortcuts[$key];
			$this->$method($value);
		}
		
		return $this;
	}
	
	/**
	 * Set the BrowserStack username.
	 *
	 * @param string $username
	 * @return $this
	 */
	public function setBrowserStackUsername(string $username)
	{
		$this->browserStackUsername = $username;
		
		return $this;
	}
	
	/**
	 * Set the BrowserStack API key.
	 *
	 * @param string $key
	 * @return $this
	 */
	public function setBrowserStackKey(string $key)
	{
		$this->browserStackKey = $key;
		
		return $this;
	}
	
	/**
	 * Set the BrowserStackLocal configuration options.
	 *
	 * @param array $config
	 * @return $this
	 */
	public function setBrowserStackLocalConfig(array $config)
	{
		$this->browserStackLocalConfig = $config;
		
		return $this;
	}
	
	/**
	 * Set the requested WebDriver capabilities.
	 *
	 * @param array $capabilities
	 * @return $this
	 */
	public function setBrowserStackCapabilities(array $capabilities)
	{
		$this->browserStackCapabilities = $capabilities;
		
		return $this;
	}
	
	/**
	 * Load configuration from application's config/ dir one time.
	 */
	protected function loadApplicationConfig()
	{
		if ($this->browserStackLoadedConfig) {
			return;
		}
		
		$this->browserStackLoadedConfig = true;
		
		$config = $this->app['config']->get('services.browserstack', []);
		$this->setBrowserStackConfig($config);
	}
	
	/**
	 * Start the BrowserStack process.
	 */
	protected function startBrowserStack()
	{
		if ($this->isBrowserStackRunning()) {
			return;
		}
		
		if (!static::$browserStackProcess) {
			static::$browserStackProcess = new Local();
		}
		
		if (!static::$browserStackProcess->isRunning()) {
			$this->startBrowserStackProcess();
			
			static::afterClass(function() {
				static::stopBrowserStack();
			});
		}
	}
	
	/**
	 * Build the process to run the BrowserStack.
	 */
	protected function startBrowserStackProcess()
	{
		$defaults = [
			'key' => $this->browserStackKey,
			'logfile' => base_path('tests/Browser/console/browserstack.log'),
		];
		
		$config = array_merge($defaults, $this->browserStackLocalConfig);
		
		if (empty($config['key']) && !env('BROWSERSTACK_ACCESS_KEY')) {
			throw new RuntimeException('A BrowserStack API key must be configured.');
		}
		
		static::$browserStackProcess->start($config);
	}
	
	/**
	 * Check if a BrowserStack Local is running in a separate process.
	 */
	protected function isBrowserStackRunning()
	{
		if (null === $this->browserStackRunningExternally) {
			$this->browserStackRunningExternally = false;
			if ($conn = @stream_socket_client('tcp://127.0.0.1:45691')) {
				$this->browserStackRunningExternally = true;
				fclose($conn);
			}
		}
		
		return $this->browserStackRunningExternally;
	}
	
	/**
	 * Build the BrowserStack Selenium URL.
	 *
	 * @return string
	 */
	protected function browserStackSeleniumUrl() : string
	{
		$username = $this->browserStackUsername;
		$key = $this->browserStackKey ?? env('BROWSERSTACK_ACCESS_KEY');
		
		if (empty($key) || empty($username)) {
			throw new RuntimeException('A BrowserStack API key & username must be configured.');
		}
		
		return "https://{$username}:{$key}@hub-cloud.browserstack.com/wd/hub";
	}
	
	/**
	 * Build the BrowserStack Selenium capabilities.
	 *
	 * @return array
	 */
	protected function browserStackSeleniumCaps() : array
	{
		$defaults = [
			'browserstack.local' => 'true',
		];
		
		return array_merge(
			$defaults,
			DesiredCapabilities::chrome()->toArray(),
			$this->browserStackCapabilities
		);
	}
}
