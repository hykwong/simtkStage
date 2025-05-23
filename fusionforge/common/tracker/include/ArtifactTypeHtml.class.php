<?php
/**
 * FusionForge Generic Tracker facility
 *
 * Copyright 1999-2001 (c) VA Linux Systems - Sourceforge
 * Copyright 2010 (c) Fusionforge Team
 * Copyright 2011, Franck Villaume - Capgemini
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2014, Franck Villaume - TrivialDev
 * Copyright 2016-2025, SimTK Team
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

require_once $gfcommon.'tracker/ArtifactType.class.php';
require_once $gfcommon.'tracker/ArtifactExtraField.class.php';
require_once $gfcommon.'tracker/ArtifactExtraFieldElement.class.php';
require_once $gfcommon.'tracker/ArtifactWorkflow.class.php';
require_once $gfcommon.'include/utils_crossref.php';

class ArtifactTypeHtml extends ArtifactType {

	function header($params) {
		global $HTML;
		if (!forge_get_config('use_tracker')) {
			exit_disabled();
		}
		$group_id= $this->Group->getID();

		//required by new site_project_header
		$params['group']=$group_id;
		$params['toptab']='tracker';
		$params['tabtext']=$this->getName();

		$labels = array();
		$links  = array();
		$attr   = array();

		$labels[] = $this->getName() . " List";
		$links[]  = '/tracker/?func=browse&group_id='.$group_id.'&atid='. $this->getID();
		$attr[]   = array('title' => _('Browse this tracker.'));
		$labels[] = _("View All Lists");
		$links[]  = '/tracker/?group_id='.$group_id;
		$attr[]   = array('title' => _('Get the list of available trackers'));
/*
		$labels[] = _('Export CSV');
		$links[]  = '/tracker/?func=csv&group_id='.$group_id.'&atid='. $this->getID();
		$attr[]   = array('title' => _('Download data from this tracker as csv file.'));
*/
		if (forge_check_perm ('tracker',$this->getID(),'submit')) {
			$labels[] = _('Submit New');
			$links[]  = '/tracker/?func=add&group_id='.$group_id.'&atid='. $this->getID();
			$attr[]   = array('title' => _('Add a new issue.'));
		}

		if (session_loggedin()) {
/*
			$labels[] = _('Reporting');
			$links[]  = '/tracker/reporting/?group_id='.$group_id.'&atid='. $this->getID();
			$attr[]   = array('title' => _('Various graph about statistics.'));
*/
			if ($this->isMonitoring()) {
				$labels[] = _('Stop Follow');
				$links[]  = '/tracker/?group_id='.$group_id.'&atid='. $this->getID().'&func=monitor&stopmonitor=1';
				$attr[]   = array('title' => _('Remove this tracker from your monitoring.'));
			} else {
				$labels[] = _('Follow');
				$links[]  = '/tracker/?group_id='.$group_id.'&atid='. $this->getID().'&func=monitor&startmonitor=1';
				$attr[]   = array('title' => _('Add this tracker from your monitoring.'));
			}

			if (forge_check_perm ('tracker', $this->getID(), 'manager')) {
				$labels[] = _('Administration');
				$links[]  = '/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID();
				$attr[]   = array('title' => _('Global administration for trackers. Create, clone, workflow, fields ...'));
			}
		}

		$params['submenu'] = $HTML->subMenu($labels, $links, $attr);
		site_project_header($params);

		if ($this)
			plugin_hook("blocks", "tracker_".$this->getName());

	}

	function footer($params = array()) {
		site_project_footer($params);
	}

	function adminHeader($params) {
		global $HTML;
		$this->header($params);
		$group_id= $this->Group->getID();

/*
		$links_arr[]='/tracker/admin/?group_id='.$group_id;
		$title_arr[]=_('New Tracker');
*/

		$links_arr[]='/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID().'&update_type=1';
		$title_arr[]=_('Update Settings');

/*
		$links_arr[]='/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID().'&add_extrafield=1';
		$title_arr[]=_('Manage Custom Fields');

		$links_arr[]='/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID().'&workflow=1';
		$title_arr[]=_('Manage Workflow');

		$links_arr[]='/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID().'&customize_list=1';
		$title_arr[]=_('Customize List');
*/

		$links_arr[]='/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID().'&add_canned=1';
		$title_arr[]=_('Add/Update Canned Responses');

/*
		$links_arr[]='/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID().'&clone_tracker=1';
		$title_arr[]=_('Clone Tracker');
*/

		$links_arr[]='/tracker/admin/?group_id='.$group_id.'&atid='.$this->getID().'&delete=1';
		$title_arr[]=_('Delete');

		//echo $HTML->printSubMenu($title_arr, $links_arr, array());
	}

	function adminFooter($params) {
		$this->footer($params);
	}

	function renderSubmitInstructions() {
		$msg = $this->getSubmitInstructions();
		return str_replace("\n","<br />", $msg);
	}

	function renderBrowseInstructions() {
		$msg = $this->getBrowseInstructions();
		return str_replace("\n","<br />", $msg);
	}

	/**
	 * renderExtraFields - ???
	 *
	 * @param	array	$selected
	 * @param	bool	$show_100		Display the specific '100' value. Default is false.
	 * @param	string	$text_100		Label displayed for the '100' value. Default is 'none'
	 * @param	bool	$show_any
	 * @param	string	$text_any
	 * @param	array	$types
	 * @param	bool	$status_show_100	Force display of the '100' value if needed. Default is false.
	 * @param	string	$mode
	 */
	function renderExtraFields($selected = array(),
                               $show_100 = false, $text_100 = 'none',
                               $show_any = false, $text_any = 'Any',
                               $types = array(),
                               $status_show_100 = false,
                               $mode = '') {
		$efarr = $this->getExtraFields($types);
		//each two columns, we'll reset this and start a new row

		$template = $this->getRenderHTML($types, $mode);

		if ($mode=='QUERY') {
			$keys=array_keys($efarr);
			for ($k=0; $k<count($keys); $k++) {
				$i=$keys[$k];
				if ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_SELECT ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_CHECKBOX ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_RADIO ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_STATUS ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_MULTISELECT) {
					$efarr[$i]['field_type'] = ARTIFACT_EXTRAFIELDTYPE_MULTISELECT;
				} else {
					$efarr[$i]['field_type'] = ARTIFACT_EXTRAFIELDTYPE_TEXT;
				}
			}
		}

		// 'DISPLAY' mode is for rendering in 'read-only' mode (for detail view).
		if ($mode === 'DISPLAY') {
			$keys=array_keys($efarr);
			for ($k=0; $k<count($keys); $k++) {
				$post_name = '';
				$i=$keys[$k];

				if (!isset($selected[$efarr[$i]['extra_field_id']]))
					$selected[$efarr[$i]['extra_field_id']] = '';

				$value = @$selected[$efarr[$i]['extra_field_id']];

				if ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_SELECT ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_CHECKBOX ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_RADIO ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_STATUS ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_MULTISELECT) {
					if ($value == 100) {
						$value = $efarr[$i]['show100label'];
					} else {
						$arr = $this->getExtraFieldElements($efarr[$i]['extra_field_id']);

						// Convert the values (ids) to names in the ids order.
						$new = array();
						for ($j=0; $j<count($arr); $j++) {
							if (is_array($value)) {
								if (in_array($arr[$j]['element_id'],$value))
									$new[]= $arr[$j]['element_name'];
							} elseif ($arr[$j]['element_id'] === $value) {
									$new[] = $arr[$j]['element_name'];
							}
						}
						$value = join('<br />', $new);
					}
				} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_TEXT ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_TEXTAREA) {
					$value = preg_replace('/((http|https|ftp):\/\/\S+)/',
								"<a href=\"\\1\" target=\"_blank\">\\1</a>", $value);
				} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_RELATION) {
					// Convert artifact id to links.
					$myFunc1 = function($matches) {
						return _artifactid2url($matches[1], 'title');
					};
					$value = preg_replace_callback('/\b(\d+)\b/', $myFunc1, $value);
				}
				$template = str_replace('{$PostName:'.$efarr[$i]['field_name'].'}', $post_name, $template);
				$template = str_replace('{$'.$efarr[$i]['field_name'].'}', $value, $template);
			}
			echo $template;
			return ;
		}

		$keys = array_keys($efarr);
		for ($k = 0; $k < count($keys); $k++) {
			$i = $keys[$k];
			$post_name = '';

			if (!isset($selected[$efarr[$i]['extra_field_id']]))
				$selected[$efarr[$i]['extra_field_id']] = '';

			if ($status_show_100) {
				$efarr[$i]['show100'] = $status_show_100;
			}

			if ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_SELECT) {
				$str = $this->renderSelect($efarr[$i]['extra_field_id'],$selected[$efarr[$i]['extra_field_id']],$efarr[$i]['show100'],$efarr[$i]['show100label'],$show_any,$text_any);

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_CHECKBOX) {

				$str = $this->renderCheckbox($efarr[$i]['extra_field_id'],$selected[$efarr[$i]['extra_field_id']],$efarr[$i]['show100'],$efarr[$i]['show100label']);

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_RADIO) {

				$str = $this->renderRadio($efarr[$i]['extra_field_id'],$selected[$efarr[$i]['extra_field_id']],$efarr[$i]['show100'],$efarr[$i]['show100label'],$show_any,$text_any);

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_TEXT ||
					$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_INTEGER) {

				$str = $this->renderTextField($efarr[$i]['extra_field_id'],$selected[$efarr[$i]['extra_field_id']],$efarr[$i]['attribute1'],$efarr[$i]['attribute2']);
				if ($mode == 'QUERY') {
					$post_name =  ' <i>'._('(%% for wildcards)').'</i>&nbsp;&nbsp;&nbsp;';
				}

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_TEXTAREA) {

				$str = $this->renderTextArea($efarr[$i]['extra_field_id'],$selected[$efarr[$i]['extra_field_id']],$efarr[$i]['attribute1'],$efarr[$i]['attribute2']);
				if ($mode == 'QUERY') {
					$post_name =  ' <i>'._('(%% for wildcards)').'</i>';
				}

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_MULTISELECT) {

				$str = $this->renderMultiSelectBox ($efarr[$i]['extra_field_id'],$selected[$efarr[$i]['extra_field_id']],$efarr[$i]['show100'],$efarr[$i]['show100label']);

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_STATUS) {

				// Get the allowed values from the workflow.
				$atw = new ArtifactWorkflow($this, $efarr[$i]['extra_field_id']);

				// Special treatement for the initial step (Submit).
				// In this case, the initial value is the first value.
				if ($selected === true) {
					$selected_node = 100;
				} elseif (isset($selected[$efarr[$i]['extra_field_id']]) && $selected[$efarr[$i]['extra_field_id']]) {
					$selected_node = $selected[$efarr[$i]['extra_field_id']];
				} else {
					$selected_node = 100;
				}

				$allowed = $atw->getNextNodes($selected_node);
				$allowed[] = $selected_node;
				$str = $this->renderSelect($efarr[$i]['extra_field_id'],$selected_node,$status_show_100,$text_100,$show_any,$text_any, $allowed);

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_RELATION) {

				$str = $this->renderRelationField($efarr[$i]['extra_field_id'],$selected[$efarr[$i]['extra_field_id']],$efarr[$i]['attribute1'],$efarr[$i]['attribute2']);
				if ($mode == 'UPDATE') {
					$post_name = html_image('ic/forum_edit.gif','37','15',array('title'=>"Click to edit", 'alt'=>"Click to edit", 'onclick'=>"switch2edit(this, 'show$i', 'edit$i')"));
				}
			}
			$template = str_replace('{$PostName:'.$efarr[$i]['field_name'].'}',$post_name,$template);
			$template = str_replace('{$'.$efarr[$i]['field_name'].'}',$str,$template);
		}
		if($template != NULL){
			echo $template;
		}
	}

	function renderRelatedTasks($group, $ah) {

		if (!$group->usesPM()) {
			return;
		}

		$taskcount = db_numrows($ah->getRelatedTasks());
		db_result_reset($ah->getRelatedTasks());

		if (forge_check_perm('tracker_admin', $ah->ArtifactType->Group->getID())) {
			$is_admin=true;
		} else {
			$is_admin=false;
		}

		$totalPercentage = 0;

		if ($taskcount > 0) {
			echo '<tr><td colspan="2">';
			echo '<b>'._("Related Tasks")._(':').'</b><br/>';
			$title_arr = array();
			$title_arr[] = _('Task Id and Summary');
			$title_arr[] = _('Progress');
			$title_arr[] = _('Start Date');
			$title_arr[] = _('End Date');
			$title_arr[] = _('Status');
			(($is_admin) ? $title_arr[]=_('Remove Relation') : '');
			echo $GLOBALS['HTML']->listTableTop($title_arr);

			for ($i = 0; $i < $taskcount; $i++) {
				$taskinfo  = db_fetch_array($ah->relatedtasks, $i);
				$totalPercentage += $taskinfo['percent_complete'];
				$taskid    = $taskinfo['project_task_id'];
				$projectid = $taskinfo['group_project_id'];
				$groupid   = $taskinfo['group_id'];
				$summary   = util_unconvert_htmlspecialchars($taskinfo['summary']);
				$startdate = date(_('Y-m-d H:i'), $taskinfo['start_date']);
				$enddate   = date(_('Y-m-d H:i'), $taskinfo['end_date']);
				$status   = $taskinfo['status_name'];
				echo '<tr>
						<td>'.util_make_link('/pm/task.php?func=detailtask&project_task_id='.$taskid.'&group_id='.$groupid.'&group_project_id='.$projectid, '[T'.$taskid.'] '.$summary).'</td>
						<td><div class="percentbar" style="width: 100px;">
							<div style="width:'.round($taskinfo['percent_complete']).'px;"></div></div></td>
						<td>'.$startdate.'</td>
						<td>'.$enddate.'</td>
						<td>'.$status.' ('.$taskinfo['percent_complete'].'%)</td>'.
					(($is_admin) ? '<td><input type="checkbox" name="remlink[]" value="'.$taskid.'" /></td>' : '').
					'</tr>';
			}
			echo $GLOBALS['HTML']->listTableBottom();

			echo "\n<hr /><p style=\"text-align:right;\">";
			printf(_('Average completion rate: %d%%'), (int)($totalPercentage/$taskcount));
			echo "</p>\n";
			echo '</td></tr>';
		}
	}

	function renderFiles($group_id, $ah) {

		$file_list =& $ah->getFiles();
		$count=count($file_list);

		if ($count > 0) {
			echo '<tr><td colspan="2">';
			echo '<b>'._("Attachments")._(':').'</b>'.'<br/>';
			$title_arr=array();
			$title_arr[] = _('Size');
			$title_arr[] = _('Name');
			$title_arr[] = _('Date');
			$title_arr[] = _('By');
			$title_arr[] = _('Download');
			echo $GLOBALS['HTML']->listTableTop($title_arr);

			foreach ($file_list as $file) {
				echo '<tr>';
				echo '<td>'.human_readable_bytes($file->getSize()).'</td>';
				echo '<td>'.htmlspecialchars($file->getName()).'</td>';
				echo '<td>'.date(_('Y-m-d H:i'), $file->getDate()).'</td>';
				echo '<td>'.$file->getSubmittedUnixName().'</td>';
				echo '<td>'.util_make_link('/tracker/download.php/'.$group_id.'/'. $this->getID().'/'. $ah->getID() .'/'.$file->getID().'/'.$file->getName(), htmlspecialchars($file->getName())).'</td>';
				if (forge_check_perm ('tracker', $this->getID(), 'tech')) {
					echo '<td><input type="checkbox" name="delete_file[]" value="'. $file->getID() .'">'._('Delete').'</td>';
				}
				echo '</tr>';
			}

			echo $GLOBALS['HTML']->listTableBottom();
			echo '</td></tr>';
		}
	}

	/**
	 * getRenderHTML
	 *
	 * @param	array	$types
	 * @param	string	$mode
	 * @return	string	HTML template.
	 */
	function getRenderHTML($types=array(), $mode='') {
		// Use template only for the browse (not for query or mass update)
		if (($mode === 'DISPLAY' || $mode === 'DETAIL' || $mode === 'UPDATE')
			&& $this->data_array['custom_renderer']) {
			return preg_replace('/<!--(\S+.*?)-->/','{$\\1}',$this->data_array['custom_renderer']);
		} else {
			return $this->generateRenderHTML($types, $mode);
		}
	}

	/**
	 * generateRenderHTML
	 *
	 * @param	array	$types
	 * @param	string	$mode	Display mode (QUERY OR DISPLAY)
	 * @return	string	HTML template.
	 */
	function generateRenderHTML($types=array(), $mode) {
		$efarr = $this->getExtraFields($types);
		//each two columns, we'll reset this and start a new row

		$return = '
			<!-- Start Extra Fields Rendering -->
			<tr>';
		$col_count=0;

		$keys=array_keys($efarr);
		$count=count($keys);
		if ($count == 0) return '';

		for ($k=0; $k<$count; $k++) {
			$i=$keys[$k];

			// Do not show the required star in query mode (creating/updating a query).
			$is_required = ($mode == 'QUERY' || $mode == 'DISPLAY') ?	0 : $efarr[$i]['is_required'];
			$name = $efarr[$i]['field_name']._(': ');
			$name = '<strong>'.$name.'</strong>';

			if ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_SELECT) {

				$return .= '
					<td class="halfwidth top">'.$name.'<br />{$'.$efarr[$i]['field_name'].'}</td>';

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_CHECKBOX) {

				$return .= '
					<td class="halfwidth top">'.$name.'<br />{$'.$efarr[$i]['field_name'].'}</td>';

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_RADIO) {

				$return .= '
					<td class="halfwidth top">'.$name.'<br />{$'.$efarr[$i]['field_name'].'}</td>';

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_TEXT ||
				$efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_INTEGER) {

				//text fields might be really wide, so need a row to themselves.
				if (($col_count == 1) && ($efarr[$i]['attribute1'] > 30)) {
					$colspan=2;
					$return .= '
					<td></td>
			</tr>
			<tr>';
				} else {
					$colspan=1;
				}
				$return .= '
					<td style="width:'.(50*$colspan).'%" colspan="'.$colspan.'" class="top">'.$name.'{$PostName:'.$efarr[$i]['field_name'].'}<br />{$'.$efarr[$i]['field_name'].'}</td>';

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_TEXTAREA) {

				//text areas might be really wide, so need a row to themselves.
				if (($col_count == 1) && ($efarr[$i]['attribute2'] > 30)) {
					$colspan=2;
					$return .= '
					<td></td>
			</tr>
			<tr>';
				} else {
					$colspan=1;
				}
				$return .= '
					<td style="width:'.(50*$colspan).'%" colspan="'.$colspan.'" class="top">'.$name.'{$PostName:'.$efarr[$i]['field_name'].'}<br />{$'.$efarr[$i]['field_name'].'}</td>';

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_MULTISELECT) {

				$return .= '
					<td class="halfwidth top">'.$name.'<br />{$'.$efarr[$i]['field_name'].'}</td>';

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_STATUS) {

				$return .= '
					<td class="halfwidth top">'.$name.'<br />{$'.$efarr[$i]['field_name'].'}</td>';

			} elseif ($efarr[$i]['field_type'] == ARTIFACT_EXTRAFIELDTYPE_RELATION) {

				//text fields might be really wide, so need a row to themselves.
				if (($col_count == 1) && ($efarr[$i]['attribute1'] > 30)) {
					$colspan=2;
					$return .= '
					<td></td>
			</tr>
			<tr>';
				} else {
					$colspan=1;
				}
				$return .= '
					<td style="width:'.(50*$colspan).'%" colspan="'.$colspan.'" class="top">'.$name.'{$PostName:'.$efarr[$i]['field_name'].'}<br />{$'.$efarr[$i]['field_name'].'}</td>';

			}
			$col_count++;
			//we've done two columns - if there are more to do, start a new row
			if (($col_count == 2) && ($k != $count-1)) {
				$col_count = 0;
				$return .= '
			</tr>
			<tr>';
			}
		}
		if ($col_count == 1) {
			$return .= '
					<td></td>';
		}
		$return .= '
			</tr>
			<!-- End Extra Fields Rendering -->';
		return $return;
	}

	/**
	 * renderSelect - this function builds pop up box with choices.
	 *
	 * @param	int		$extra_field_id	The ID of this field.
	 * @param	string		$checked	The item that should be checked
	 * @param	bool|string	$show_100	Whether to show the '100 row'
	 * @param	string		$text_100	What to call the '100 row'
	 * @param	bool		$show_any
	 * @param	string		$text_any
	 * @param	bool		$allowed
	 * @return string HTML code for the box and choices
	 */
	function renderSelect ($extra_field_id,$checked='xzxz',$show_100=false,$text_100='none',$show_any=false,$text_any='Any', $allowed=false) {
		if ($text_100 == 'none'){
			$text_100=_('None');
		}
		$arr = $this->getExtraFieldElements($extra_field_id);
		$keys = array();
		$vals = array();
		for ($i=0; $i<count($arr); $i++) {
			$keys[$i]=$arr[$i]['element_id'];
			$vals[$i]=$arr[$i]['element_name'];
		}
		return html_build_select_box_from_arrays ($keys,$vals,'extra_fields['.$extra_field_id.']',$checked,$show_100,$text_100,$show_any,$text_any, $allowed);
	}

	/**
	 * renderRadio - this function builds radio buttons.
	 *
	 * @param	int	$extra_field_id	The $int ID of this field.
	 * @param	string	$checked	The $string item that should be checked
	 * @param	bool	$show_100	Whether $string to show the '100 row'
	 * @param	string	$text_100	What $string to call the '100 row'
	 * @param	bool	$show_any
	 * @param	string	$text_any
	 * @return	string	HTML code using radio buttons
	 */
	function renderRadio ($extra_field_id,$checked='xzxz',$show_100=false,$text_100='none',$show_any=false,$text_any='Any') {
		$arr = $this->getExtraFieldElements($extra_field_id);
		$keys = array();
		$vals = array();
		for ($i=0; $i<count($arr); $i++) {
			$keys[$i]=$arr[$i]['element_id'];
			$vals[$i]=$arr[$i]['element_name'];
		}
		return html_build_radio_buttons_from_arrays ($keys,$vals,'extra_fields['.$extra_field_id.']',$checked,$show_100,$text_100,$show_any,$text_any);
	}

	/**
	 * renderCheckbox - this function builds checkboxes.
	 *
	 * @param	int		$extra_field_id	The ID of this field.
	 * @param	array		$checked	The items that should be checked
	 * @param	bool|string	$show_100	Whether to show the '100 row'
	 * @param	string		$text_100	What to call the '100 row'
	 * @return string radio buttons
	 */
	function renderCheckbox ($extra_field_id,$checked=array(),$show_100=false,$text_100='none') {
		if ($text_100 == 'none'){
			$text_100=_('None');
		}
		if (!$checked || !is_array($checked)) {
			$checked=array();
		}
		$arr = $this->getExtraFieldElements($extra_field_id);
		$return = '';
		if ($show_100) {
			$return .= '
				<input type="checkbox" name="extra_fields['.$extra_field_id.'][]" value="100" '.
			((in_array(100,$checked)) ? 'checked="checked"' : '').'/> '.$text_100.'<br />';
		}
		for ($i=0; $i<count($arr); $i++) {
			$return .= '
				<input type="checkbox" name="extra_fields['.$extra_field_id.'][]" value="'.$arr[$i]['element_id'].'" '.
			((in_array($arr[$i]['element_id'],$checked)) ? 'checked="checked"' : '').'/> '.$arr[$i]['element_name'].'<br />';
		}
		return $return;
	}

	/**
	 * renderMultiSelectBox - this function builds checkboxes.
	 *
	 * @param	int		$extra_field_id	The ID of this field.
	 * @param	array		$checked	The items that should be checked
	 * @param	bool|string	$show_100	Whether to show the '100 row'
	 * @param	string		$text_100	What to call the '100 row'
	 * @return	string		radio multiselectbox
	 */
	function renderMultiSelectBox ($extra_field_id,$checked=array(),$show_100=false,$text_100='none') {
		if (!$checked) {
			$checked=array();
		}
		if (!is_array($checked)) {
			$checked = explode(',',$checked);
		}
		$keys=array();
		$vals=array();
		$arr = $this->getExtraFieldElements($extra_field_id);
		for ($i=0; $i<count($arr); $i++) {
			$keys[]=$arr[$i]['element_id'];
			$vals[]=$arr[$i]['element_name'];
		}
		$size = min( count($arr)+1, 15);
			return html_build_multiple_select_box_from_arrays($keys,$vals,"extra_fields[$extra_field_id][]",$checked,$size,$show_100,$text_100);
	}

	/**
	 * renderTextField - this function builds a text field.
	 *
	 * @param	int	$extra_field_id	The ID of this field.
	 * @param	string	$contents	The data for this field.
	 * @param	string	$size
	 * @param	string	$maxlength
	 * @return	string	HTML code of corresponding input tag.
	 */
	function renderTextField ($extra_field_id, $contents, $size, $maxlength) {
		return '
			<input type="text" name="extra_fields['.$extra_field_id.']" value="'.$contents.'" size="'.$size.'" maxlength="'.$maxlength.'"/>';
	}

	/**
	 * renderRelationField - this function builds a relation field.
	 *
	 * @param	int	$extra_field_id	The ID of this field.
	 * @param	string	$contents	The data for this field.
	 * @param	string	$size
	 * @param	string	$maxlength
	 * @return	string	text area and data.
	 */
	function renderRelationField ($extra_field_id,$contents,$size,$maxlength) {
		$arr = $this->getExtraFieldElements($extra_field_id);
		for ($i=0; $i<count($arr); $i++) {
			$keys[$i]=$arr[$i]['element_id'];
			$vals[$i]=$arr[$i]['element_name'];
		}
		// Convert artifact id to links.
		$myFunc2 = function($matches) {
			return _artifactid2url($matches[1], 'title');
		};
		$html_contents = preg_replace_callback('/\b(\d+)\b/', $myFunc2, $contents);
		$edit_contents = $this->renderTextField ($extra_field_id,$contents,$size,$maxlength);
		return '
			<div id="edit'.$extra_field_id.'" style="display: none;" title="'._('Tip: Enter a space-separated list of artifact ids ([#NNN] also accepted)').'" >'.$edit_contents.'</div>
			<div id="show'.$extra_field_id.'" style="display: block;">'.$html_contents.'</div>';
	}

	/**
	 * renderTextArea - this function builds a text area.
	 *
	 * @param	int	$extra_field_id	The ID of this field.
	 * @param	string	$contents	The data for this field.
	 * @param	string	$rows
	 * @param	string	$cols
	 * @return	string	text area and data.
	 */
	function renderTextArea ($extra_field_id,$contents,$rows,$cols) {
		return '
			<textarea name="extra_fields['.$extra_field_id.']" rows="'.$rows.'" cols="'.$cols.'">'.$contents.'</textarea>';
	}

	function technicianBox ($name='assigned_to[]',$checked='xzxz',$show_100=true,$text_100='none',$extra_id='-1',$extra_name='',$multiple=false) {
		if ($text_100=='none'){
			$text_100=_('Nobody');
		}

		$engine = RBACEngine::getInstance () ;
		$techs = $engine->getUsersByAllowedAction ('tracker', $this->getID(), 'tech') ;

		$ids = array () ;
		$names = array () ;

		sortUserList($techs);

		foreach ($techs as $tech) {
			$ids[] = $tech->getID() ;
			$names[] = $tech->getRealName() ;
		}

		if ($extra_id != '-1') {
			$ids[]=$extra_id;
			$names[]=$extra_name;
		}

		if ($multiple) {
			if (!is_array($checked)) {
				$checked = explode(',',$checked);
			}
			$size = min( count($ids)+1, 15);
			return html_build_multiple_select_box_from_arrays ($ids,$names,$name,$checked,$size,$show_100,$text_100);
		} else {
			return html_build_select_box_from_arrays ($ids,$names,$name,$checked,$show_100,$text_100);
		}
	}

	function submitterBox ($name='submitted_by[]',$checked='xzxz',$show_100=true,$text_100='none',$extra_id='-1',$extra_name='',$multiple=false) {
		if ($text_100=='none'){
			$text_100=_('Nobody');
		}
		$result = $this->getSubmitters();
		$ids =& util_result_column_to_array($result,0);
		$names =& util_result_column_to_array($result,1);
		if ($extra_id != '-1') {
			$ids[]=$extra_id;
			$names[]=$extra_name;
		}

		if ($multiple) {
			if (!is_array($checked)) {
				$checked = explode(',',$checked);
			}
			$size = min( count($ids)+1, 15);
			return html_build_multiple_select_box_from_arrays ($ids,$names,$name,$checked,$size,$show_100,$text_100);
		} else {
			return html_build_select_box_from_arrays ($ids,$names,$name,$checked,$show_100,$text_100);
		}
	}

	function cannedResponseBox ($name='canned_response',$checked='xzxz') {
		return html_build_select_box ($this->getCannedResponses(),$name,$checked);
	}

	/**
	 * statusBox - show the statuses - automatically shows the "custom statuses" if they exist
	 *
	 *
	 */
	function statusBox ($name='status_id',$checked='xzxz',$show_100=false,$text_100='none') {
		if ($text_100=='none'){
			$text_100=_('None');
		}
		return html_build_select_box($this->getStatuses(),$name,$checked,$show_100,$text_100);
	}

}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
