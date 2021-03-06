<?php
/**
 * Search Engine
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2004 (c) Guillaume Smet / Open Wide
 * Copyright 2013, French Ministry of National Education
 * Copyright 2016-2019, Henry Kwong, Tod Hing - SimTK Team
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

require_once $gfwww.'search/include/renderers/HtmlSearchRenderer.class.php';
require_once $gfcommon.'search/ProjectSearchQuery.class.php';

class ProjectHtmlSearchRenderer extends HtmlSearchRenderer {

	/**
	 * Constructor
	 *
	 * @param string $words words we are searching for
	 * @param int $offset offset
	 * @param boolean $isExact if we want to search for all the words or if only one matching the query is sufficient
	 */
	function __construct($words, $offset, $isExact) {

		$searchQuery = new ProjectSearchQuery($words, $offset, $isExact);

		parent::__construct(SEARCH__TYPE_IS_SOFTWARE, $words, $isExact, $searchQuery);

		$this->tableHeaders = array(
			_('Project Name'),
			_('Description')
		);
	}

	/**
	 * writeHeader - write the header of the output
	 */
	function writeHeader() {
		$GLOBALS['HTML']->header(array('title'=>_('Project Search'), 'pagename'=>'search'));
		parent::writeHeader();
	}

	/**
	 * getRows - get the html output for result rows
	 *
	 * @return string html output
	 */
	function getRows() {
		$result =& $this->searchQuery->getResult();

		$return = '';
		$i = 0;

		while ($row = db_fetch_array($result)) {
			if (!forge_check_perm ('project_read', $row['group_id'])) {
				continue ;
			}
			$i++;
			if ($row['type_id'] == 2) {
				$what = 'foundry';
			} else {
				$what = 'projects';
			}
			$return .= '<tr '.$GLOBALS['HTML']->boxGetAltRowStyle($i).'>'
				.'<td width="30%"><a href="'.util_make_url('/'.$what.'/'.$row['unix_group_name'].'/').'">'
				.html_image('ic/msg.png', '10', '12')
				.' '.$this->highlightTargetWords($row['group_name']).'</a></td>'
				.'<td width="70%">'.$this->highlightTargetWords($row['short_description']).'</td></tr>';
		}

		return $return;
	}

	/**
	 * redirectToResult - redirect the user  directly to the result when there is only one matching result
	 */
	function redirectToResult() {

		$project_name = $this->getResultId('unix_group_name');
		$project_id = $this->getResultId('group_id');

		if (forge_get_config('use_fti')) {
			// If FTI is being used, the project name returned by the query will be "<b>projectname</b>", so
			// we remove the HTML code (otherwise we'd get an error)
			$project_name = str_replace('<b>', '', $project_name);
			$project_name = str_replace('</b>', '', $project_name);
		}

		if ($this->getResultId('type') == 2) {
			session_redirect('/foundry/'.$project_name.'/');
		} else {
			if (forge_check_perm ('project_read', $project_id)) {
				header('Location: '.util_make_url_g($project_name,$project_id));
			} else {
				$this->writeHeader();
				$html = '<h2>'.sprintf(_('Search results for “%s”'), $project_name).'</h2>';
				$html .= '<p><strong>'.sprintf(_('No matches found for “%s”'), $project_name).'</strong></p>';
				echo $html;
				$this->writeFooter();
			}
		}
		exit();
	}

}
