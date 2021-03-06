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
 * Copyright 2016-2019, Henry Kwong, Tod Hing - SimTK Team
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

global $HTML;
?>



<?php
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
	
	//
	//	Post Changes to database
	//
	if (getStringFromRequest('post_changes') == 'y') {
		//
		//	Add list
		//
		if (getStringFromRequest('add_list') == 'y') {

			if (check_email_available($group, $group->getUnixName() . '-' . getStringFromPost('list_name'), $error_msg)) {
				$mailingList = new MailingList($group);

				if (!form_key_is_valid(getStringFromRequest('form_key'))) {
					exit_form_double_submit('mail');
				}
				if(!$mailingList || !is_object($mailingList)) {
					form_release_key(getStringFromRequest("form_key"));
					exit_error(_('Error getting the list'),'mail');
				} elseif($mailingList->isError()) {
					form_release_key(getStringFromRequest("form_key"));
					exit_error($mailingList->getErrorMessage(),'mail');
				}

				if(!$mailingList->create(
					getStringFromPost('list_name'),
					getStringFromPost('description'),
					getIntFromPost('is_public', 1)
				)) {
					form_release_key(getStringFromRequest("form_key"));
					exit_error($mailingList->getErrorMessage(),'mail');
				} else {
					$feedback .= _('List Added');
				}
			}
			else {
				form_release_key(getStringFromRequest("form_key"));
			}
		//
		//	Change status
		//
		} elseif (getStringFromPost('change_status') == 'y') {
			$mailingList = new MailingList($group, getIntFromGet('group_list_id'));

			if(!$mailingList || !is_object($mailingList)) {
				exit_error(_('Error getting the list'),'mail');
			} elseif($mailingList->isError()) {
				exit_error($mailingList->getErrorMessage(),'mail');
			}

			if(!$mailingList->update(
				unInputSpecialChars(getStringFromPost('description')),
				getIntFromPost('is_public', MAIL__MAILING_LIST_IS_PUBLIC),
				MAIL__MAILING_LIST_IS_UPDATED
			)) {
				exit_error($mailingList->getErrorMessage(),'mail');
			} else {
				$feedback .= _('List updated');
			}
		}
	}

	//
	//	Reset admin password
	//
	if (getIntFromRequest('reset_pw') == 1) {
		$mailingList = new MailingList($group, getIntFromGet('group_list_id'));

		if(!$mailingList || !is_object($mailingList)) {
			exit_error(_('Error getting the list'),'mail');
		} elseif($mailingList->isError()) {
			exit_error($mailingList->getErrorMessage(),'mail');
		}

		if($mailingList->getStatus() == MAIL__MAILING_LIST_IS_CONFIGURED) {
			if(!$mailingList->update(
				   $mailingList->getDescription(),
				   $mailingList->isPublic(),
				   MAIL__MAILING_LIST_PW_RESET_REQUESTED
				   )) {
				exit_error($mailingList->getErrorMessage(),'mail');
			} else {
				$feedback .= _('Password reset requested');
			}
		}
	}

