<?php
/**
 * Mailing Lists Facility
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2003-2004 (c) Guillaume Smet - Open Wide
 * Copyright 2010 (c) Franck Villaume - Capgemini
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012, Jean-Christophe Masson - French National Education Department
 * Copyright 2016-2025, SimTK Team
 * http://fusionforge.org/
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'mail/mail_utils.php';

require_once $gfcommon.'mail/MailingList.class.php';
require_once $gfcommon.'mail/MailingListFactory.class.php';

global $HTML;
$group_id = getIntFromGet('group_id');

// Check permission and prompt for login if needed.
session_require_perm('project_read', $group_id);

if ($group_id) {
	$group = group_get_object($group_id);
	if (!$group || !is_object($group)) {
		exit_no_group();
	} elseif ($group->isError()) {
		exit_error($group->getErrorMessage(),'mail');
	}

	$mlFactory = new MailingListFactory($group);
	if (!$mlFactory || !is_object($mlFactory)) {
		exit_error(_('Could Not Get MailingListFactory'),'mail');
	} elseif ($mlFactory->isError()) {
		exit_error($mlFactory->getErrorMessage(),'mail');
	}

	mail_header(array(
		'title' => sprintf(_('Mailing Lists'))
	));

	if (session_loggedin()) {
		if (forge_check_perm ('project_admin', $group_id)) {
			echo " <a href='/mail/admin/?group_id=$group_id' class='btn-blue share_text_button'>Administration</a>";
		}
	}

	plugin_hook ("blocks", "mail index");

	$mlArray = $mlFactory->getMailingLists();

	if ($mlFactory->isError()) {
		echo $HTML->error_msg(sprintf('Unable to get the list %s: %s',
			$group->getPublicName(), 
			$mlFactory->getErrorMessage()));
		mail_footer();
		exit;
	}

	if (session_loggedin()) {
		$action = false;
		if (getStringFromRequest('submit') == "Subscribe") {
			$action = "Subscribe";
		}
		else if (getStringFromRequest('submit') == "Unsubscribe") {
			$action = "Unsubscribe";
		}
		$listID = getIntFromRequest('list_id');
		if ($listID !== 0 && $action !== false) {
			// Get user.
			$theUser = session_get_user();
			$userName = escapeshellcmd($theUser->getUnixName());
			$userEmail = escapeshellcmd($theUser->getEmail());

			// Handle un/subscription action.
			$listName = false;
			for ($j = 0; $j < count($mlArray); $j++) {
				$currentList =& $mlArray[$j];
				if (!$currentList->isPermissionDeniedError() && 
					!$currentList->isError() &&
					$currentList->getStatus() != MAIL__MAILING_LIST_IS_REQUESTED) {
					if ($currentList->getID() == $listID) {
						// Found list.
						$listName = $currentList->getName();
						break;
					}
				}
			}
			if ($listName !== false) {
				if ($action == "Subscribe") {
					addMemberToMailingList($listName, 
						$userName, 
						$userEmail,
						isDigestEnabled($listName));
				}
				else if ($action == "Unsubscribe") {
					$res = removeMemberFromMailingList($listName, $userEmail);
					if ($res !== true && $res !== false) {
						$error_msg = $res;
						echo $HTML->error_msg($res);
					}
				}
			}
		}
	}

	if (session_loggedin()) {
		// User logged in.
		$tableHeaders = array(
			'Mailing List',
			'Address',
			'Description',
			'Subscription'
		);
	}
	else {
		$tableHeaders = array(
			'Mailing List',
			'Address',
			'Description',
		);
	}

	$cnt = 0;
	$hasDenied = false;
	for ($j = 0; $j < count($mlArray); $j++) {
		$currentList =& $mlArray[$j];
		if (!$currentList->isPermissionDeniedError()) {
			if ($currentList->isError()) {
				/*
				echo '<td colspan="4">'.$currentList->getErrorMessage().'</td>';
				*/
			}
			elseif ($currentList->getStatus() == MAIL__MAILING_LIST_IS_REQUESTED) {
				/*
				echo '<td class="halfwidth" colspan="2"><strong>'.$currentList->getName().'</strong></td>'.
					'<td width="25%">'.htmlspecialchars($currentList->getDescription()). '</td>'.
					'<td width="25%" class="align-center">'._('Not activated yet').'</td>';
				*/
			}
			else {
				if ($cnt == 0) {
					echo '<p>Choose a list to browse, search, and post messages.</p>';

					// Has non-empty table: first item.
					// Start header.
					echo $HTML->listTableTop($tableHeaders);
				}
				echo '<tr '. $HTML->boxGetAltRowStyle($cnt++) .'>';
				echo '<td width="25%">'. 
					'<strong><a href="'.$currentList->getArchivesUrl().'" target="_blank">' .
					sprintf(_('%s Archives'), $currentList->getName()).'</a></strong></td>'.
					'<td width="25%" align="center"><a href="&#109;&#097;&#105;&#108;&#116;&#111;:'.$currentList->getListEmail().'">'.$currentList->getListEmail(). '</a></td>'.
					'<td width="25%">'.htmlspecialchars($currentList->getDescription()). '</td>';

				if (session_loggedin()) {
					// Get user.
					$theUser = session_get_user();
					$userName = escapeshellcmd($theUser->getUnixName());
					$userEmail = escapeshellcmd($theUser->getEmail());
					$subscribed = isMemberOfMailingList($currentList->getName(), 
							$userEmail);
					// Action.
					if ($subscribed) {
						$action = "Unsubscribe";
					}
					else {
						$action = "Subscribe";
					}
					// Form.
					echo "<td width='25%' class='align-center'>";
					echo "<form id='mySubmit' action='" . 
						util_make_url('/mail/index.php?group_id=' . $group_id) . "' " .
						"method='post' " .
						"enctype='multipart/form-data'>";
					echo "<input type='hidden' name='list_id' " .
						"value='" . $currentList->getID() . "'/>";
					echo "<input type='submit' name='submit' " .
						"value='" . $action . "' " .
						"class='btn-blue share_text_button'/>";
					echo "</form>";
					echo "</td>";
				}

				echo '</tr>';
			}
		}
		else {
			$hasDenied = true;
		}
	}

	if ($cnt > 0) {
		echo $HTML->listTableBottom();
	}
	else {
		if ($hasDenied) {
			echo "<br/><br/>Permission is required to access mailing lists.";
		}
		else {
			echo "<br/><br/>Mailing lists have been deprecated. " .
                                "If you have questions, please contact the " .
                                "<a href='/sendmessage.php?recipient=admin&groupname=" .
                                $group->getUnixName() .
                                "'>SimTK WebMaster</a>.";
		}
	}

	mail_footer();
}
else {
	exit_no_group();
}
