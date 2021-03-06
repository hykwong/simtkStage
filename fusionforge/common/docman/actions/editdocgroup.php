<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 2000, Quentin Cregan/Sourceforge
 * Copyright 2002-2003, Tim Perdue/GForge, LLC
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2013, Franck Villaume - TrivialDev
 * Copyright 2016-2021, SimTK Team
 * http://fusionforge.org
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

/* please do not add require here : use www/docman/index.php to add require */
/* global variables used */
global $g; //group object
global $dirid; //id of doc_group
global $group_id; // id of group
global $childgroup_id; // plugin projects hierarchy handler

$urlredirect = '/docman/?group_id='.$group_id.'&view=listfile&dirid='.$dirid;

// plugin projects-hierarchy handler
if ($childgroup_id) {
	$g = group_get_object($childgroup_id);
	$urlredirect = '/docman/?group_id='.$group_id.'&view=listfile&dirid='.$dirid.'&childgroup_id='.$childgroup_id;
}

if (!forge_check_perm('docman', $g->getID(), 'approve')) {
	$return_msg = _('Document Manager Action Denied.');
	session_redirect('/docman/?group_id='.$group_id.'&view=listfile&dirid='.$dirid.'&warning_msg='.urlencode($return_msg));
}

$groupname = htmlspecialchars(getStringFromRequest('groupname'));
$parent_dirid = getIntFromRequest('parent_dirid');
$dg = new DocumentGroup($g, $dirid);
if ($dg->isError())
	session_redirect($urlredirect.'&error_msg='.urlencode($dg->getErrorMessage()));

if (!$dg->update($groupname, $parent_dirid))
	session_redirect($urlredirect.'&error_msg='.urlencode($dg->getErrorMessage()));

if ($dg->getState() == 2) {
	/**
	 * we need to update stateid for the content
	 * Get the document groups info
	 */
	$df = new DocumentFactory($g);
	if ($df->isError())
		exit_error($df->getErrorMessage(), 'docman');

	$dgf = new DocumentGroupFactory($g);
	if ($dgf->isError())
		exit_error($dgf->getErrorMessage(), 'docman');

	$trashnested_groups =& $dgf->getNested(2);

	$df->setDocGroupID($dirid);
	$d_arr =& $df->getDocuments();

	$trashnested_docs = array();
	/* put the doc objects into an array keyed of the docgroup */
	if (is_array($d_arr)) {
		foreach ($d_arr as $doc) {
			$trashnested_docs[$doc->getDocGroupID()][] = $doc;
		}
	}

	if (is_array($trashnested_groups[$dirid])) {
		foreach ($trashnested_groups[$dirid] as $ndg) {
			$localdf = new DocumentFactory($g);
			$localdf->setDocGroupID($ndg->getID());
			$d_arr =& $localdf->getDocuments();
			if (is_array($d_arr)) {
				foreach ($d_arr as $doc) {
					$trashnested_docs[$doc->getDocGroupID()][] = $doc;
				}
			}
		}
	}

	docman_recursive_stateid($dirid, $trashnested_groups, $trashnested_docs, 1);
}

if (!$dg->setStateID('1'))
	session_redirect($urlredirect.'&error_msg='.urlencode($dg->getErrorMessage()));

$return_msg = sprintf(_('Documents folder %s updated successfully'), $dg->getName());
if ($childgroup_id)
	$return_msg .= ' '.sprintf(_('on project %s'), $g->getPublicName());

session_redirect($urlredirect.'&feedback='.urlencode($return_msg));
