<?php
/**
 * Generic Tracker facility
 *
 * Copyright 1999-2001 (c) VA Linux Systems; 2005 GForge, LLC
 * Copyright 2012,2014, Franck Villaume - TrivialDev
 * Copyright 2016-2021, Henry Kwong, Tod Hing - SimTK Team
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
require_once 'note.php';

// Check whether anonymous posting is allowed in the given tracker.
function isAllowAnon($idGroupArtifact) {
	$strSql = 'SELECT simtk_allow_anon FROM artifact_group_list ' .
		'WHERE group_artifact_id=' . $idGroupArtifact;
	$res = db_query_params($strSql, array());
	if ($res && db_numrows($res) > 0) {
		$isAllowAnon = db_result($res, 0, 'simtk_allow_anon');
		if ($isAllowAnon == 1) {
			return true;
		}
	}
	return false;
}

function artifact_submission_form($ath, $group) {
	global $HTML;

	// Check if tracker access is allowed.
	if (!$ath->isPermitted()) {
		echo $HTML->warning_msg("Permission denied. This project's administrator will have to grant you permission to view this page.");
		echo '</td></tr></table></form>';
		return;
	}               

	/*
		Show the free-form text submitted by the project admin
	*/
	echo notepad_func();
	//echo $ath->renderSubmitInstructions();
	echo '<span class="required_note"><br/>Required fields outlined in blue</span><br/><br/>';
?>

	<form id="trackeraddform" action="<?php echo getStringFromServer('PHP_SELF') . '?group_id='.$group->getID().'&amp;atid='.$ath->getID(); ?>" method="post" enctype="multipart/form-data">
	<input type="hidden" name="form_key" value="<?php echo form_generate_key(); ?>" />
	<input type="hidden" name="func" value="postadd" />
	<input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
	<table>

	<tr>
		<td class="top">
<?php
/*
	if (!session_loggedin()) {
		echo '<div class="login_warning_msg">';
		echo $HTML->warning_msg(_('Please').' '.util_make_link('/account/login.php?return_to='.urlencode(getStringFromServer('REQUEST_URI')), _('login')));
		echo _('If you <strong>cannot</strong> login, then enter your email address here')._(':').'<p>
		<input type="text" name="user_email" size="50" maxlength="255" /></p>
		</div>';
	}
*/
	if (!session_loggedin() && isAllowAnon($ath->getID()) == false) {
		// Anonymous posting not allowed.
		// Prompt user to log in.
		exit_not_logged_in();
	}

?>
		</td>
	</tr>
	<tr>
		<td class="top"><strong><?php echo _('For project')._(':'); ?></strong><br /><?php echo $group->getPublicName(); ?></td>
		<td class="top"><input type="submit" name="submit" value="Submit" class="btn-cta" /></td>
	</tr>

<?php
	$ath->renderExtraFields(array(),true,'none',false,'Any',array(),false,'UPDATE');

	if (forge_check_perm ('tracker', $ath->getID(), 'manager')) {
		echo '<tr>
		<td><strong>'._('Assigned to')._(':').'</strong><br />';
		echo $ath->technicianBox('assigned_to');
		echo '&nbsp;'.util_make_link('/tracker/admin/?group_id='.$group->getID().'&atid='.$ath->getID().'&update_users=1', '('._('Admin').')' );

		echo '</td><td><strong>'._('Priority')._(':').'</strong><br />';
		build_priority_select_box('priority');
		echo '</td></tr>';
	}
?>
	<tr>
		<td colspan="2"><strong><?php echo _('Summary')._(':'); ?></strong><br />
			<input id="tracker-summary" class="required" required="required" type="text" name="summary" size="80" maxlength="255" title="<?php echo util_html_secure(html_get_tooltip_description('summary')); ?>" />
		</td>
	</tr>

	<tr>
		<td colspan="2">
			<strong><?php echo _('Detailed description')._(':'); ?></strong><?php notepad_button('document.forms.trackeraddform.details'); ?><br />
			<textarea id="tracker-description" class="required" required="required" name="details" rows="20" cols="80" title="<?php echo util_html_secure(html_get_tooltip_description('description')); ?> "></textarea>
		</td>
	</tr>

	<tr>
		<td colspan="2">
<?php
/*
	if (!session_loggedin()) {
		echo '<div class="login_warning_msg">';
		echo $HTML->error_msg(_('Please').' '.util_make_link('/account/login.php?return_to='.urlencode(getStringFromServer('REQUEST_URI')), _('login')));
		echo _('If you <strong>cannot</strong> login, then enter your email address here').':<p>
		<input type="text" name="user_email" size="30" maxlength="255" /></p>
		</div>';
	}
*/
?>
		<p>&nbsp;</p>
		<span class="important"><?php echo _('DO NOT enter passwords or confidential information in your message!'); ?></span>
		</td>
	</tr>

	<tr>
		<td colspan="2">
		<div class="file_attachments">
		<p>
		<strong><?php echo _('Attach Files')._(':'); ?> </strong> <?php echo('('._('max upload size: '.human_readable_bytes(util_get_maxuploadfilesize())).')') ?><br />
		<input type="file" name="input_file0" /><br />
		<input type="file" name="input_file1" /><br />
		<input type="file" name="input_file2" /><br />
		<input type="file" name="input_file3" /><br />
		<input type="file" name="input_file4" />
		</p>
		</div>
		</td>
	</tr>

	<tr><td colspan="2">
		<input type="submit" name="submit" value="Submit" class="btn-cta" />
		</td>
	</tr>

	</table>
	</form>
<?php
	//echo $HTML->addRequiredFieldsInfoBox();
}
