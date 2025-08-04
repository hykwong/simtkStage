<?php
/**
 * index.php
 *
 * Mailing Lists Facility
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2003-2004 (c) Guillaume Smet - Open Wide
 * Copyright 2010 (c) Franck Villaume - Capgemini
 * Copyright (C) 2011-2012 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012-2014, Franck Villaume - TrivialDev
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

require_once '../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'mail/admin/../mail_utils.php';

require_once $gfcommon.'mail/MailingList.class.php';
require_once $gfcommon.'mail/MailingListFactory.class.php';
require_once $gfcommon.'include/mailinglist_utils.php';

global $HTML;
?>

<?php

// Get mailing lists host.
$mlHost = forge_get_config("lists_host");
if (!isset($mlHost)) {
	// Cannot get mailing lists credentials.
	return;
}

$group_id = getIntFromRequest('group_id');

if ($group_id == 0) {
	// group_id is not provided. Try to look it up if list_name is provided.
	$listName = getStringFromRequest('list_name');
	if (trim($listName) != "") {
		// list_name may contain uppercase. Convert to lowercase first.
		$listName = strtolower(trim($listName));
		$query  = "SELECT group_id FROM mail_group_list WHERE list_name=$1 ";
		$result = db_query_params($query, array($listName));
		if ($result) {
			if ($row = db_fetch_array($result)) {
				// Set group_id if present.
				$group_id = $row["group_id"];
			}
		}
		db_free_result($result);
	}
}

if ($group_id) {
	$group = group_get_object($group_id);
	if (!$group || !is_object($group) || $group->isError()) {
		exit_no_group();
	}

	session_require_perm ('project_admin', $group->getID()) ;	

	$mlFactory = new MailingListFactory($group);
	if (!$mlFactory || !is_object($mlFactory) || $mlFactory->isError()) {
		exit_error($mlFactory->getErrorMessage(),'mail');
	}
	$mlArray = $mlFactory->getMailingLists();
	if ($mlFactory->isError()) {
		mail_header(array('title'=>'Mailing List'));
		echo "<p class='error'>Error: Unable to get the lists. " .
			$mlFactory->getErrorMessage() . "</p>";
		mail_footer(array());
		exit;
	}
	$mlCount = count($mlArray);

	// Get array of undeprecated mailing lists.
	$arrMailList = getUndeprecatedMailLists($group_id);

	if (count($arrMailList) <= 0) {
		mail_header(array('title'=>'Mailing List'));
		echo "Mailing lists have been deprecated. " .
			"If you have questions, please contact the " .
			"<a href='/sendmessage.php?recipient=admin&groupname=" .
			$group->getUnixName() . 
			"'>SimTK WebMaster</a>.";
		mail_footer();
		exit;
	}

	mail_header(array('title'=>'Mailing List'));

	if (defined('MAILING_LISTS_VERSION') && MAILING_LISTS_VERSION == 3) {

		// Populate mailing list information.
		$tableHeaders = array("Mailing List", "");

		$cnt = 0;
		$mlCount = count($mlArray);
		for ($i = 0; $i < $mlCount; $i++) {
			$currentList =& $mlArray[$i];
			if ($currentList->isError()) {
				// Skip.
				continue;
			}

			$mlName = $currentList->getName();
			// Mailman3: Do not include deleted list.
			if (in_array($mlName, $arrMailList) && 
				isListPresentMailman3($mlName)) {

				// Non-deprecated list which has not been deleted.

				if ($cnt == 0) {
					// Has non-empty table: first item.
					// Start header.
					echo "<h3>Edit Existing Lists</h3>";
					echo "<p>" . sprintf("Please note that private lists can still be viewed by members of your project, but are not listed on %s.", forge_get_config ("forge_name")) . "</p>";
					echo $HTML->listTableTop($tableHeaders);

					$user = session_get_user(); // get the session user
					$user_id = $user->getID();
					$email = $user->getEmail();
					$token = bin2hex(random_bytes(16));
					$arrParams = array($user_id, $token, $mlName, $email);
					$sql = "INSERT INTO auth_list_logins " .
						"(user_id,token,mailinglist_name,email) " .
						"VALUES ($1,$2,$3,$4) " .
						"ON CONFLICT (email) DO UPDATE SET " .
						"token=EXCLUDED.token," .
						"created_at=now()," .
						"expires_at=now() + INTERVAL '30 minutes'";
					$res = queryMailmanWeb($sql, $arrParams);
				}

				echo "<tr ". $HTML->boxGetAltRowStyle($cnt++) . ">" .
					"<td><strong>" . $mlName . "</strong><br/>" . 
					htmlspecialchars($currentList->getDescription()) . '</td>';
				echo "<td class='align-center'>";
				echo "<a href='https://" . $mlHost . 
					"/mailman3/lists/" . $mlName . 
					"." . $mlHost .  "?token=$token' " .
					"target='_blank'>" .
					"Manage List</a></td>";
				echo "</tr>";
			}
		}

		if ($cnt > 0) {
			// Non-empty table.
			echo $HTML->listTableBottom();
		}
		else {
			echo "Mailing lists have been deprecated. " .
				"If you have questions, please contact the " .
				"<a href='/sendmessage.php?recipient=admin&groupname=" .
				$group->getUnixName() . 
				"'>SimTK WebMaster</a>.";
		}

		mail_footer();
		exit;
	}
	else {
		// Mailman2.
		echo "<h3>Edit Existing Lists</h3>";
		echo "<p>" . sprintf("Please note that private lists can still be viewed by members of your project, but are not listed on %s.", forge_get_config ("forge_name")) . "</p>";
	}

	//
	//	Post Changes to database
	//
	if (getStringFromRequest('post_changes') == 'y') {
		//
		//	Add list
		//
		if (getStringFromRequest('add_list') == 'y') {

			if (check_email_available($group, $group->getUnixName() . '-' . 
				getStringFromPost('list_name'), $error_msg)) {
				$mailingList = new MailingList($group);

				if (!form_key_is_valid(getStringFromRequest('form_key'))) {
					exit_form_double_submit('mail');
				}
				if (!$mailingList || !is_object($mailingList)) {
					form_release_key(getStringFromRequest("form_key"));
					exit_error(_('Error getting the list'),'mail');
				}
				elseif ($mailingList->isError()) {
					form_release_key(getStringFromRequest("form_key"));
					exit_error($mailingList->getErrorMessage(),'mail');
				}

				if (!$mailingList->create(getStringFromPost('list_name'),
					getStringFromPost('description'), getIntFromPost('is_public', 1))) {
					form_release_key(getStringFromRequest("form_key"));
					exit_error($mailingList->getErrorMessage(),'mail');
				}
				else {
					$feedback .= _('List Added');
				}
			}
			else {
				form_release_key(getStringFromRequest("form_key"));
			}
		//
		//	Change status
		//
		}
		elseif (getStringFromPost('change_status') == 'y') {
			$mailingList = new MailingList($group, getIntFromGet('group_list_id'));

			if (!$mailingList || !is_object($mailingList)) {
				exit_error(_('Error getting the list'),'mail');
			}
			elseif ($mailingList->isError()) {
				exit_error($mailingList->getErrorMessage(),'mail');
			}

			if (!$mailingList->update(unInputSpecialChars(getStringFromPost('description')),
				getIntFromPost('is_public', MAIL__MAILING_LIST_IS_PUBLIC),
				MAIL__MAILING_LIST_IS_UPDATED)) {
				exit_error($mailingList->getErrorMessage(),'mail');
			}
			else {
				$feedback .= _('List updated');
			}
		}
	}

	//
	//	Reset admin password
	//
	if (getIntFromRequest('reset_pw') == 1) {
		$mailingList = new MailingList($group, getIntFromGet('group_list_id'));

		if (!$mailingList || !is_object($mailingList)) {
			exit_error(_('Error getting the list'),'mail');
		}
		elseif ($mailingList->isError()) {
			exit_error($mailingList->getErrorMessage(),'mail');
		}

		if ($mailingList->getStatus() == MAIL__MAILING_LIST_IS_CONFIGURED) {
			if (!$mailingList->update($mailingList->getDescription(),
				$mailingList->isPublic(), MAIL__MAILING_LIST_PW_RESET_REQUESTED)) {
				exit_error($mailingList->getErrorMessage(),'mail');
			}
			else {
				$feedback .= _('Password reset requested');
			}
		}
	}

//
//	Form to add list
//
		?>
		<!---
		<form method="post" action="<?php echo getStringFromServer('PHP_SELF'); ?>?group_id=<?php echo $group_id ?>">
			<input type="hidden" name="post_changes" value="y" />
			<input type="hidden" name="add_list" value="y" />
			<input type="hidden" name="form_key" value="<?php echo form_generate_key();?>" />
			<p><strong><?php echo _('Mailing List Name')._(':'); ?></strong><br />
			<strong><?php echo $group->getUnixName(); ?>-<input type="text" name="list_name" value="" size="10" maxlength="12" required="required" pattern="[a-zA-Z0-9]{4,}" />@<?php echo forge_get_config('lists_host'); ?></strong></p>
			<p>
			<strong><?php echo _('Is Public?'); ?></strong><br />
			<input type="radio" name="is_public" value="<?php echo MAIL__MAILING_LIST_IS_PUBLIC; ?>" <?php echo ($group->isPublic() ? ' checked="checked"' : '') ?> ><label><?php echo _('Yes'); ?></label></input><br />
			<input type="radio" name="is_public" value="<?php echo MAIL__MAILING_LIST_IS_PRIVATE; ?>" <?php echo ($group->isPublic() ? '' : ' checked="checked"') ?> ><label><?php echo _('No'); ?></label></input></p><p>
			<strong><?php echo _('Description')._(':'); ?></strong><br />
			<input type="text" name="description" value="" size="40" maxlength="80" /></p>
			<p>
			<input type="submit" name="submit" value="<?php echo _('Add This List'); ?>" /></p>
		</form>
		--->
		<?php
		
		//mail_footer(array());

//
//	Form to modify list
//
	if (getIntFromGet('change_status') && getIntFromGet('group_list_id')) {
		$mailingList = new MailingList($group, getIntFromGet('group_list_id'));

		if (!$mailingList || !is_object($mailingList)) {
			exit_error(_('Error getting the list'), 'mail');
		}
		elseif ($mailingList->isError()) {
			exit_error($mailingList->getErrorMessage(), 'mail');
		}

		?>
		<h3>Update <?php echo $mailingList->getName(); ?></h3>
		<form method="post" action="<?php echo getStringFromServer('PHP_SELF'); ?>?group_id=<?php echo $group_id; ?>&amp;group_list_id=<?php echo $mailingList->getID(); ?>">
			<input type="hidden" name="post_changes" value="y" />
			<input type="hidden" name="change_status" value="y" />
			<p>
			<strong><?php echo _('Is Public?'); ?></strong><br />
			<input type="radio" name="is_public" value="<?php echo MAIL__MAILING_LIST_IS_PUBLIC; ?>"<?php echo ($mailingList->isPublic() == MAIL__MAILING_LIST_IS_PUBLIC ? ' checked="checked"' : ''); ?> ><label><?php echo _('Yes'); ?></label></input><br />
			<input type="radio" name="is_public" value="<?php echo MAIL__MAILING_LIST_IS_PRIVATE; ?>"<?php echo ($mailingList->isPublic() == MAIL__MAILING_LIST_IS_PRIVATE ? ' checked="checked"' : ''); ?> ><label><?php echo _('No'); ?></label></input>
			</p>
			<p>
			<strong><?php echo _('Description')._(':'); ?></strong><br />
			<input type="text" name="description" value="<?php echo inputSpecialChars($mailingList->getDescription()); ?>" size="40" maxlength="80" /></p>
			<p>
			<input type="submit" name="submit"  class="btn-cta" value="<?php echo _('Update'); ?>" /></p>
		</form>
		<a href="deletelist.php?group_id=<?php echo $group_id; ?>&amp;group_list_id=<?php echo $mailingList->getID(); ?>">[<?php echo _('Permanently Delete List'); ?>]</a>
	<?php
		mail_footer(array());
	}
	else {
//
//	Show lists
//
		$mlFactory = new MailingListFactory($group);
		if (!$mlFactory || !is_object($mlFactory) || $mlFactory->isError()) {
			exit_error($mlFactory->getErrorMessage(),'mail');
		}

		?>
		
		<script type="text/javascript">
           $(function() {
                $('.expander').simpleexpand();
           });
        </script>

		<?php
		if ($mlCount > 0) {
			$tableHeaders = array('Mailing List', '', '');
			echo $HTML->listTableTop($tableHeaders);
			$cnt = 0;
			for ($i = 0; $i < $mlCount; $i++) {
				$currentList =& $mlArray[$i];

				if ($currentList->isError()) {
					// Skip.
					continue;
				}

				$mlName = $currentList->getName();
				if (!in_array($mlName, $arrMailList)) {
					// Mailing list deprecated.
					// Skip.
					continue;
				}

				echo '<tr '. $HTML->boxGetAltRowStyle($cnt) . '><td>'.
					'<strong>' . $mlName . '</strong><br/>'.
					htmlspecialchars($currentList->getDescription()).'</td>';
				echo '<td class="align-center">';
				echo '<a href="'.getStringFromServer('PHP_SELF').'?group_id='.$group_id.'&amp;group_list_id='.$currentList->getID().'&amp;change_status=1">'._('Update').'</a>';
				echo '&nbsp&nbsp</td>';
				echo '<td class="align-center">';
				echo '<a href="'.$currentList->getExternalAdminUrl().'?adminpw='.$currentList->getPassword().'" target="_blank">'._('Manage List').'</a>';
				echo '</td>';
				echo '</tr>';

				$cnt++;
			}

			echo $HTML->listTableBottom();
		}
		
		mail_footer(array());
	}
}
else {
	exit_no_group();
}
