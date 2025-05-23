<?php
/**
 * SVNPlugin.class.php
 *
 * FusionForge Subversion plugin
 *
 * Copyright 2003-2010, Roland Mas, Franck Villaume
 * Copyright 2004, GForge, LLC
 * Copyright 2010, Alain Peyrat <aljeux@free.fr>
 * Copyright 2012-2014,2016, Franck Villaume - TrivialDev
 * Copyright 2013, French Ministry of National Education
 * Copyright 2016-2025, SimTK Team
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

forge_define_config_item('default_server', 'scmsvn', forge_get_config ('web_host'));
forge_define_config_item('repos_path', 'scmsvn', forge_get_config('chroot').'/scmrepos/svn');
forge_define_config_item('use_ssh', 'scmsvn', false);
forge_set_config_item_bool('use_ssh', 'scmsvn');
forge_define_config_item('use_dav', 'scmsvn', true);
forge_set_config_item_bool('use_dav', 'scmsvn');
forge_define_config_item('use_ssl', 'scmsvn', true);
forge_set_config_item_bool('use_ssl', 'scmsvn');
forge_define_config_item('anonsvn_login','scmsvn', 'anonsvn');
forge_define_config_item('anonsvn_password','scmsvn', 'anonsvn');

class SVNPlugin extends SCMPlugin {
	var $text;
	var $svn_root_fs;
	var $svn_root_dav;

	function __construct() {
		parent::__construct();
		$this->name = 'scmsvn';
		$this->text = 'Subversion';
		//$this->svn_root_fs = '/scmrepos/svn';
		$this->svn_root_fs = '/svn';
		/*
		if (!file_exists($this->svn_root_fs.'/.')) {
			$this->svn_root_fs = forge_get_config('repos_path',
			    $this->name);
		}
		*/
		$this->svn_root_dav = '/svn';
		$this->_addHook('scm_browser_page');
		$this->_addHook('scm_update_repolist');
		$this->_addHook('scm_generate_snapshots');
		$this->_addHook('scm_gather_stats');
		$this->_addHook('activity');

		$this->provides['svn'] = true;

		$this->register();
	}

	function getDefaultServer() {
		return forge_get_config('default_server', 'scmsvn') ;
	}

	function printShortStats($params) {
		$project = $this->checkParams($params);
		if (!$project) {
			return;
		}

		if ($project->usesPlugin($this->name) && forge_check_perm('scm', $project->getID(), 'read')) {
			$result = db_query_params('SELECT sum(commits) AS commits, sum(adds) AS adds FROM stats_cvs_group WHERE group_id=$1',
						  array ($project->getID())) ;
			$commit_num = db_result($result,0,'commits');
			$add_num    = db_result($result,0,'adds');
			if (!$commit_num) {
				$commit_num=0;
			}
			if (!$add_num) {
				$add_num=0;
			}
			echo ' (Subversion: '.sprintf(_('<strong>%1$s</strong> updates, <strong>%2$s</strong> adds'), number_format($commit_num, 0), number_format($add_num, 0)).")";
		}
	}

	function getBlurb($project) {
		return '<p>'
		        . sprintf(_('For quick instructions on installing and using <a href="http://subversion.apache.org/">Subversion</a> (sometimes called "SVN") please click <a href="instructions.php?group_id=%1$s">here</a>.'), $project->getID())
				. '</p><p>'
				. sprintf(_('Documentation for %1$s is available at <a href="%2$s">%2$s</a>.'),
							'Subversion (“SVN”)',
							'http://svnbook.red-bean.com/')
				. '</p>';
	}

	function topModule($project) {
		// Check toplevel module presence
		$repo = 'file://' . forge_get_config('repos_path', $this->name).'/'.$project->getUnixName().'/';
		$res = array ();
		//$module = 'trunk';
		$module = '';
		if (!(exec("svn ls '$repo'", $res) && in_array($module.'/', $res)))
		{
			$module = '';
		}

		return '/'.$module;
	}

	function getInstructionsForAnon($project) {
		$b = '<h2>' . _('Anonymous Subversion Access') . '</h2>';
		$b .= '<p>';
		$b .= _("This project's SVN repository can be checked out through anonymous access with the following command(s).");
		$b .= '</p>';

		$b .= '<p>' ;
		$module = $this->topModule($project);
		if (forge_get_config('use_ssh', 'scmsvn')) {
			$b .= '<tt>svn checkout svn://'.$this->getBoxForProject($project).$this->svn_root_fs.'/'.$project->getUnixName().$module.'</tt><br />';    	
		}
		if (forge_get_config('use_dav', 'scmsvn')) {
			$b .= '<tt>svn checkout --username '.forge_get_config('anonsvn_login', 'scmsvn').' http'.((forge_get_config('use_ssl', 'scmsvn')) ? 's' : '').'://' . $this->getBoxForProject($project). $this->svn_root_dav .'/'. $project->getUnixName() .$module.'</tt><br />';
			$b .= _('The password is ').forge_get_config('anonsvn_password', 'scmsvn').'<br />';
		}
		$b .= '</p>';
		return $b;
	}

	function getInstructionsForRW($project) {
		$b = '';

		$module = $this->topModule($project);

		if (session_loggedin()) {
			$u = user_get_object(user_getid());
			$d = $u->getUnixName() ;
			if (forge_get_config('use_ssh', 'scmsvn')) {
				$b .= '<h2>';
				$b .= sprintf(_('Developer %s Access via SSH'), 'Subversion');
				$b .= '</h2>';
				$b .= '<p>';
				$b .= sprintf(_('Only project developers can access the %s tree via this method.'), 'Subversion');
				$b .= ' ';
				$b .= _('SSH must be installed on your client machine.');
				$b .= ' ';
				$b .= _('Enter your site password when prompted.');
				$b .= '</p>';
				$b .= '<p><tt>svn checkout svn+ssh://'.$d.'@' . $this->getBoxForProject($project) . $this->svn_root_fs .'/'. $project->getUnixName().$module.'</tt></p>' ;
			}
			if (forge_get_config('use_dav', 'scmsvn')) {
				$b .= '<h2>';
				$b .= _('Developer Subversion Access via DAV');
				$b .= '</h2>';
				$b .= '<p>';
				$b .= sprintf(_('Only project developers can access the %s tree via this method.'), 'Subversion');
				$b .= ' ';
				$b .= _('Enter your site password when prompted.');
				$b .= '</p>';
				$b .= '<p><tt>svn checkout --username '.$d.' http'.((forge_get_config('use_ssl', 'scmsvn')) ? 's' : '').'://'. $this->getBoxForProject($project) . $this->svn_root_dav .'/'.$project->getUnixName().$module.'</tt></p>' ;
			}
		} else {
			if (forge_get_config('use_ssh', 'scmsvn')) {
				$b .= '<h2>';
				$b .= sprintf(_('Developer %s Access via SSH'), 'Subversion');
				$b .= '</h2>';
				$b .= '<p>';
				$b .= sprintf(_('Only project developers can access the %s tree via this method.'), 'Subversion');
				$b .= ' ';
				$b .= _('SSH must be installed on your client machine.');
				$b .= ' ';
				$b .= _('Substitute <em>developername</em> with the proper value.');
				$b .= ' ';
				$b .= _('Enter your site password when prompted.');
				$b .= '</p>';
				$b .= '<p><tt>svn checkout svn+ssh://<i>'._('developername').'</i>@' . $this->getBoxForProject($project) . $this->svn_root_fs .'/'. $project->getUnixName().$module.'</tt></p>' ;
			}
			if (forge_get_config('use_dav', 'scmsvn')) {
				$b .= '<h2>';
				$b .= _('Developer Subversion Access via DAV');
				$b .= '</h2>';
				$b .= '<p>';
				$b .= sprintf(_('Only project developers can access the %s tree via this method.'), 'Subversion');
				$b .= ' ';
				$b .= _('Substitute <em>developername</em> with the proper value.');
				$b .= ' ';
				$b .= _('Enter your site password when prompted.');
				$b .= '</p>';
				$b .= '<p><tt>svn checkout --username <i>'._('developername').'</i> http'.((forge_get_config('use_ssl', 'scmsvn')) ? 's' : '').'://'. $this->getBoxForProject($project) . $this->svn_root_dav .'/'.$project->getUnixName().$module.'</tt></p>' ;
			}
		}
		return $b;
	}

	function getSnapshotPara($project) {
		return ;
	}

	function getBrowserLinkBlock($project) {
		global $HTML ;
		$b = '<h2>';
		$b .= $HTML->boxMiddle(sprintf(_('%s Repository Browser'), 'Subversion'));
		$b .= '</h2>';
		$b .= '<p>';
		$b .= sprintf(_("Browsing the %s tree gives you a view into the current status of this project's code."), 'Subversion');
		$b .= ' ';
		//$b .= _('You may also view the complete histories of any file in the repository.');
		$b .= '</p>';
		$b .= '<p>[' ;
		//$b .= util_make_link ("/scm/browser.php?group_id=".$project->getID(),
		//						sprintf(_('Browse %s Repository'), 'Subversion')
		//	) ;
		$b .= util_make_link ("/svn/".$project->getUnixName()."/",
								sprintf(_('Browse %s Repository'), 'Subversion')
			) ;	
		$b .= ']</p>' ;
		return $b ;
	}

	function getStatsBlock($project) {
		global $HTML ;
		$b = '' ;

		$result = db_query_params('SELECT u.realname, u.user_name, u.user_id, sum(commits) as commits, sum(adds) as adds, sum(adds+commits) as combined FROM stats_cvs_user s, users u WHERE group_id=$1 AND s.user_id=u.user_id AND (commits>0 OR adds >0) GROUP BY u.user_id, realname, user_name, u.user_id ORDER BY combined DESC, realname',
					  array ($project->getID()));

		if (db_numrows($result) > 0) {
			$b .= $HTML->boxMiddle(_('Repository Statistics'));

			$tableHeaders = array(
				_('Name'),
				_('Adds'),
				_('Updates')
				);
			$b .= $HTML->listTableTop($tableHeaders);

			$i = 0;
			$total = array('adds' => 0, 'commits' => 0);

			while($data = db_fetch_array($result)) {
				$b .= '<tr '. $HTML->boxGetAltRowStyle($i) .'>';
				$b .= '<td width="50%">' ;
				$b .= util_make_link_u ($data['user_name'], $data['user_id'], $data['realname']) ;
				$b .= '</td><td width="25%" align="right">'.$data['adds']. '</td>'.
					'<td width="25%" align="right">'.$data['commits'].'</td></tr>';
				$total['adds'] += $data['adds'];
				$total['commits'] += $data['commits'];
				$i++;
			}
			$b .= '<tr '. $HTML->boxGetAltRowStyle($i) .'>';
			$b .= '<td width="50%"><strong>'._('Total')._(':').'</strong></td>'.
				'<td width="25%" align="right"><strong>'.$total['adds']. '</strong></td>'.
				'<td width="25%" align="right"><strong>'.$total['commits'].'</strong></td>';
			$b .= '</tr>';
			$b .= $HTML->listTableBottom();
		}

		return $b;
	}

	function printBrowserPage($params) {
		$project = $this->checkParams($params);
		if (!$project) {
			return;
		}

		if ($project->usesPlugin($this->name)) {
			if ($this->browserDisplayable($project)) {
				session_redirect("/scm/viewvc.php/?root=".$project->getUnixName());
			}
		}
	}

	function createOrUpdateRepo($params) {
		$project = $this->checkParams($params);
		if (!$project) {
			return false;
		}

		if (! $project->usesPlugin($this->name)) {
			return false;
		}

		$repo_prefix = forge_get_config('repos_path', 'scmsvn');
		if (!is_dir($repo_prefix) && !mkdir($repo_prefix, 0755, true)) {
			return false;
		}

		$repo = $repo_prefix . '/' . $project->getUnixName();

		if (!is_dir ($repo) || !is_file ("$repo/format")) {
			if (!mkdir($repo, 0700, true)) {
				return false;
			}
			$ret = 0;
			system ("svnadmin create $repo", $ret);
			if ($ret != 0) {
				return false;
			}
			system ("sed -i '/enable-rep-sharing = false/s/^. //' $repo/db/fsfs.conf") ;
			system ("svn mkdir -m'Init' file:///$repo/trunk file:///$repo/tags file:///$repo/branches >/dev/null") ;
			if (forge_get_config('use_ssh', 'scmsvn')) {
				$unix_group = 'scm_' . $project->getUnixName() ;
				system ("find $repo -type d | xargs -I{} chmod g+s {}") ;
				if ($project->enableAnonSCM()) {
					system ("chmod -R g+rwX,o+rX-w $repo") ;
				} else {
					system ("chmod -R g+rwX,o-rwx $repo") ;
				}
				system ("chgrp -R $unix_group $repo") ;
			} else {
				$unix_user = forge_get_config('apache_user');
				$unix_group = forge_get_config('apache_group');
				system ("chmod -R g-rwx,o-rwx $repo") ;
				system ("chown -R $unix_user:$unix_group $repo") ;
			}
		}

		if (forge_get_config('use_ssh', 'scmsvn')) {
			$unix_group = 'scm_' . $project->getUnixName();
			system("find $repo -type d | xargs -I{} chmod g+s {}");
			if (forge_get_config('use_dav', 'scmsvn')) {
				$unix_user = forge_get_config('apache_user');
				system("chown $unix_user:$unix_group $repo");
			} else {
				system("chgrp $unix_group $repo");
			}
			if ($project->enableAnonSCM()) {
				system("chmod g+rwX,o+rX-w $repo") ;
			} else {
				system("chmod g+rwX,o-rwx $repo") ;
			}
		} else {
			$unix_user = forge_get_config('apache_user');
			$unix_group = forge_get_config('apache_group');
			system("chown $unix_user:$unix_group $repo") ;
			system("chmod g-rwx,o-rwx $repo") ;
		}
	}

	function updateRepositoryList(&$params) {
		$groups = $this->getGroups();

		// Update WebDAV stuff
		if (!forge_get_config('use_dav', 'scmsvn')) {
			return true;
		}

		$access_data = '';
		$password_data = '';
		$engine = RBACEngine::getInstance() ;

		$svnusers = array();
		foreach ($groups as $project) {
			if ( !$project->isActive()) {
				continue;
			}
			if ( !$project->usesSCM()) {
				continue;
			}
			$access_data .= '[' . $project->getUnixName() . ":/]\n";

			$users = $engine->getUsersByAllowedAction('scm',$project->getID(),'read');
			if ($users === false) {
				$params['output'] .= $engine->getErrorMessage();
				return false;
			}
			foreach ($users as $user) {
				$svnusers[$user->getID()] = $user;
				if (forge_check_perm_for_user($user,
							       'scm',
							       $project->getID(),
							       'write')) {
					$access_data .= $user->getUnixName() . "= rw\n";
				} else {
					$access_data .= $user->getUnixName() . "= r\n";
				}
			}

			if ($project->enableAnonSCM()) {
				$access_data .= forge_get_config('anonsvn_login', 'scmsvn')." = r\n";
				$access_data .= "* = r\n";
			}

			$access_data .= "\n";
			$engine->invalidateRoleCaches();  // caching all roles takes ~1GB RAM for 5K projects/15K users
		}

		foreach ($svnusers as $user_id => $user) {
			$password_data .= $user->getUnixName().':'.$user->getUnixPasswd()."\n";
		}
		$password_data .= forge_get_config('anonsvn_login', 'scmsvn').":".htpasswd_apr1_md5(forge_get_config('anonsvn_password', 'scmsvn'))."\n";

		$fname = forge_get_config('data_path').'/svnroot-authfile';
		$f = fopen($fname.'.new', 'w');
		fwrite($f, $password_data);
		fclose($f);
		chmod($fname.'.new', 0644);
		rename($fname.'.new', $fname);

		$fname = forge_get_config('data_path').'/svnroot-access';
		$f = fopen($fname.'.new', 'w');
		fwrite($f, $access_data);
		fclose($f);
		chmod($fname.'.new', 0644);
		rename($fname.'.new', $fname);
	}

	function gatherStats($params) {
		global $last_user, $last_time, $last_tag, $time_ok, $start_time, $end_time,
			$adds, $deletes, $updates, $commits, $date_key,
			$usr_adds, $usr_deletes, $usr_updates;

		$time_ok = true;

		$project = $this->checkParams($params);
		if (!$project) {
			return false;
		}

		if (! $project->usesPlugin($this->name)) {
			return false;
		}

		if ($params['mode'] == 'day') {
			db_begin();

			$year = $params['year'];
			$month = $params['month'];
			$day = $params['day'];
			$month_string = sprintf("%04d%02d", $year, $month);
			$start_time = gmmktime(0, 0, 0, $month, $day, $year);
			$end_time = $start_time + 86400;

			$adds    = 0;
			$updates = 0;
			$usr_adds    = array();
			$usr_updates = array();

			$repo = forge_get_config('repos_path', 'scmsvn') . '/' . $project->getUnixName();
			if (!is_dir ($repo) || !is_file ("$repo/format")) {
				echo "No repository $repo\n";
				db_rollback();
				return false;
			}

			$d1 = date('Y-m-d', $start_time - 150000);
			$d2 = date('Y-m-d', $end_time + 150000);

			$pipe = popen ("svn log file://$repo --xml -v -q -r '".'{'.$d2.'}:{'.$d1.'}'."' 2> /dev/null", 'r' ) ;

			// cleaning stats_cvs_* table for the current day
			$res = db_query_params('DELETE FROM stats_cvs_group WHERE month=$1 AND day=$2 AND group_id=$3',
						array($month_string,
						       $day,
						       $project->getID()));
			if(!$res) {
				echo "Error while cleaning stats_cvs_group\n" ;
				db_rollback();
				return false;
			}

			$res = db_query_params ('DELETE FROM stats_cvs_user WHERE month=$1 AND day=$2 AND group_id=$3',
						array ($month_string,
						       $day,
						       $project->getID())) ;
			if(!$res) {
				echo "Error while cleaning stats_cvs_user\n" ;
				db_rollback () ;
				return false ;
			}

			$xml_parser = xml_parser_create();
			xml_set_element_handler($xml_parser, "SVNPluginStartElement", "SVNPluginEndElement");
			xml_set_character_data_handler($xml_parser, "SVNPluginCharData");

			// Analyzing history stream
			while (!feof($pipe) &&
				$data = fgets ($pipe, 4096)) {
				if (!xml_parse ($xml_parser, $data, feof ($pipe))) {
					debug("Unable to parse XML with error " .
					      xml_error_string(xml_get_error_code($xml_parser)) .
					      " on line " .
					      xml_get_current_line_number($xml_parser));
					db_rollback () ;
					return false ;
					break;
				}
			}

			xml_parser_free($xml_parser);

			// inserting group results in stats_cvs_groups
			if ($updates > 0 || $adds > 0) {
				if (!db_query_params('INSERT INTO stats_cvs_group (month,day,group_id,checkouts,commits,adds) VALUES ($1,$2,$3,$4,$5,$6)',
						      array ($month_string,
							     $day,
							     $project->getID(),
							     0,
							     $updates,
							     $adds))) {
					echo "Error while inserting into stats_cvs_group\n" ;
					db_rollback();
					return false;
				}
			}

			// building the user list
			$user_list = array_unique( array_merge( array_keys( $usr_adds ), array_keys( $usr_updates ) ) );

			foreach ( $user_list as $user ) {
				// trying to get user id from user name
				$u = user_get_object_by_name ($user) ;
				if ($u) {
					$user_id = $u->getID();
				} else {
					continue;
				}

				$uu = isset($usr_updates[$user]) ? $usr_updates[$user] : 0 ;
				$ua = isset($usr_adds[$user]) ? $usr_adds[$user] : 0 ;
				if ($uu > 0 || $ua > 0) {
					if (!db_query_params ('INSERT INTO stats_cvs_user (month,day,group_id,user_id,commits,adds) VALUES ($1,$2,$3,$4,$5,$6)',
							      array ($month_string,
								     $day,
								     $project->getID(),
								     $user_id,
								     $uu,
								     $ua))) {
						echo "Error while inserting into stats_cvs_user\n" ;
						db_rollback () ;
						return false ;
					}
				}
			}
			db_commit();
		}
	}

	function generateSnapshots($params) {

		$project = $this->checkParams($params);
		if (!$project) {
			return false;
		}

		$group_name = $project->getUnixName();

		$snapshot = forge_get_config('scm_snapshots_path').'/'.$group_name.'-scm-latest.tar'.util_get_compressed_file_extension();
		$tarball = forge_get_config('scm_tarballs_path').'/'.$group_name.'-scmroot.tar'.util_get_compressed_file_extension();

		if (! $project->usesPlugin($this->name)) {
			return false;
		}

		if (! $project->enableAnonSCM()) {
			if (is_file($snapshot)) {
				unlink ($snapshot);
			}
			if (is_file($tarball)) {
				unlink ($tarball);
			}
			return false;
		}

		$toprepo = forge_get_config('repos_path', 'scmsvn');
		$repo = $toprepo . '/' . $project->getUnixName();

		if (!is_dir ($repo) || !is_file ("$repo/format")) {
			if (is_file($snapshot)) {
				unlink ($snapshot) ;
			}
			if (is_file($tarball)) {
				unlink ($tarball) ;
			}
			return false ;
		}

		//$tmp = trim (`mktemp -d`) ;
		$tmp = trim (`mktemp -d --tmpdir=/var/tmp/svn_tmp_dir`) ;
		if ($tmp == '') {
			return false ;
		}
		$today = date ('Y-m-d') ;
		$dir = $project->getUnixName ()."-$today" ;
		system ("mkdir -p $tmp") ;
		$code = 0 ;
		system ("svn ls file://$repo/trunk > /dev/null 2> /dev/null", $code) ;
		if ($code == 0) {
			system ("cd $tmp ; svn export file://$repo/trunk $dir > /dev/null 2>&1") ;
			system ("tar cCf $tmp - $dir |".forge_get_config('compression_method')."> $tmp/snapshot") ;
			chmod ("$tmp/snapshot", 0644) ;
			copy ("$tmp/snapshot", $snapshot) ;
			unlink ("$tmp/snapshot") ;
			system ("rm -rf $tmp/$dir") ;
		} else {
			if (is_file($snapshot)) {
				unlink ($snapshot) ;
			}
		}

		system ("tar cCf $toprepo - ".$project->getUnixName() ."|".forge_get_config('compression_method')."> $tmp/tarball") ;
		chmod ("$tmp/tarball", 0644) ;
		copy ("$tmp/tarball", $tarball) ;
		unlink ("$tmp/tarball") ;
		system ("rm -rf $tmp") ;
	}

	function activity($params) {
		global $last_user, $last_time, $last_tag, $time_ok, $start_time, $end_time,
			$adds, $deletes, $updates, $commits, $date_key,
			$usr_adds, $usr_deletes, $usr_updates, $old_commit,
			$messages, $last_message, $times, $revisions, $users;
		$group_id = $params['group'];
		$project = group_get_object($group_id);
		if (! $project->usesPlugin($this->name)) {
			return false;
		}

		if (in_array('scmsvn', $params['show']) || (count($params['show']) < 1)) {
			$commits = 0;
			$old_commit = -1;
			$start_time = $params['begin'];
			$end_time = $params['end'];
			$d1 = date('Y-m-d', $start_time - 80000);
			$d2 = date('Y-m-d', $end_time + 80000);

			$repo = forge_get_config('repos_path', 'scmsvn') . '/' . $project->getUnixName();
			$pipe = popen("svn log file://$repo --xml -v -r '".'{'.$d2.'}:{'.$d1.'}'."' 2> /dev/null", 'r' );
			$xml_parser = xml_parser_create();
			xml_set_element_handler($xml_parser, "SVNPluginStartElement", "SVNPluginEndElement");
			xml_set_character_data_handler($xml_parser, "SVNPluginCharData");
			while (!feof($pipe) && $data = fgets($pipe, 4096)) {
				if (!xml_parse($xml_parser, $data, feof ($pipe))) {
					debug("Unable to parse XML with error " .
						xml_error_string(xml_get_error_code($xml_parser)) .
						" on line " .
						xml_get_current_line_number($xml_parser));
					return false;
					break;
				}
			}
			xml_parser_free($xml_parser);
			if ($adds > 0 || $updates > 0) {
				$i = 0;
				foreach ($messages as $message) {
					$result = array();
					$result['section'] = 'scm';
					$result['group_id'] = $group_id;
					$result['ref_id'] = 'viewvc.php/?root='.$project->getUnixName();
					$result['description'] = htmlspecialchars($message).' (r'.$revisions[$i].')';
					$result['user_name'] = $users[$i];
					$userObject = user_get_object_by_name($users[$i]);
					if (is_a($userObject, 'GFUser')) {
						$result['realname'] = $userObject->getFirstName().' '.$userObject->getLastName();
						$result['user_id'] = $userObject->getId();
					} else {
						$result['realname'] = '';
						$result['user_id'] = '';
					}
					$result['activity_date'] = $times[$i];
					$result['subref_id'] = '&view=rev&revision='.$revisions[$i];
					$params['results'][] = $result;
					$i++;
				}
			}
		}
		$params['ids'][] = $this->name;
		$params['texts'][] = _('Source Code Commits');
		return true;
	}
}

