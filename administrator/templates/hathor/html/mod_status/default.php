<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Template.hathor
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$hideLinks	= JRequest::getBool('hidemainmenu');
$task = JRequest::getCmd('task');
$output = array();

// Print the logged in users.
if ($params->get('show_loggedin_users', 1)) :
	$output[] = '<span class="loggedin-users">'.JText::plural('MOD_STATUS_USERS', $online_num).'</span>';
endif;

// Print the back-end logged in users.
if ($params->get('show_loggedin_users_admin', 1)) :
	$output[] = '<span class="backloggedin-users">'.JText::plural('MOD_STATUS_BACKEND_USERS', $count).'</span>';
endif;

//  Print the inbox message.
if ($params->get('show_messages', 1)) :
	$output[] = '<span class="'.$inboxClass.'">'.
	($hideLinks ? '' : '<a href="'.$inboxLink.'">').
	JText::plural('MOD_STATUS_MESSAGES', $unread).
	($hideLinks ? '' : '</a>').
	'</span>';
endif;

// Print the Preview link to Main site.
$output[] = '<span class="viewsite"><a href="' . JURI::root() . '" target="_blank">' . JText::_('JGLOBAL_VIEW_SITE') . '</a></span>';

// Print the logout link.
if ($task == 'edit' || $task == 'editA' || $hideLinks) {
	$logoutLink = '';
} else {
	$logoutLink = JRoute::_('index.php?option=com_login&task=logout&'. JSession::getFormToken() .'=1');
}
if ($params->get('show_logout', 1)) :
	$output[] = '<span class="logout">' . ($hideLinks ? '' : '<a href="' . $logoutLink.'">') . JText::_('JLOGOUT') . ($hideLinks ? '' : '</a>') . '</span>';
endif;

// Output the items.
foreach ($output as $item) :
	echo $item;
endforeach;
