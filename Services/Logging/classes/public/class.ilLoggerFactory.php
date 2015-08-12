<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Logging/lib/vendor/autoload.php';
include_once './Services/Logging/classes/public/class.ilLogLevel.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;


/**
 * Logging factory 
 *
 * This class supplies an implementation for the locator.
 * The locator will send its output to ist own frame, enabling more flexibility in
 * the design of the desktop.
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * @version $Id$
 * 
 */
class ilLoggerFactory
{
	const DEFAULT_FORMAT  = "[%suid%] [%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
	
	const ROOT_LOGGER = 'root';
	
	private static $instance = null;
	
	private $settings = null;
	
	private $enabled = FALSE;
	private $loggers = array();
	
	protected function __construct()
	{
		$this->init();
	}

	/**
	 * 
	 * @return ilLoggerFactory
	 */
	public static function getInstance()
	{
		if(!static::$instance)
		{
			static::$instance = new ilLoggerFactory();
		}
		return static::$instance;
	}
	
	/**
	 * Get component logger
	 * @see mudules.xml or service.xml
	 * 
	 * @param string $a_component_id
	 * @return ilLogger
	 */
	public static function getLogger($a_component_id)
	{
		$factory = self::getInstance();
		return $factory->getComponentLogger($a_component_id);
	}
	
	/**
	 * The unique root logger has a fixed error level
	 * @return ilLogger
	 */
	public static function getRootLogger()
	{
		$factory = self::getInstance();
		return $factory->getComponentLogger(self::ROOT_LOGGER);
	}
	
	/**
	 * Init user specific log options
	 * @param type $a_login
	 * @return boolean
	 */
	public function initUser($a_login)
	{
		if(!$this->getSettings()->isBrowserLogEnabledForUser($a_login))
		{
			return TRUE;
		}
		
		foreach($this->loggers as $a_component_id => $logger)
		{
			$browser_handler = new BrowserConsoleHandler();
			$browser_handler->setLevel($this->getSettings()->getLevelByComponent($a_component_id));
			$browser_handler->setFormatter(new LineFormatter(static::DEFAULT_FORMAT, 'Y-m-d H:i:s.u',TRUE,TRUE));
			
			$logger->getLogger()->pushHandler($browser_handler);
		}
	}
	
	/**
	 * Get settigns
	 * @return ilLoggingSettings
	 */
	public function getSettings()
	{
		return $this->settings;
	}
	
	/**
	 * 
	 * @return ilComponentLogger[]
	 */
	protected function getLoggers()
	{
		return $this->loggers;
	}
	
	/**
	 * Init factory
	 */
	protected function init()
	{
		include_once './Services/Logging/classes/class.ilLoggingSettings.php';
		$this->settings = ilLoggingSettings::getInstance();
		$this->enabled = ILIAS_LOG_ENABLED;
	}
	
	/**
	 * Get component logger
	 * @param type $a_component_id
	 * @return \Logger
	 */
	protected function getComponentLogger($a_component_id)
	{
		if(isset($this->loggers[$a_component_id]))
		{
			return $this->loggers[$a_component_id];
		}
		
		switch($a_component_id)
		{
			case 'root':
				$logger = new Logger(CLIENT_ID.'_root');
				break;
				
			default:
				$logger = new Logger(CLIENT_ID.'_'.$a_component_id);
				break;
				
		}
		
		// standard stream handler
		$stream_handler = new StreamHandler(ILIAS_LOG_DIR.'/'.ILIAS_LOG_FILE,TRUE);
		$stream_handler->setLevel($this->getSettings()->getLevelByComponent($a_component_id));
		
		// format lines
		$line_formatter = new LineFormatter(static::DEFAULT_FORMAT, 'Y-m-d H:i:s.u',TRUE,TRUE);
		$stream_handler->setFormatter($line_formatter);
		
		if($this->getSettings()->isCacheEnabled())
		{
			// add new finger crossed handler
			$finger_crossed_handler = new FingersCrossedHandler(
					$stream_handler,
					new ErrorLevelActivationStrategy($this->getSettings()->getCacheLevel()),
					10
			);
			$logger->pushHandler($finger_crossed_handler);
		}
		else
		{
			$logger->pushHandler($stream_handler);
		}
		
		if($GLOBALS['ilUser'] instanceof ilObjUser)
		{
			if($this->getSettings()->isBrowserLogEnabledForUser($GLOBALS['ilUser']->getLogin()))
			{
				$browser_handler = new BrowserConsoleHandler();
				$browser_handler->setLevel($this->getSettings()->getLevelByComponent($a_component_id));
				$browser_handler->setFormatter($line_formatter);
				$logger->pushHandler($browser_handler);
			}
		}
		

		// suid log
		$logger->pushProcessor(function ($record) { 
			$record['suid'] = substr(session_id(),0,5);
			return $record;
		});

		// append trace 
		include_once './Services/Logging/classes/extensions/class.ilTraceProcessor.php';
		$logger->pushProcessor(new ilTraceProcessor(ilLogLevel::DEBUG));
		
				
		// register new logger
		include_once './Services/Logging/classes/class.ilComponentLogger.php';
		$this->loggers[$a_component_id] = new ilComponentLogger($logger);
		
		return $this->loggers[$a_component_id];
	}
	
	/**
	 * on destruct automatically write memory peak usage
	 */
	public function __destruct()
	{
		if($this->getSettings()->isMemoryUsageEnabled())
		{
			$this->getRootLogger()->writeMemoryPeakUsage(ilLogLevel::DEBUG);
		}
	}
}
?>