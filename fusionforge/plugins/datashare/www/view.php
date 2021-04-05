<?php

/**
 *
 * view.php
 * 
 * Copyright 2005-2021, SimTK Team
 *
 * This file is part of the SimTK web portal originating from        
 * Simbios, the NIH National Center for Physics-Based               
 * Simulation of Biological Structures at Stanford University,      
 * funded under the NIH Roadmap for Medical Research, grant          
 * U54 GM072970, with continued maintenance and enhancement
 * funded under NIH grants R01 GM107340 & R01 GM104139, and 
 * the U.S. Army Medical Research & Material Command award 
 * W81XWH-15-1-0232R01.
 * 
 * SimTK is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as 
 * published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 * 
 * SimTK is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details. 
 * 
 * You should have received a copy of the GNU General Public 
 * License along with SimTK. If not, see  
 * <http://www.gnu.org/licenses/>.
 */ 


require_once '../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfplugins.'datashare/www/datashare-utils.php';
require_once $gfplugins.'datashare/include/Datashare.class.php';
require_once $gfwww.'project/project_utils.php';
require_once $gfplugins.'api/include/Api.class.php';

// Override and use the configuration parameters as specified in
// the file datashare.ini if the file is present and has the relevant parameters.
if (file_exists("/etc/gforge/config.ini.d/datashare.ini")) {
	// The file datashare.ini is present.
	$arrDatashareConfig = parse_ini_file("/etc/gforge/config.ini.d/datashare.ini");

	// Check for each parameter's presence.
	if (isset($arrDatashareConfig["datashare_server"])) {
		$datashareServer = $arrDatashareConfig["datashare_server"];
	}
}
if (!isset($datashareServer)) {
	exit_error("Cannot get datashare server");
}

$group_id = getIntFromRequest('id');
$study_id = getIntFromRequest('studyid');
$typeConfirm = getIntFromRequest('typeConfirm');
$theToken = trim(getStringFromRequest('token'));
$nameDownload = trim(getStringFromRequest('nameDownload'));
$pathSelected = trim(getStringFromRequest('pathSelected'));
$namePackage = trim(getStringFromRequest('namePackage'));
$strFilesHash = trim(getStringFromRequest('filesHash'));

$pluginname = 'datashare';

if (!$group_id || !$study_id) {
	exit_error("Cannot Process your request","No ID specified");
}

$group = group_get_object($group_id);
if (!$group) {
	exit_error("Invalid Project", "Inexistent Project");
}

$userid = 0;
$member = 0;
$firstname = "";
$lastname = "";
$email = "";
if (session_loggedin()) {
	// get user
	$user = session_get_user(); // get the session user
	$userid = $user->getID();
	$firstname = $user->getFirstName();
	$lastname = $user->getLastName();
	$email = $user->getEmail();

	if (user_ismember($group_id)) {
		$member = 1;
	}
}


if (!($group->usesPlugin ( $pluginname )) ) {//check if the group has the Data Share plugin active
	exit_error("Error", "First activate the $pluginname plugin through the Project's Admin Interface");
}

// get study
// get current studies
$study = new Datashare($group_id);

if (!$study || !is_object($study)) {
	exit_error('Error','Could Not Create Study Object');
}
elseif ($study->isError()) {
	exit_error($study->getErrorMessage(), 'Datashare Error');
}

datashare_header(array('title'=>'Data Share:'),$group_id);

$study_result = $study->getStudy($study_id);
echo "<h4>&nbsp; " . $study_result[0]->title;
if ($study->isDOI($study_id)) {
	if (empty($study->getDOI($study_id))) {
		echo " (doi: pending)";
	}
	else {
		echo " (doi:" . $study->getDOI($study_id) . ")";
	}
}
echo "</h4>";

echo "<div class=\"project_overview_main\">\n";
echo "<div style=\"display: table; width: 100%;\">\n";
echo "<div class=\"main_col\">\n";

