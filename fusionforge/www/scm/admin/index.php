<?php
/**
 * SCM Frontend
 *
 * Copyright 2004 (c) Roland Mas, Tim Perdue GForge LLC
 * Copyright 2005-2021, SimTK Team
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

require_once '../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'project/admin/project_admin_utils.php';
require_once $gfwww.'scm/include/scm_utils.php';
require_once $gfcommon.'scm/SCMFactory.class.php';

html_use_jquery();
html_use_coolfieldset();

$group_id = getIntFromRequest('group_id');
$group = group_get_object($group_id);
if (!$group || !is_object($group)) {
    exit_no_group();
} elseif ($group->isError()) {
	exit_error($group->getErrorMessage(), 'scm');
}

// Check permissions
session_require_perm('project_admin', $group_id);

if (getStringFromRequest('form_create_repo')) {
	$hook_params = array();
	$hook_params['group_id'] = $group_id;
	plugin_hook('scm_admin_form', $hook_params);
	exit;
}

if (getStringFromRequest('create_repository') && getStringFromRequest('submit')) {
	$repo_name = htmlspecialchars(trim(getStringFromRequest('repo_name')));
	$description = preg_replace('/[\r\n]/', ' ', htmlspecialchars(getStringFromRequest('description')));
	$clone = htmlspecialchars(getStringFromRequest('clone'));
	$hook_params = array () ;
	$hook_params['group_id'] = $group_id;
	$hook_params['repo_name'] = $repo_name;
	$hook_params['description'] = $description;
	$hook_params['clone'] = $clone;
	$hook_params['error_msg'] = '';
	$hook_params['scm_enable_anonymous'] = getIntFromRequest('scm_enable_anonymous');
	plugin_hook_by_reference('scm_add_repo', $hook_params);
	if ($hook_params['error_msg']) {
		$error_msg = $hook_params['error_msg'];
	}
	else {
		$feedback = sprintf(_('New repository %s registered, will be created shortly.'), $repo_name);
	}
} elseif (getStringFromRequest('delete_repository') && getStringFromRequest('submit')) {
	$repo_name = htmlspecialchars(trim(getStringFromRequest('repo_name')));

	$hook_params = array () ;
	$hook_params['group_id'] = $group_id;
	$hook_params['repo_name'] = $repo_name;
	$hook_params['error_msg'] = '';
	$hook_params['scm_enable_anonymous'] = getIntFromRequest('scm_enable_anonymous');
	plugin_hook_by_reference('scm_delete_repo', $hook_params);
	if ($hook_params['error_msg']) {
		$error_msg = $hook_params['error_msg'];
	}
	else {
		$feedback = sprintf(_('Repository %s is marked for deletion (actual deletion will happen shortly).'), $repo_name);
	}
} elseif (getStringFromRequest('submit')) {
	$hook_params = array();
	$hook_params['group_id'] = $group_id;
	
	$scmradio = '';
	$scmvars = array_keys(_getRequestArray());
	foreach (_getRequestArray() as $key => $value) {
		foreach ($scm_list as $scm) {
			if ($key == strstr($key, $scm . "_")) {
				$hook_params[$key] = $value;
			}
			else {
				$hook_params[$scm] = getArrayFromRequest($scm);
			}
		}
		if ($key == strstr($key, "scm_")) {
			$hook_params[$key] = $value;
		} elseif ($key == 'scmradio') {
			$scmradio = $value;
		}
	}

	$SCMFactory = new SCMFactory();
	$scm_plugins = $SCMFactory->getSCMs();
	
	$scm_changed = false;
	if (count($scm_plugins) == 1) {
	   $scmradio = htmlspecialchars(getStringFromRequest('scmradio'));
	   $myPlugin = plugin_get_object($scm_plugins[0]);
	      if ($scmradio == $myPlugin->name) {
		     if (!$group->usesPlugin($myPlugin->name)) {
			    $group->setPluginUse($myPlugin->name, 1);
				if ($myPlugin->getDefaultServer()) {
					$group->setSCMBox($myPlugin->getDefaultServer());
				}
				$scm_changed = true;
			 }
		  } else {
			 $group->setPluginUse($myPlugin->name, 0);
		  }   
		
	} else if (count($scm_plugins) > 1) {  
	  if (in_array($scmradio, $scm_plugins)) {
		foreach ($scm_plugins as $plugin) {
			$myPlugin = plugin_get_object($plugin);
			if ($scmradio == $myPlugin->name) {
				if (!$group->usesPlugin($myPlugin->name)) {
					$group->setPluginUse($myPlugin->name, 1);
					if ($myPlugin->getDefaultServer()) {
						$group->setSCMBox($myPlugin->getDefaultServer());
					}
					$scm_changed = true;
				}
			} else {
				$group->setPluginUse($myPlugin->name, 0);
			}
		}
	  }

	}
	
	// Don't call scm plugin update if their form wasn't displayed
	// to avoid processing an apparently empty form and reset configuration
	if (!$scm_changed)
		plugin_hook("scm_admin_update", $hook_params);
		$feedback .= _('Updated');
		//var_dump($hook_params);
}

$hook_params = array();
$hook_params['group_id'] = $group_id;
plugin_hook('scm_admin_buttons', $hook_params);

scm_header(array('title'=>_('SCM Repository'),'group'=>$group_id));
?>
<script type="text/javascript">
	$(document).ready(function() {
		$("input[type=radio][name=scmradio]").change(function() {
			$("input[type=radio][name=scmradio]").each(function () {
				$('#div_'+$(this).val()).hide();
			});
			$('#div_'+$("input[type=radio][name=scmradio]:checked").val()).show();
		});
	});
</script>
<form method="post" action="<?php echo util_make_uri('/scm/admin/?group_id='.$group_id) ?>">
<div class="form_simtk">
<?php

	$hook_params = array () ;
	$hook_params['group_id'] = $group_id ;

	$SCMFactory = new SCMFactory();
	$scm_plugins = $SCMFactory->getSCMs();
	
	/*
	if (count($scm_plugins) != 0) {
		if (count($scm_plugins) == 1) {
			$myPlugin = plugin_get_object($scm_plugins[0]);
			//echo '<input type="hidden" name="scmradio" value="'.$myPlugin->name.'" />' ;
			
			echo '<p><input type="checkbox" name="scmradio" ';
			echo 'value="'.$myPlugin->name.'"';
			if ($group->usesPlugin($myPlugin->name)) {
				$scm = $myPlugin->name;
				echo ' checked="checked"';
			}
			echo ' />'.$myPlugin->text.' <a href="#" data-toggle="popover" data-placement="right" data-trigger="hover" title="Source Code" data-content="Unchecking the box does not delete the repository.  It only affects the information displayed under the View Source Code Menu.">?</a>
</p>';
			//echo '<br />'."\n";
		} else {
			echo '<p>'._('Note: Changing the repository does not delete the previous repository.  It only affects the information displayed under the SCM tab.').'</p>';
			echo '<p><strong>'._('SCM Repository').'</strong></p>';
			$checked=true;
			foreach ($scm_plugins as $plugin) {
				$myPlugin = plugin_get_object($plugin);
				echo '<p><input type="radio" name="scmradio" ';
				echo 'value="'.$myPlugin->name.'"';
				if ($group->usesPlugin($myPlugin->name)) {
					$scm = $myPlugin->name;
					echo ' checked="checked"';
				}
				echo ' />'.$myPlugin->text.'</p>';
			}
			echo '<br />'."\n";
		}
	} else {
		echo '<p class="error_msg">'._('Error: Site has SCM but no plugins registered').'</p>';
	}
    echo "scm_plugins: " . count($scm_plugins);
	*/
	
	(isset($scm)) ? $hook_params['scm_plugin'] = $scm : $hook_params['scm_plugin'] = 0;
	plugin_hook("scm_admin_page", $hook_params);
	
	if ($group->isPublic()) {
?>
       <input type="hidden" name="group_id" value="<?php echo $group_id; ?>" />
       <input type="submit"  class="btn-blue" name="submit" value="<?php echo _('Update'); ?>" />
    <?php } else {
	         echo "This project is set to private.  To allow everyone read access to the repository, first go to admin settings to set project to public.";
	      }
	?>
</div> <!-- class simtk -->
</form>
<?php

plugin_hook('scm_admin_form', $hook_params);
scm_footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
