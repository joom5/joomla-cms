<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.log
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Joomla! System Logging Plugin
 *
 * @package     Joomla.Plugin
 * @subpackage  System.log
 */
class plgSystemLog extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		JLog::addLogger(array());
	}

	public function onUserLoginFailure($response)
	{
		$errorlog = array();

		switch($response['status'])
		{
			case JAuthentication::STATUS_SUCCESS:
				$errorlog['status']  = $response['type'] . " CANCELED: ";
				$errorlog['comment'] = $response['error_message'];
				break;

			case JAuthentication::STATUS_FAILURE:
				$errorlog['status']  = $response['type'] . " FAILURE: ";
				if ($this->params->get('log_username', 0))
				{
					$errorlog['comment'] = $response['error_message'] . ' ("' . $response['username'] . '")';
				}
				else
				{
					$errorlog['comment'] = $response['error_message'];
				}
				break;

			default:
				$errorlog['status']  = $response['type'] . " UNKNOWN ERROR: ";
				$errorlog['comment'] = $response['error_message'];
				break;
		}
		JLog::add($errorlog['comment'], JLog::INFO, $errorlog['status']);
	}
}
