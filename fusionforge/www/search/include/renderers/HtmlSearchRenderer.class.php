<?php
/**
 * Search Engine
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2004 (c) Guillaume Smet / Open Wide
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2014, Franck Villaume - TrivialDev
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

require_once $gfwww.'search/include/renderers/SearchRenderer.class.php';

class HtmlSearchRenderer extends SearchRenderer {

	/**
	 * Headers of the HTML results table
	 *
	 * @var array $tableHeaders
	 */
	var $tableHeaders = array();

	/**
	 * Constructor
	 *
	 * @param string  $typeOfSearch type of the search (Software, Forum, People and so on)
	 * @param string  $words        words we are searching for
	 * @param boolean $isExact      if we want to search for all the words or if only one matching the query is sufficient
	 * @param object  $searchQuery  SearchQuery instance
	 */
	function __construct($typeOfSearch, $words, $isExact, $searchQuery) {
		parent::__construct($typeOfSearch, $words, $isExact, $searchQuery);
	}

	/**
	 * flush - flush the html output
	 */
	function flush() {
		global $HTML;
		$searchQuery =& $this->searchQuery;
		if($searchQuery->isError()) {
			$this->writeHeader();
			echo $HTML->error_msg($searchQuery->getErrorMessage());
			$this->writeFooter();
		} else {
			$searchQuery->executeQuery();
			if($searchQuery->getResult() && ($searchQuery->getRowsTotalCount() == 1 && $searchQuery->getOffset() == 0) && $this->implementsRedirectToResult()) {
				$this->redirectToResult();
			} else {
/*
				$this->writeHeader();
				$this->writeBody();
				$this->writeFooter();
*/
				// Get text from search field.
				$words = $this->searchQuery->getWords();
				foreach ($this->searchQuery->getPhrases() as $p) {
					foreach (explode(' ', $p) as $w) {
						$words[] = $w;
					}
				}
				$strSearchWords = implode(',', $words);

				// Redirect to search.php page.
				$strURL = '/search/search.php?' .
					'srch=' . $strSearchWords;
				header("location: " . $strURL);
			}
		}
	}

	/**
	 * writeHeader - write the header of the output
	 */
	function writeHeader() {
	}

	/**
	 * writeBody - write the body
	 */
	function writeBody() {
		echo $this->writeResults();
	}

	/**
	 * writeFooter - write the footer
	 */
	function writeFooter() {
		global $HTML;
		$HTML->footer();
	}

	/**
	 * getResults - get the html output which will display the search results
	 *
	 * @return string html output
	 */
	function writeResults() {
		global $HTML;
		$searchQuery =& $this->searchQuery;
		$query =& $this->query;

		if(!$searchQuery->getResult() || $searchQuery->getRowsCount() < 1) {
			$html = '<p><strong>'.sprintf(_('No matches found for “%s”'), $query['words']).'</strong></p>';
			$html .= db_error();
		} else {
			$html = $HTML->listTableTop($this->tableHeaders);
			$html .= $this->getRows();
			$html .= $HTML->listTableBottom();
		}

		if($searchQuery->getRowsCount() > 0 && ($searchQuery->getRowsTotalCount() > $searchQuery->getRowsCount() || $searchQuery->getOffset() != 0 )) {
			$html .= $this->getNavigationPanel();
		}

		return $html;
	}

	/**
	 * getNavigationPanel - get the html output for the navigation panel
	 *
	 * @return string html output
	 */
	function getNavigationPanel() {
		$searchQuery =& $this->searchQuery;

		$html = '<br />';
		$html .= '<table class="tablecontent" width="100%" cellpadding="5">';
		$html .= '<tr>';
		$html .= '<td>';
		if ($searchQuery->getOffset() != 0) {
			$html .= util_make_link($this->getPreviousResultsUrl(), html_image('t2.png', '15', '15').' '._('Previous Results'), array('class' => 'prev'));
		} else {
			$html .= '&nbsp;';
		}
		$html .= '</td><td class="align-right">';
		if ($searchQuery->getRowsTotalCount() > $searchQuery->getRowsCount()) {
			$html .= util_make_link($this->getNextResultsUrl(), _('Next Results').' '.html_image('t.png', '15', '15'), array('class' => 'next'));
		} else {
			$html .= '&nbsp;';
		}
		$html .= '</td></tr>';
		$html .= '</table>';
		return $html;
	}

	/**
	 * getPreviousResultsUrl - get the url to go to see the previous results
	 *
	 * @return string url to previous results page
	 */
	function getPreviousResultsUrl() {
		$offset = $this->searchQuery->getOffset() - $this->searchQuery->getRowsPerPage();
		$query =& $this->query;

		$url = '/search/?type='.$query['typeOfSearch'].'&exact='.$query['isExact'].'&q='.urlencode($query['words']);
		if($offset > 0) {
			$url .= '&offset='.$offset;
		}
		return $url;
	}

	/**
	 * getNextResultsUrl - get the url to go to see the next results
	 *
	 * @return string url to next results page
	 */
	function getNextResultsUrl() {
		$query =& $this->query;
		return '/search/?type='.$query['typeOfSearch'].'&exact='.$query['isExact'].'&q='.urlencode($query['words']).'&offset='.($this->searchQuery->getOffset() + $this->searchQuery->getRowsPerPage());
	}

	/**
	 * highlightTargetWords - highlight the words we are looking for
	 *
	 * @param string $text text
	 * @return string text with keywords highlighted
	 */
	function highlightTargetWords($text) {
		if (empty($text)) {
			return '&nbsp;';
		}
		$words = $this->searchQuery->getWords();
		foreach ($this->searchQuery->getPhrases() as $p) {
			foreach (explode(' ',$p) as $w) {
				$words[] = $w;
			}
		}
		$regexp = implode('|',$words);
		return preg_replace('/('.str_replace('/', '\/', $regexp).')/i','<span class="selected">\1</span>', $text);
	}

	/**
	 * implementsRedirectToResult - check if the current object implements the redirect to result feature by having a redirectToResult method
	 *
	 * @return boolean true if our object implements search by id, false otherwise.
	 */
	function implementsRedirectToResult() {
		return method_exists($this, 'redirectToResult');
	}

	/**
	 * getResultId - get the field value for the first row of a result handle
	 *
	 * @param string $fieldName field name
	 * @return string value of the field
	 */
	function getResultId($fieldName) {
		return db_result($this->searchQuery->getResult(), 0, $fieldName);
	}
}
