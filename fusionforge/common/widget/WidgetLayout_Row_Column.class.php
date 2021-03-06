<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright 2014, Franck Villaume - TrivialDev
 * Copyright 2016-2019, Henry Kwong, Tod Hing - SimTK Team
 *
 * This file is a part of Fusionforge.
 *
 * Fusionforge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fusionforge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

class WidgetLayout_Row_Column {
    var $id;
    var $width;
    var $contents;
    var $row;
    function __construct($id, $width) {
        $this->id       = $id;
        $this->width    = $width;
        $this->contents = array();
    }
    function setRow(&$row) {
        $this->row =& $row;
    }
    function add(&$c, $is_minimized, $display_preferences) {
        $this->contents[] = array('content' => &$c, 'is_minimized' => $is_minimized, 'display_preferences' => $display_preferences);
    }
    function display($readonly, $owner_id, $owner_type, $is_last) {
        echo html_ao('td', array('style' => 'height:10px; width:'. $this->width .'%; '. (!$is_last ? 'padding-right:20px;' : ''), 'id' => $this->getColumnId()));
        foreach ($this->contents as $key => $nop) {
            $this->contents[$key]['content']->display($this->row->layout->id, $this->id, $readonly, $this->contents[$key]['is_minimized'], $this->contents[$key]['display_preferences'], $owner_id, $owner_type);
        }
        echo html_ac(html_ap() -1);
    }
    function getColumnId() {
        return 'widgetlayout_col_'. $this->id;
    }
}
