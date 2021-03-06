<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 2000, Quentin Cregan/Sourceforge
 * Copyright 2002-2003, Tim Perdue/GForge, LLC
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
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

$urlredirect = '/docman/?group_id='.$group_id;

// plugin projects-hierarchy handler
$childgroup_id = getIntFromRequest('childgroup_id');
if ($childgroup_id) {
	$g = group_get_object($childgroup_id);
	$urlredirect .= '&childgroup_id='.$childgroup_id;
}

if (!forge_check_perm('docman', $g->getID(), 'approve')) {
	$return_msg = _('Document Manager Action Denied.');
	session_redirect('/docman/?group_id='.$group_id.'&view=listfile&dirid='.$dirid.'&warning_msg='.urlencode($return_msg));
}

$dg = new DocumentGroup($g, $dirid);

if ($dg->isError())
	session_redirect($urlredirect.'&view=listfile&dirid='.$dirid.'&error_msg='.urlencode($dg->getErrorMessage()));

if (!$dg->delete($dirid, $g->getID()))
	session_redirect($urlredirect.'&view=listfile&dirid='.$dirid.'&error_msg='.urlencode($dg->getErrorMessage()));

if ($dg->getState() != 2) {
	$parentId = $dg->getParentID();
	$view='listfile';
} else {
	$parentId = 0;
	$view='listtrashfile';
}

$return_msg = sprintf(_('Document folder %s deleted successfully.'),$dg->getName());
session_redirect($urlredirect.'&view='.$view.'&dirid='.$parentId.'&feedback='.urlencode($return_msg));