if ($study_result) {

	// Set timezone.
	date_default_timezone_set('America/Los_Angeles');

	// Get number of days since epoch.
	$now = new DateTime();
	$epoch = new DateTime('1970-01-01');
	$diffTime = $now->diff($epoch);
	$numDays = $diffTime->format("%a");

	$private = $study_result[0]->is_private;
	$display_study = 0;

	// Validate token for package download, if present.
	if (trim($namePackage) != "" && trim($theToken) != "") {
		$api = new Api;
		if (!$api || !is_object($api)) {
			echo "Cannot validate information";
			echo "</div></div></div></div></div>";
			site_project_footer(array());
			exit;
		}
		else {
			if (!$api->verifyToken($study_result[0]->token, $userid, $numDays, $theToken)) {
				if (session_loggedin()) {
					// Invalid user.
					echo "<span>&nbsp;&nbsp;&nbsp;User does not have valid permission for the download.</span>";
					echo "</div></div></div></div></div>";
					site_project_footer(array());
					exit;
				}
				else {
					// User is not logged in. Show login prompt.
					session_require_perm('datashare', $group_id, 'read_public');
				}
			}
		}
	}

	if ($study_result[0]->is_private == 0) {
		// public
		$display_study = 1;
	}
	elseif ($study_result[0]->is_private == 1) {
		// check if registered user
		// Check permission and prompt for login if needed.
		if ($userid) {
			$display_study = 1;
		}
		else {
			echo "<script type='text/javascript'>window.top.location='" .
				"index.php?group_id=" . $group_id . "&login=1" .
				"';</script>";
			exit;
		}
	}
	elseif ($study_result[0]->is_private == 2) {
		// check if member
		if ($userid) {
			if (user_ismember($group_id) || forge_check_global_perm('forge_admin')) {
				$display_study = 1;
			}
			else {
				echo "You must be a project member to access this private study.";
				echo "</div></div></div></div></div>";
				site_project_footer(array());
				exit;
			}
		}
		else {
			// Generate the URL to return to after user login.
			$return_to = "/plugins/datashare/view.php?" .
				"id=" . $group_id .
				"&studyid=" . $study_id;
			if ($pathSelected != "") {
				// selected path is present.
				$return_to .= "&pathSelected=" . $pathSelected;
			}
			echo " Please " .
				"<a href='/account/login.php?return_to=" .
				urlencode($return_to) .
				"'>log in</a> or " .
				"<a href='/account/register.php'>create an account</a>" .
				" to access the study.";
			echo "</div></div></div></div></div>";
			site_project_footer(array());
			exit;
		}
	}

	if ($display_study) {
		$strInfo = $study_result[0]->token . ":" . $userid . ":" . $numDays;
		$token = password_hash($strInfo, PASSWORD_DEFAULT);
?>

<script>
$(window).ready(function() {
	// Set up event listener for message posted from child iframe.
	var myEventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
	var myEventHandler = window[myEventMethod];
	var myMessageEvent = myEventMethod == "attachEvent" ? "onmessage" : "message";

	// Listen to message.
	myEventHandler(myMessageEvent, function(theEvent) {
		if (theEvent.data.event_id == "iframeLoaded") {
			// NOTE: Use the following to ensure that
			// the iframe content is displayed when loaded.
			// Otherwise, the iframe content is sometimes
			// not displayed until the browser is resized.
			$("iframe").css("visibility", "visible");
			$("iframe").css("display", "none");
			$("iframe").fadeIn(300);
		}
	}, false);
});
</script>

<?php

		echo "<iframe name=\"" . rand() . "\" src=\"https://" .
			$datashareServer . 
			"?section=datashare&" .
			"groupid=$group_id&" .
			"userid=$userid&" .
			"studyid=$study_id&" .
			"isDOI=" . $study->isDOI($study_id) . "&" .
			"doi_identifier=" . $study->getDOI($study_id) . "&" .
			"subject_prefix=" . $study_result[0]->subject_prefix . "&" .
			"token=$token&" .
			"private=$private&" .
			"member=$member&" .
			"typeConfirm=$typeConfirm&" .
			"nameDownload=$nameDownload&" .
			"pathSelected=$pathSelected&" .
			"namePackage=$namePackage&" .
			"filesHash=$strFilesHash&" .
			"firstname=$firstname&" .
			"lastname=$lastname" .
			"\" frameborder=\"0\" " .
			"scrolling=\"yes\"  " .
			"height=\"1000\" " .
			"width=\"1000\" " .
			"align=\"left\">" .
			"</iframe>";
	}

}
else {
	echo "Error getting study";
}

echo "</div></div></div>"; // end of main_col

echo "</div></div>";

site_project_footer(array());


// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
