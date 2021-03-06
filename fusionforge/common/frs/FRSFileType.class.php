<?php
/**
 * FusionForge file release system
 *
 * Copyright 2007 SoftwareEntwicklung Beratung Schulung
 * Copyright 2007 Karl Heinz Marbaise
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

require_once $gfcommon.'include/FFError.class.php';

function get_frs_filetypes() {
	$res=db_query_params('SELECT * FROM frs_filetype', array());
	if (db_numrows($res) < 1) {
		return false;
	}
	$ps = array();
	while($arr = db_fetch_array($res)) {
		$ps[]=new FRSFileType($arr['type_id'],$arr['name']);
	}
	return $ps;
}

class FRSFileType extends FFError {

	/**
	 * Associative array of data from db.
	 *
	 * @var	array	$data_array.
	 */
	var $data_array;

	function __construct ($type_id=false, $name=false) {
		parent::__construct();
		if($type_id && $name) {
			$this->data_array = array( 'type_id' => $type_id, 'name' => $name);
		}
		else {
			if ($type_id) {
				$this->fetchData($type_id);
			}
		}
	}

	/**
	 * fetchData - re-fetch the data for this FRSFileType from the database.
	 *
	 * @param	int	$type_id	The type_id
	 * @return	bool	success.
	 */
	function fetchData($type_id) {
		$res=db_query_params('SELECT * FROM frs_filetype WHERE type_id=$1', array($type_id));
		if (!$res || db_numrows($res) < 1) {
			$this->setError(_('Invalid type_id'));
			return false;
		}
		$this->data_array = db_fetch_array($res);
		db_free_result($res);
		return true;
	}

	/**
	 * getID - get this file_id.
	 *
	 * @return	int	The id of this file.
	 */
	function getID() {
		return $this->data_array['type_id'];
	}

	/**
	 * getName - get the name of this file.
	 *
	 * @return	string	The name of this file.
	 */
	function getName() {
		return $this->data_array['name'];
	}

}