//
//	Form to add list
//
/*
	if(getIntFromGet('add_list')) {
		mail_header(array('title' => _('Mailing List')));
		//echo " <a href='/mail/admin/?group_id=$group_id' class='btn-blue share_text_button'>Administration</a>";

		echo "<h3>Add Mailing List</h3>";
		print '<p>';
		printf(_('Lists are named in this manner:<br /><strong>projectname-listname@%s</strong>'), forge_get_config('lists_host'));
		print '</p>';

		print '<p>';
		print _('It will take one hour for your list to be created.');
		print '</p>';

		$mlFactory = new MailingListFactory($group);
		if (!$mlFactory || !is_object($mlFactory) || $mlFactory->isError()) {
			exit_error($mlFactory->getErrorMessage(),'mail');
		}

		$mlArray = $mlFactory->getMailingLists();

		if ($mlFactory->isError()) {
			echo '<p class="error">'._('Error').' '._('Unable to get the lists') .$mlFactory->getErrorMessage().'</p>';
			mail_footer(array());
			exit;
		}

		$tableHeaders = array(
			_('Existing mailing lists')
		);
//
//	Show lists
//
		$mlCount = count($mlArray);
		if($mlCount > 0) {
			echo $HTML->listTableTop($tableHeaders);
			for ($j = 0; $j < $mlCount; $j++) {
				$currentList =& $mlArray[$j];
				if ($currentList->isError()) {
					echo '<tr '. $HTML->boxGetAltRowStyle($j) . '><td>';
					echo $currentList->getErrorMessage();
					echo '</td></tr>';
				} else {
					echo '<tr '. $HTML->boxGetAltRowStyle($j) . '><td>'.$currentList->getName().'</td></tr>';
				}
			}
			echo $HTML->listTableBottom();
		}
//
//	Form to add list
//
*/
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
	if(getIntFromGet('change_status') && getIntFromGet('group_list_id')) {
		$mailingList = new MailingList($group, getIntFromGet('group_list_id'));

		if(!$mailingList || !is_object($mailingList)) {
			exit_error(_('Error getting the list'),'mail');
		} elseif($mailingList->isError()) {
			exit_error($mailingList->getErrorMessage(),'mail');
		}

		mail_header(array('title' => _('Mailing List')));
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
	} else {
//
//	Show lists
//
		$mlFactory = new MailingListFactory($group);
		if (!$mlFactory || !is_object($mlFactory) || $mlFactory->isError()) {
			exit_error($mlFactory->getErrorMessage(),'mail');
		}

		mail_header(array(
			'title' => _('Mailing Lists'))
		);

		//echo "<a href='/mail/admin/?add_list=1&group_id=$group_id' class='btn-blue share_text_button'>Add</a>";
		
		?>
		
		<script type="text/javascript">
           $(function() {
                $('.expander').simpleexpand();
           });
        </script>

		<div class="expand_content">
		<div id="panel1.1">
		<h2><a style="color:#f75236;font-size:29px;" id="expander" class="expander toggle collapsed" href="#">Add List</a></h2>
					<div class="content"  style="display: block;">
	
		<p>
		Lists are named in this manner:<br />
        <b>projectname-listname@simtk.org</b>
        </p>
		<p>It will take one hour for your list to be created.</p>
		
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
			<input type="submit" name="submit" class="btn-cta" value="<?php echo _('Add This List'); ?>" /></p>
		</form>
		
		</div>
		</div>
	    </div>	
		
		<?php
		$mlArray = $mlFactory->getMailingLists();

		if ($mlFactory->isError()) {
			echo '<p>'._('Error').' '.sprintf(_('Unable to get the list %s'), $group->getPublicName()) .'</p>';
			echo '<div class="error">'.$mlFactory->getErrorMessage().'</div>';
			mail_footer(array());
			exit;
		}
		
		echo '<h3>Edit Existing Lists</h3>';
		
		echo '<p>'.sprintf(_('Please note that private lists can still be viewed by members of your project, but are not listed on %s.'), forge_get_config ('forge_name')).'</p>';
		//echo '<ul><li><a href="'.getStringFromServer('PHP_SELF').'?group_id='.$group_id.'&amp;add_list=1">'._('Add Mailing List').'</a></li></ul>';
		$mlCount = count($mlArray);
		if($mlCount > 0) {
			$tableHeaders = array(
				_('Mailing List'),
				'',
				''
			);
			echo $HTML->listTableTop($tableHeaders);
			for ($i = 0; $i < $mlCount; $i++) {
				$currentList =& $mlArray[$i];
				if ($currentList->isError()) {
					echo '<tr '. $HTML->boxGetAltRowStyle($i) .'><td colspan="4">';
					echo $currentList->getErrorMessage();
					echo '</td></tr>';
				} else {
					echo '<tr '. $HTML->boxGetAltRowStyle($i) . '><td>'.
					'<strong>'.$currentList->getName().'</strong><br />'.
					htmlspecialchars($currentList->getDescription()).'</td>';
					echo '<td class="align-center">';
					//if ($currentList->getStatus() != MAIL__MAILING_LIST_PW_RESET_REQUESTED) {
						echo '<a href="'.getStringFromServer('PHP_SELF').'?group_id='.$group_id.'&amp;group_list_id='.$currentList->getID().'&amp;change_status=1">'._('Update').'</a>';
					//}
					echo '&nbsp&nbsp</td>';
					echo '<td class="align-center">';
					if($currentList->getStatus() == MAIL__MAILING_LIST_IS_REQUESTED) {
						echo _('Not activated yet');
					} else {
						echo '<a href="'.$currentList->getExternalAdminUrl().'?adminpw='.$currentList->getPassword().'" target="_blank">'._('Administration').'</a>';
					}
					echo '</td>';
					/*
					echo '<td class="align-center">';
					if($currentList->getStatus() == MAIL__MAILING_LIST_IS_CONFIGURED) {
						print '<a href="'.getStringFromServer('PHP_SELF').'?group_id='.$group_id.'&amp;group_list_id='.$currentList->getID().'&amp;reset_pw=1">'._('Reset admin password').'</a></td>' ;

					}
					*/
					echo '</tr>';
				}
			}
			echo $HTML->listTableBottom();
		}
		
		
		mail_footer(array());
	}
} else {
	exit_no_group();
}
