<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright 2016-2025, SimTK Team
 * http://fusionforge.org
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

require_once 'common/include/Codendi_Request.class.php';

/**
 * @package Codendi
 */
class HTTPRequest extends Codendi_Request {

    /**
     * Constructor
     */
    function __construct() {
        parent::__construct($_REQUEST);
    }

    /**
     * Get the value of $variable in $this->params (server side values).
     *
     * @param  string $variable Name of the parameter to get.
     * @return mixed  If the variable exist, the value is returned (string)
     * otherwise return false;
     */
    function getFromServer($variable) {
        return $this->_get($variable, $_SERVER);
    }

    /**
     * Check if current request is send via 'post' method.
     *
     * This method is useful to test if the current request comes from a form.
     *
     * @return boolean
     */
    function isPost() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return true if browser used to submit the request is netscape 4.
     *
     * @return boolean
     */
    function browserIsNetscape4() {
        return browser_is_netscape4();
    }

    /**
     * Singleton method for the class.
     *
     * @return mixed HTTPRequest Object.
     */
    static function &instance() {
        static $_httprequest_instance;
        if (!$_httprequest_instance) {
            $_httprequest_instance = new HTTPRequest();
        }
        return $_httprequest_instance;
    }

    /**
     * Validate file upload.
     *
     * @param  Valid_File Validator for files.
     * @return Boolean
     */
    function validFile(&$validator) {
        if(is_a($validator, 'Valid_File')) {
            $this->_validated_input[$validator->getKey()] = true;
            return $validator->validate($_FILES, $validator->getKey());
        } else {
            return false;
        }
    }

    /**
     * Remove slashes in $value. If $value is an array, remove slashes for each
     * element.
     *
     * @access private
     * @param  mixed $value
     * @return mixed
     */
    function _stripslashes($value) {
        if (is_string($value)) {
            $value = stripslashes($value);
        } elseif (is_array($value)) {
            foreach($value as $key => $val) {
                $value[$key] = $this->_stripslashes($val);
            }
        }
        return $value;
    }

    /**
     * Get the value of $variable in $array. If magic_quotes are enabled, the
     * value is escaped.
     *
     * @access private
     * @param string $variable Name of the parameter to get.
     * @param array  $array    Name of the parameter to get.
	 * @return bool|mixed
	 */
    function _get($variable, $array) {
        if ($this->_exist($variable, $array)) {
            return $array[$variable];
        } else {
            return false;
        }
    }

}
