<?php
/**
 * Survey Facility
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2002-2004 (c) GForge Team
 * Copyright (C) 2010 Alain Peyrat - Alcatel-Lucent
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

$HTML->header(array('title'=>_('Voting')));

if (!session_loggedin()) {
	exit_not_logged_in();
} else {
	$vote_on_id = getIntFromRequest('vote_on_id');
	$response = getStringFromRequest('response');
	$flag = getStringFromRequest('flag');

	if ($vote_on_id && $response && $flag) {
		/*
			$flag
			1=project
			2=release
		*/
		$toss = db_query_params ('DELETE FROM survey_rating_response WHERE user_id=$1 AND type=$2 AND id=$3',
					 array(user_getid(),
					       $flag,
					       $vote_on_id));

		$result = db_query_params ('INSERT INTO survey_rating_response (user_id,type,id,response,post_date) VALUES ($1,$2,$3,$4,$5)',
					   array(user_getid(),
						 $flag,
						 $vote_on_id,
						 $response,
						 time()));
		if (!$result) {
			$error_msg .= _('Insert Error')._(': ').db_error();
			session_redirect('/');
		} else {
			$feedback .= _('Vote registered');
			$warning_msg .= _('If you vote again, your old vote will be erased.');
			session_redirect('/');
		}
	} else {
		exit_missing_param('',array(_('Vote ID'),_('Response'),_('Flag')),'survey');
	}
}
$HTML->footer();
