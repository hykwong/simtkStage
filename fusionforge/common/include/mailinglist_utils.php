<?php

/**
 *
 * Copyright 2005-2025, SimTK Team
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

// Look up Mailman server and version.
if (!defined('MAILING_LISTS_VERSION')) {
	$mlVer = forge_get_config("mailman_ver");
	if ($mlVer != null && trim($mlVer) != "") {
		$mlVer = intval($mlVer);
	}
	if ($mlVer == 3) {
		// Mailman3.
		define('MAILING_LISTS_VERSION', 3);
	}
	else {
		// Legacy Mailman2.
		define('MAILING_LISTS_VERSION', 2);
	}
}

// Utility to query MailmanWeb database.
function queryMailmanWeb($theQuery, $arrParams) {
	return queryMailman($theQuery, $arrParams, "ml_name_web");
}
 
// Utility to query Mailman3 database.
function queryMailman($theQuery, $arrParams, $ml_name=false) {

	// Check whether mailinglists.ini is present.
	if (!file_exists("/etc/gforge/config.ini.d/mailinglists.ini")) {
		return null;
	}
	$arrMailingListsConfig = parse_ini_file("/etc/gforge/config.ini.d/mailinglists.ini");

	// Check for each parameter's presence.
	if ($ml_name === false) {
		$ml_name = "ml_name";
	}
	if (isset($arrMailingListsConfig[$ml_name])) {
		$mlName = $arrMailingListsConfig[$ml_name];
	}
	if (isset($arrMailingListsConfig["ml_user"])) {
		$mlUser = $arrMailingListsConfig["ml_user"];
	}
	if (isset($arrMailingListsConfig["ml_password"])) {
		$mlPass = $arrMailingListsConfig["ml_password"];
	}

	// Get mailing lists host.
	$mlHost = forge_get_config("lists_host");

	if (!isset($mlName) || !isset($mlUser) || !isset($mlPass) || 
		!isset($mlHost)) {
		// Cannot get mailing lists credentials.
		return null;
	}

	// Connect and select database.
	$dbConn = pg_connect(
		"host=" . $mlHost .
		" dbname=" . $mlName .
		" user=" . $mlUser .
		" password=" . $mlPass);
	if ($dbConn == null) {
		// Cannot connect to forum. Do not proceed.
		return null;
	}

	// Perform SQL query.
	$result = pg_query_params($dbConn, $theQuery, $arrParams) or 
		die('Query failed: ' . pg_last_error());

	// Closing connection
	pg_close($dbConn);

	return $result;
}

// Get array of undeprecated mailing lists.
function getUndeprecatedMailLists($groupId) {
	$arrMailList = array();
	$query  = "SELECT mgl.list_name AS list_name FROM mail_group_list mgl " .
		"JOIN mail_list_keep mlk " .
		"ON mgl.list_name=mlk.list_name " .
		"WHERE mgl.group_id=$1";
	$result = db_query_params($query, array($groupId));
	if ($result) {
		while ($row = db_fetch_array($result)) {
			$arrMailList[] = $row["list_name"];
		}
		db_free_result($result);
	}

	return $arrMailList;
}

// Is mailing list present in Mailman3
function isListPresentMailman3($listName) {

	if (MAILING_LISTS_VERSION !== 3) {
		// This check is only for Mailman3.
		return true;
	}

	$strQuery = "SELECT count(*) as num_lists FROM mailinglist WHERE list_name=$1";
	$arrParams = array($listName);
	$res = queryMailman($strQuery, $arrParams);
	if ($res == null) {
		return true;
	}
	while ($row = pg_fetch_array($res, null, PGSQL_ASSOC)) {
		$numLists = $row["num_lists"];
		if ($numLists > 0) {
			$isPresent = true;
		}
		else {
			$isPresent = false;
		}
	}

	// Free resultset.
	pg_free_result($res);

	return $isPresent;
}

// Check whether digests are enabled in mailing list.
function isDigestEnabled($listName) {

	if (MAILING_LISTS_VERSION == 2) {
		// Access legacy Mailman2.
		return isDigestEnabledMailman2($listName);
	}
	else if (MAILING_LISTS_VERSION != 3) {
		return false;
	}

	// Mailman3.

	$isEnabled = false;

	$strQuery = "SELECT digests_enabled FROM mailinglist WHERE list_name=$1";
	$arrParams = array($listName);
	$res = queryMailman($strQuery, $arrParams);
	if ($res == null) {
		return false;
	}
	while ($row = pg_fetch_array($res, null, PGSQL_ASSOC)) {
		$strDigestEnabled = $row["digests_enabled"];
		if ($strDigestEnabled == "t") {
			$isEnabled = true;
		}
		else {
			$isEnabled = false;
		}
	}

	// Free resultset.
	pg_free_result($res);

	return $isEnabled;
}

// Update user email address in all mailing lists.
function updateMailingListsEmailAddr($userName, $userEmailOld, $userEmailNew) {

	if (MAILING_LISTS_VERSION != 3) {
		return;
	}

	// Mailman3.

	$userEmailOld = escapeshellcmd($userEmailOld);
	if (!validate_email($userEmailOld)) {
		return;
	}
	$userEmailNew = escapeshellcmd($userEmailNew);
	if (!validate_email($userEmailNew)) {
		return;
	}

	// Get mailing lists host.
	$mlHost = forge_get_config("lists_host");
	if (!isset($mlHost)) {
		// Cannot get mailing lists credentials.
		return;
	}
	$strPrepend = "ssh root@" . $mlHost . " ";

	// Change email address on all mailing lists.
	$cmdChangeAddr = $strPrepend . 
		"/opt/mailman/venv/bin/mailman --run-as-root " .
		"changeaddress $userEmailOld $userEmailNew 2>&1";

	$fp = fopen("/opt/tmp/MailingListChangeAddress.log", "a+");
	fwrite($fp, "User $userName Old: $userEmailOld New: $userEmailNew ");

	$retStatus = exec($cmdChangeAddr);

	// Log error if exists.
	if (($idx = stripos($retStatus, "Error:")) !== false) {
		$strError = substr($retStatus, $idx);
		fwrite($fp, "'" . $strError . "' at " . date('Y-m-d H:i:s') . "\n");
	}
	else {
		fwrite($fp, "at " . date('Y-m-d H:i:s') . "\n");
	}
	fclose($fp);
}

// Get owners in mailing list.
function getOwnersInMailingList($listName) {

	if (MAILING_LISTS_VERSION == 2) {
		return false;
	}

	// Mailman3

	$strQuery = "SELECT email FROM member m " .
		"JOIN mailinglist ml ON m.list_id=ml.list_id " .
		"JOIN address a ON a.id=m.address_id " .
		"WHERE list_name=$1 AND role=2";
	$arrParams = array($listName);
	$res = queryMailman($strQuery, $arrParams);
	if ($res == null) {
		return false;
	}
	$arrOwners = array();
	while ($row = pg_fetch_array($res, null, PGSQL_ASSOC)) {
		$arrOwners[] = $row["email"];
	}

	// Free resultset.
	pg_free_result($res);

	return $arrOwners;
}

// Get roles in mailing list.
function getRolesInMailingList($listName, $userEmail) {

	if (MAILING_LISTS_VERSION == 2) {
		return false;
	}

	// Mailman3

	$strQuery = "SELECT role FROM member m " .
		"JOIN mailinglist ml ON m.list_id=ml.list_id " .
		"JOIN address a ON a.id=m.address_id " .
		"WHERE list_name=$1 AND " .
		"email=$2";
	$arrParams = array($listName, $userEmail);
	$res = queryMailman($strQuery, $arrParams);
	if ($res == null) {
		return false;
	}
	$arrRoles = array();
	while ($row = pg_fetch_array($res, null, PGSQL_ASSOC)) {
		$arrRoles[] = intval($row["role"]);
	}

	// Free resultset.
	pg_free_result($res);

	if (count($arrRoles) == 0) {
		return false;
	}
	return $arrRoles;
}

// Check whether user is member of mailing list.
function isMemberOfMailingList($listName, $userEmail) {

	if (MAILING_LISTS_VERSION == 2) {
		// Access legacy Mailman2.
		return isMemberOfMailingListMailman2($listName, $userEmail);
	}
	else if (MAILING_LISTS_VERSION != 3) {
		return false;
	}

	// Mailman3.

	$isMember = false;

	$strQuery = "SELECT count(*) as num_members FROM member m " .
		"JOIN mailinglist ml ON m.list_id=ml.list_id " .
		"JOIN address a ON a.id=m.address_id " .
		"WHERE list_name=$1 AND " .
		"email=$2";
	$arrParams = array($listName, $userEmail);
	$res = queryMailman($strQuery, $arrParams);
	if ($res == null) {
		return false;
	}
	while ($row = pg_fetch_array($res, null, PGSQL_ASSOC)) {
		$numMembers = $row["num_members"];
		if ($numMembers > 0) {
			$isMember = true;
		}
		else {
			$isMember = false;
		}
	}

	// Free resultset.
	pg_free_result($res);

	return $isMember;
}

// Add member to mailing list.
function addMemberToMailingList($listName, $userName, $userEmail, $digest) {

	$userName = escapeshellcmd($userName);
	$userEmail = escapeshellcmd($userEmail);
	if (!validate_email($userEmail)) {
		return;
	}

	// Get mailing lists host.
	$mlHost = forge_get_config("lists_host");
	if (!isset($mlHost)) {
		// Cannot get mailing lists credentials.
		return;
	}
	$strPrepend = "ssh root@" . $mlHost . " ";

	if (MAILING_LISTS_VERSION == 2) {
		// Access legacy Mailman2.
		if ($digest) {
			$deliveryOption = "-d";
		}
		else {
			$deliveryOption = "-r";
		}
		$cmdAddMember = "echo '$userName <$userEmail>' | " . 
			$strPrepend . 
			"/usr/lib/mailman/bin/add_members " .
			$deliveryOption .
			" - -w y " .
			$listName;
	}
	else if (MAILING_LISTS_VERSION == 3) {

		if (!isListPresentMailman3($listName)) {
			// Ignore. Mailing list is not present.
			return;
		}

		// Mailman3.
		if ($digest) {
			$deliveryOption = "-d plain";
		}
		else {
			$deliveryOption = "-d regular";
		}
		$cmdAddMember = "echo $userEmail | " . 
			$strPrepend . 
			"/opt/mailman/venv/bin/mailman --run-as-root addmembers " .
			$deliveryOption . 
			" - " .
			$listName . "@" . $mlHost;
	}
	else {
		// Invalid version. Do not proceed.
		return;
	}

	exec($cmdAddMember);

	$fp = fopen("/opt/tmp/MailingListSubscription.log", "a+");
	if ($digest) {
		fwrite($fp, "$listName (Digest): $userEmail at " . date('Y-m-d H:i:s') . "\n");
	}
	else {
		fwrite($fp, "$listName (Regular): $userEmail at " . date('Y-m-d H:i:s') . "\n");
	}
	fclose($fp);
}

// Remove member from mailing list.
function removeMemberFromMailingList($listName, $userEmail) {

	$userEmail = escapeshellcmd($userEmail);
	if (!validate_email($userEmail)) {
		// Invalid email.
		return false;
	}

	// Get mailing lists host.
	$mlHost = forge_get_config("lists_host");
	if (!isset($mlHost)) {
		// Cannot get mailing lists credentials.
		return false;
	}
	$strPrepend = "ssh root@" . $mlHost . " ";

	if (MAILING_LISTS_VERSION == 2) {
		// Access legacy Mailman2.
		$cmdUnsubscribe = $strPrepend . 
			"/usr/lib/mailman/bin/remove_members $listName $userEmail";
	}
	else if (MAILING_LISTS_VERSION == 3) {

		// Mailman3.

		if (!isListPresentMailman3($listName)) {
			// Ignore. Mailing list is not present.
			return false;
		}

		$arrOwners = getOwnersInMailingList($listName);
		if ($arrOwners !== false && 
			in_array($userEmail, $arrOwners) && 
			count($arrOwners) === 1) {
			// Should not remove last owner in mailing list.
			return "Cannot remove the last owner of mailing list";
		}

		// Get member roles in mailing list.
		$arrRoles = getRolesInMailingList($listName, $userEmail);
		if ($arrRoles === false) {
			// Not a member of list. Ignore.
			return false;
		}

		if (in_array(2, $arrRoles, true)) {
			// Remove owner.
			$cmdUnsubscribe = $strPrepend . 
				"/opt/mailman/venv/bin/mailman --run-as-root " .
				"admins " .
				"-r owner " .
				"-d $userEmail " .
				"$listName". "@" . $mlHost;
			exec($cmdUnsubscribe);
		}

		if (in_array(3, $arrRoles, true)) {
			// Remove moderator.
			$cmdUnsubscribe = $strPrepend . 
				"/opt/mailman/venv/bin/mailman --run-as-root " .
				"admins " .
				"-r moderator " .
				"-d $userEmail " .
				"$listName". "@" . $mlHost;
			exec($cmdUnsubscribe);
		}

		if (in_array(1, $arrRoles, true)) {
			// Remove member.
			$cmdUnsubscribe = $strPrepend . 
				"/opt/mailman/venv/bin/mailman --run-as-root " .
				"delmembers " .
				"-m $userEmail " .
				"-l $listName". "@" . $mlHost;
			exec($cmdUnsubscribe);
		}
	}
	else {
		// Invalid version. Do not proceed.
		return false;
	}

	$fp = fopen("/opt/tmp/MailingListUnsubscription.log", "a+");
	fwrite($fp, "$listName : Removed $userEmail at " . date('Y-m-d H:i:s') . "\n");
	fclose($fp);

	return true;
}


// Check whether digests are enabled in Mailman2 mailing list.
function isDigestEnabledMailman2($listName) {

	if (MAILING_LISTS_VERSION != 2) {
		return false;
	}

	// Get mailing lists host.
	$mlHost = forge_get_config("lists_host");
	if (!isset($mlHost)) {
		// Cannot get mailing lists credentials.
		return false;
	}
	$strPrepend = "ssh root@" . $mlHost . " ";

	$cmdGetMailingListDigestOption = $strPrepend . "/usr/lib/mailman/bin/withlist --quiet -r get_digest_option " . $listName;
	exec($cmdGetMailingListDigestOption, $arrOut);

	if (isset($arrOut) && isset($arrOut[0]) && 
		$arrOut[0] != "True" && $arrOut[0] != 1) {
		// "No digest" option is set for the mailinag list.
		return false;
	}
	else {
		// "Digest" option is set for the mailinag list.
		return true;
	}
}

// Check whether user is member of Mailman2 mailing list.
function isMemberOfMailingListMailman2($listName, $userEmail) {

	$userEmail = escapeshellcmd($userEmail);
	if (!validate_email($userEmail)) {
		return false;
	}

	if (MAILING_LISTS_VERSION != 2) {
		return false;
	}

	// Get mailing lists host.
	$mlHost = forge_get_config("lists_host");
	if (!isset($mlHost)) {
		// Cannot get mailing lists credentials.
		return false;
	}
	$strPrepend = "ssh root@" . $mlHost . " ";

	// Get mail list members.
	$cmdListMembers = $strPrepend . "/usr/lib/mailman/bin/list_members $listName";
	exec($cmdListMembers, $listMembers);

	for ($cnt = 0; $cnt < count($listMembers); $cnt++ ) {
		if (strtolower($listMembers[$cnt]) == $userEmail) {
			// Already a member of this mail list.
			return true;
		}
	}

	// Not member of this mail list.
	return false;
}

?>