// End of class, helper functions now

function SVNPluginCharData($parser, $chars) {
	global $last_tag, $last_user, $last_time, $start_time, $end_time, $old_commit, $commits,
		$time_ok, $user_list, $last_message, $messages, $times, $users;
	switch ($last_tag) {
		case "AUTHOR": {
			$last_user = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($chars)));
			$users[] = $last_user;
			break;
		}
		case "DATE": {
			$chars = preg_replace('/T(\d\d:\d\d:\d\d)\.\d+Z?$/', ' ${1}', $chars);
			$last_time = strtotime($chars);
			if ($start_time <= $last_time && $last_time < $end_time) {
				$time_ok = true;
			} else {
				$time_ok = false;
			}
			$times[] = $last_time;
			break;
		}
		case "MSG": {
			/* If commit id is the same, then concatenate the string with the previous
+			 * (happen when the message contain accents).
+			 */
			if ($old_commit == $commits) {
				$messages[count($messages)-1] .= $chars;
			} else {
				$messages[] = $chars;
			}
			$old_commit = $commits;
			break;
		}
	}
}

function SVNPluginStartElement($parser, $name, $attrs) {
	global $last_user, $last_time, $last_tag, $time_ok,
		$adds, $updates, $usr_adds, $usr_updates, $last_message, $messages, $times, $revisions;
	$last_tag = $name;
	switch($name) {
		case "LOGENTRY": {
			// Make sure we clean up before doing a new log entry
			$last_user = "";
			$last_time = "";
			$revisions[] = $attrs['REVISION'];
			break;
		}
		case "PATH": {
			if ($time_ok) {
				if ($attrs['ACTION'] == "M") {
					$updates++;
					if ($last_user) {
						$usr_updates[$last_user] = isset($usr_updates[$last_user]) ? ($usr_updates[$last_user]+1) : 1 ;
					}
				} elseif ($attrs['ACTION'] == "A") {
					$adds++;
					if ($last_user) {
						$usr_adds[$last_user] = isset($usr_adds[$last_user]) ? ($usr_adds[$last_user]+1) : 1 ;
					}
				}
			}
			break;
		}
	}
}

function SVNPluginEndElement($parser, $name) {
	global $time_ok, $last_tag, $commits;
	if ($name == "LOGENTRY" && $time_ok) {
		$commits++;
	}
	$last_tag = "";
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
