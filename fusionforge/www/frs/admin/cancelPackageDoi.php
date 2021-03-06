<?php

/**
 * Project Admin: Cancel a DOI request on package.
 *
 * Copyright 2016-2019, Henry Kwong, Tod Hing - SimTK Team
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
require_once $gfcommon.'frs/include/frs_utils.php';
require_once $gfcommon.'frs/FRSPackage.class.php';

$group_id = getIntFromRequest('group_id');
$package_id = getIntFromRequest('package_id');
if (!$group_id) {
	exit_no_group();
}

$project = group_get_object($group_id);
if (!$project || !is_object($project)) {
    exit_no_group();
}
elseif ($project->isError()) {
	exit_error($project->getErrorMessage(),'frs');
}

session_require_perm ('frs', $group_id, 'write') ;


// Get package.
$frsp = new FRSPackage($project, $package_id);
if (!$frsp || !is_object($frsp)) {
	exit_error(_('Could Not Get FRS Package'),'frs');
}
elseif ($frsp->isError()) {
	exit_error($frsp->getErrorMessage(),'frs');
}

// Cancel a DOI request.

frs_admin_header(array('title'=>'Cancel DOI Request','group'=>$group_id));

echo '<hr />';
echo '<div><h3>' . $frsp->getName() . '</h3></div>';
	echo '
	<form action="/frs/admin/?group_id='.$group_id.'" method="post">
	<input type="hidden" name="func" value="cancel_package_doi" />
	<input type="hidden" name="package_id" value="'. $package_id .'" />
	<p>You are about to cancel the DOI request for this package!</p>
	<input type="checkbox" name="sure" value="1" />&nbsp;'._('I am Sure').'<br />
	<input type="checkbox" name="really_sure" value="1" />&nbsp;'._('I am Really Sure').'<br /><br />
	<input type="submit" name="submit" value="Cancel DOI Request" class="btn-cta" />
	</form>';

frs_admin_footer();
