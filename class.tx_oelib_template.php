<?php
/***************************************************************
* Copyright notice
*
* (c) 2008 Niels Pardon (mail@niels-pardon.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('oelib') . 'exceptions/class.tx_oelib_notFoundException.php');

/**
 * Class 'tx_oelib_template' for the 'oelib' extension.
 *
 * This class represents a HTML template with markers (###MARKER###) and
 * subparts (<!-- ###SUBPART### --><!-- ###SUBPART### -->).
 *
 * @package TYPO3
 * @subpackage tx_oelib
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_oelib_template {
	/**
	 * @var string the complete HTML template
	 */
	private $templateCode = '';

	/**
	 * @var array associative array of all HTML template subparts, using
	 *            the marker names without ### as keys, for example "MY_MARKER"
	 */
	private $templateCache = array();

	/**
	 * @var string list of the names of all markers (and subparts) of a template
	 */
	private $markerNames = '';

	/**
	 * @var array associative array of populated markers and their
	 *            contents (with the keys being the marker names including
	 *            the wrapping hash signs ###).
	 */
	private $markers = array();

	/**
	 * @var array Subpart names that shouldn't be displayed. Set a subpart key
	 *            like "FIELD_DATE" (the value does not matter) to remove that
	 *            subpart.
	 */
	private $subpartsToHide = array();

	/**
	 * The constructor. Does nothing.
	 */
	public function __construct() {
	}

	/**
	 * Gets the HTML template in the file specified in the paramter $filename,
	 * stores it and retrieves all subparts, writing them to $this->templateCache.
	 *
	 * The subpart names are automatically retrieved from $templateRawCode and
	 * are used as array keys. For this, the ### are removed, but the names stay
	 * uppercase.
	 *
	 * Example: The subpart ###MY_SUBPART### will be stored with the array key
	 * 'MY_SUBPART'.
	 *
	 * @param string the file name of the HTML template to process, must be an
	 *               existing file, must not be empty
	 */
	public function processTemplateFromFile($fileName) {
		$templateRawCode = file_get_contents(
			t3lib_div::getFileAbsFileName($fileName)
		);

		$this->processTemplate($templateRawCode);
	}

	/**
	 * Stores the given HTML template and retrieves all subparts, writing them
	 * to $this->templateCache.
	 *
	 * The subpart names are automatically retrieved from $templateRawCode and
	 * are used as array keys. For this, the ### are removed, but the names stay
	 * uppercase.
	 *
	 * Example: The subpart ###MY_SUBPART### will be stored with the array key
	 * 'MY_SUBPART'.
	 *
	 * @param string the content of the HTML template
	 */
	public function processTemplate($templateRawCode) {
		$this->templateCode = $templateRawCode;
		$this->findMarkers();

		$subpartNames = $this->findSubparts();

		foreach ($subpartNames as $subpartName) {
			$matches = array();
			preg_match(
				'/<!-- *###' . $subpartName . '### *-->(.*)' .
					'<!-- *###' . $subpartName . '### *-->/msSU',
				$templateRawCode,
				$matches
			);
			if (isset($matches[1])) {
				$this->templateCache[$subpartName] = $matches[1];
			}
		}
	}

	/**
	 * Finds all subparts within the current HTML template.
	 * The subparts must be within HTML comments.
	 *
	 * @return array a list of the subpart names (uppercase, without ###,
	 *               for example 'MY_SUBPART')
	 */
	private function findSubparts() {
		$matches = array();
		preg_match_all(
			'/<!-- *(###)([A-Z]([A-Z0-9_]*[A-Z0-9])?)(###)/',
			$this->templateCode,
			$matches
		);

		return array_unique($matches[2]);
	}

	/**
	 * Finds all markers within the current HTML template.
	 * Note: This also finds subpart names.
	 *
	 * The result is one long string that is easy to process using regular
	 * expressions.
	 *
	 * Example: If the markers ###FOO### and ###BAR### are found, the string
	 * "#FOO#BAR#" would be returned.
	 */
	private function findMarkers() {
		$matches = array();
		preg_match_all(
			'/(###)(([A-Z0-9_]*[A-Z0-9])?)(###)/', $this->templateCode, $matches
		);

		$markerNames = array_unique($matches[2]);

		$this->markerNames = '#' . implode('#', $markerNames) . '#';
	}

	/**
	 * Gets a list of markers with a given prefix.
	 * Example: If the prefix is "WRAPPER" (or "wrapper", case is not relevant),
	 * the following array might be returned: ("WRAPPER_FOO", "WRAPPER_BAR")
	 *
	 * If there are no matches, an empty array is returned.
	 *
	 * The function <code>findMarkers</code> must be called before this function
	 * may be called.
	 *
	 * @param string case-insensitive prefix for the marker names to look for
	 *
	 * @return array array of matching marker names, might be empty
	 */
	public function getPrefixedMarkers($prefix) {
		if ($this->markerNames == '') {
			throw new Exception(
				'The method tx_oelib_template->findMarkers() has to be called ' .
					'before this method is called.'
			);
		}

		$matches = array();
		preg_match_all(
			'/(#)(' . strtoupper($prefix) . '_[^#]+)/',
			$this->markerNames, $matches
		);

		$result = array_unique($matches[2]);

		return $result;
	}

	/**
	 * Sets a marker's content.
	 *
	 * Example: If the prefix is "field" and the marker name is "one", the
	 * marker "###FIELD_ONE###" will be written.
	 *
	 * If the prefix is empty and the marker name is "one", the marker
	 * "###ONE###" will be written.
	 *
	 * @param string the marker's name without the ### signs,
	 *               case-insensitive, will get uppercased, must not be empty
	 * @param string the marker's content, may be empty
	 * @param string prefix to the marker name (may be empty,
	 *               case-insensitive, will get uppercased)
	 */
	public function setMarker($markerName, $content, $prefix = '') {
		$unifiedMarkerName = $this->createMarkerName($markerName, $prefix);

		if ($this->isMarkerNameValidWithHashes($unifiedMarkerName)) {
			$this->markers[$unifiedMarkerName] = $content;
		}
	}

	/**
	 * Gets a marker's content.
	 *
	 * @param string the marker's name without the ### signs,
	 *               case-insensitive, will get uppercased, must not be empty
	 *
	 * @return string the marker's content or an empty string if the
	 *                marker has not been set before
	 */
	public function getMarker($markerName) {
		$unifiedMarkerName = $this->createMarkerName($markerName);
		if (!isset($this->markers[$unifiedMarkerName])) {
			return '';
		}

		return $this->markers[$unifiedMarkerName];
	}

	/**
	 * Sets a subpart's content.
	 *
	 * Example: If the prefix is "field" and the subpart name is "one", the
	 * subpart "###FIELD_ONE###" will be written.
	 *
	 * If the prefix is empty and the subpart name is "one", the subpart
	 * "###ONE###" will be written.
	 *
	 * @param string the subpart's name without the ### signs,
	 *               case-insensitive, will get uppercased, must not be empty
	 * @param string the subpart's content, may be empty
	 * @param string prefix to the subpart name (may be empty,
	 *               case-insensitive, will get uppercased)
	 */
	public function setSubpart($subpartName, $content, $prefix = '') {
		$subpartName = $this->createMarkerNameWithoutHashes(
			$subpartName, $prefix
		);

		if (!$this->isMarkerNameValidWithoutHashes($subpartName)) {
			throw new Exception(
				'The value of the parameter $subpartName is not valid.'
			);
		}

		$this->templateCache[$subpartName] = $content;
	}

	/**
	 * Sets a marker based on whether the (integer) content is non-zero.
	 * If intval($content) is non-zero, this function sets the marker's content, working
	 * exactly like setMarker($markerName, $content, $markerPrefix).
	 *
	 * @param string the marker's name without the ### signs, case-insensitive, will get uppercased, must not be empty
	 * @param integer content with which the marker will be filled, may be empty
	 * @param string prefix to the marker name for setting (may be empty, case-insensitive, will get uppercased)
	 *
	 * @return boolean true if the marker content has been set, false otherwise
	 *
	 * @see setMarkerIfNotEmpty
	 */
	public function setMarkerIfNotZero($markerName, $content, $markerPrefix = '') {
		$condition = (intval($content) != 0);
		if ($condition) {
			$this->setMarker($markerName, ((string) $content), $markerPrefix);
		}
		return $condition;
	}

	/**
	 * Sets a marker based on whether the (string) content is non-empty.
	 * If $content is non-empty, this function sets the marker's content,
	 * working exactly like setMarker($markerName, $content, $markerPrefix).
	 *
	 * @param string the marker's name without the ### signs, case-insensitive,
	 *               will get uppercased, must not be empty
	 * @param string content with which the marker will be filled, may be empty
	 * @param string prefix to the marker name for setting (may be empty,
	 *               case-insensitive, will get uppercased)
	 *
	 * @return boolean true if the marker content has been set, false otherwise
	 *
	 * @see setMarkerIfNotZero
	 */
	public function setMarkerIfNotEmpty($markerName, $content, $markerPrefix = '') {
		$condition = !empty($content);
		if ($condition) {
			$this->setMarker($markerName, $content, $markerPrefix);
		}
		return $condition;
	}

	/**
	 * Checks whether a subpart is visible.
	 *
	 * Note: If the subpart to check does not exist, this function will return
	 * false.
	 *
	 * @param string name of the subpart to check (without the ###), must
	 *               not be empty
	 *
	 * @return boolean true if the subpart is visible, false otherwise
	 */
	public function isSubpartVisible($subpartName) {
		if ($subpartName == '') {
			return false;
		}

		return (isset($this->templateCache[$subpartName])
			&& !isset($this->subpartsToHide[$subpartName]));
	}

	/**
	 * Takes a comma-separated list of subpart names and sets them to hidden. In
	 * the process, the names are changed from 'aname' to '###BLA_ANAME###' and
	 * used as keys.
	 *
	 * Example: If the prefix is "field" and the list is "one,two", the subparts
	 * "###FIELD_ONE###" and "###FIELD_TWO###" will be hidden.
	 *
	 * If the prefix is empty and the list is "one,two", the subparts
	 * "###ONE###" and "###TWO###" will be hidden.
	 *
	 * @param string comma-separated list of at least 1 subpart name to
	 *               hide (case-insensitive, will get uppercased)
	 * @param string prefix to the subpart names (may be empty,
	 *               case-insensitive, will get uppercased)
	 */
	public function hideSubparts($subparts, $prefix = '') {
		$subpartNames = explode(',', $subparts);

		$this->hideSubpartsArray($subpartNames, $prefix);
	}

	/**
	 * Takes an array of subpart names and sets them to hidden. In the process,
	 * the names are changed from 'aname' to '###BLA_ANAME###' and used as keys.
	 *
	 * Example: If the prefix is "field" and the array has two elements "one"
	 * and "two", the subparts "###FIELD_ONE###" and "###FIELD_TWO###" will be
	 * hidden.
	 *
	 * If the prefix is empty and the array has two elements "one" and "two",
	 * the subparts "###ONE###" and "###TWO###" will be hidden.
	 *
	 * @param array array of subpart names to hide
	 *              (may be empty, case-insensitive, will get uppercased)
	 * @param string prefix to the subpart names (may be empty,
	 *               case-insensitive, will get uppercased)
	 */
	public function hideSubpartsArray(array $subparts, $prefix = '') {
		foreach ($subparts as $currentSubpartName) {
			$fullSubpartName = $this->createMarkerNameWithoutHashes(
				$currentSubpartName,
				$prefix
			);

			$this->subpartsToHide[$fullSubpartName] = true;
		}
	}

	/**
	 * Takes a comma-separated list of subpart names and unhides them if they
	 * have been hidden beforehand.
	 *
	 * Note: All subpartNames that are provided with the second parameter will
	 * not be unhidden. This is to avoid unhiding subparts that are hidden by
	 * the configuration.
	 *
	 * In the process, the names are changed from 'aname' to '###BLA_ANAME###'.
	 *
	 * Example: If the prefix is "field" and the list is "one,two", the subparts
	 * "###FIELD_ONE###" and "###FIELD_TWO###" will be unhidden.
	 *
	 * If the prefix is empty and the list is "one,two", the subparts
	 * "###ONE###" and "###TWO###" will be unhidden.
	 *
	 * @param string comma-separated list of at least 1 subpart name to
	 *               unhide (case-insensitive, will get uppercased),
	 *               must not be empty
	 * @param string comma-separated list of subpart names that
	 *               shouldn't get unhidden
	 * @param string prefix to the subpart names (may be empty,
	 *               case-insensitive, will get uppercased)
	 */
	public function unhideSubparts(
		$subparts, $permanentlyHiddenSubparts = '', $prefix = ''
	) {
		$subpartNames = explode(',', $subparts);
		if ($permanentlyHiddenSubparts != '') {
			$hiddenSubpartNames = explode(',', $permanentlyHiddenSubparts);
		} else {
			$hiddenSubpartNames = array();
		}

		$this->unhideSubpartsArray($subpartNames, $hiddenSubpartNames, $prefix);
	}

	/**
	 * Takes an array of subpart names and unhides them if they have been hidden
	 * beforehand.
	 *
	 * Note: All subpartNames that are provided with the second parameter will
	 * not be unhidden. This is to avoid unhiding subparts that are hidden by
	 * the configuration.
	 *
	 * In the process, the names are changed from 'aname' to '###BLA_ANAME###'.
	 *
	 * Example: If the prefix is "field" and the array has two elements "one"
	 * and "two", the subparts "###FIELD_ONE###" and "###FIELD_TWO###" will be
	 * unhidden.
	 *
	 * If the prefix is empty and the array has two elements "one" and "two",
	 * the subparts "###ONE###" and "###TWO###" will be unhidden.
	 *
	 * @param array array of subpart names to unhide
	 *              (may be empty, case-insensitive, will get uppercased)
	 * @param array array of subpart names that shouldn't get unhidden
	 * @param string prefix to the subpart names (may be empty,
	 *               case-insensitive, will get uppercased)
	 */
	public function unhideSubpartsArray(
		array $subparts, array $permanentlyHiddenSubparts = array(), $prefix = ''
	) {
		foreach ($subparts as $currentSubpartName) {
			// Only unhide the current subpart if it is not on the list of
			// permanently hidden subparts (e.g. by configuration).
			if (!in_array($currentSubpartName, $permanentlyHiddenSubparts)) {
				$currentMarkerName = $this->createMarkerNameWithoutHashes(
					$currentSubpartName, $prefix
				);
				unset($this->subpartsToHide[$currentMarkerName]);
			}
		}
	}

	/**
	 * Sets or hides a marker based on $condition.
	 * If $condition is true, this function sets the marker's content, working
	 * exactly like setMarker($markerName, $content, $markerPrefix).
	 * If $condition is false, this function removes the wrapping subpart,
	 * working exactly like hideSubparts($markerName, $wrapperPrefix).
	 *
	 * @param string the marker's name without the ### signs,
	 *               case-insensitive, will get uppercased, must not be empty
	 * @param boolean if this is true, the marker will be filled,
	 *                otherwise the wrapped marker will be hidden
	 * @param string content with which the marker will be filled, may be empty
	 * @param string prefix to the marker name for setting (may be empty,
	 *               case-insensitive, will get uppercased)
	 * @param string prefix to the subpart name for hiding (may be empty,
	 *               case-insensitive, will get uppercased)
	 *
	 * @return boolean true if the marker content has been set, false if
	 *                 the subpart has been hidden
	 *
	 * @see setMarkerContent
	 * @see hideSubparts
	 */
	public function setOrDeleteMarker($markerName, $condition, $content,
		$markerPrefix = '', $wrapperPrefix = ''
	) {
		if ($condition) {
			$this->setMarker($markerName, $content, $markerPrefix);
		} else {
			$this->hideSubparts($markerName, $wrapperPrefix);
		}

		return $condition;
	}

	/**
	 * Sets or hides a marker based on whether the (integer) content is
	 * non-zero.
	 * If intval($content) is non-zero, this function sets the marker's content,
	 * working exactly like setMarker($markerName, $content,
	 * $markerPrefix).
	 * If intval($condition) is zero, this function removes the wrapping
	 * subpart, working exactly like hideSubparts($markerName, $wrapperPrefix).
	 *
	 * @param string the marker's name without the ### signs,
	 *               case-insensitive, will get uppercased, must not be* empty
	 * @param integer content with which the marker will be filled, may be empty
	 * @param string prefix to the marker name for setting (may be empty,
	 *               case-insensitive, will get uppercased)
	 * @param string prefix to the subpart name for hiding (may be empty,
	 *               case-insensitive, will get uppercased)
	 *
	 * @return boolean true if the marker content has been set, false if
	 *                 the subpart has been hidden
	 *
	 * @see setOrDeleteMarker
	 * @see setOrDeleteMarkerIfNotEmpty
	 * @see setMarkerContent
	 * @see hideSubparts
	 */
	public function setOrDeleteMarkerIfNotZero($markerName, $content,
		$markerPrefix = '', $wrapperPrefix = ''
	) {
		return $this->setOrDeleteMarker(
			$markerName,
			(intval($content) != 0),
			((string) $content),
			$markerPrefix,
			$wrapperPrefix
		);
	}

	/**
	 * Sets or hides a marker based on whether the (string) content is
	 * non-empty.
	 * If $content is non-empty, this function sets the marker's content,
	 * working exactly like setMarker($markerName, $content,
	 * $markerPrefix).
	 * If $condition is empty, this function removes the wrapping subpart,
	 * working exactly like hideSubparts($markerName, $wrapperPrefix).
	 *
	 * @param string the marker's name without the ### signs,
	 *               case-insensitive, will get uppercased, must not be empty
	 * @param string content with which the marker will be filled, may be empty
	 * @param string prefix to the marker name for setting (may be empty,
	 *               case-insensitive, will get uppercased)
	 * @param string prefix to the subpart name for hiding (may be empty,
	 *               case-insensitive, will get uppercased)
	 *
	 * @return boolean true if the marker content has been set, false if
	 *                 the subpart has been hidden
	 *
	 * @see setOrDeleteMarker
	 * @see setOrDeleteMarkerIfNotZero
	 * @see setMarkerContent
	 * @see hideSubparts
	 */
	public function setOrDeleteMarkerIfNotEmpty($markerName, $content,
		$markerPrefix = '', $wrapperPrefix = ''
	) {
		return $this->setOrDeleteMarker(
			$markerName,
			(!empty($content)),
			$content,
			$markerPrefix,
			$wrapperPrefix
		);
	}

	/**
	 * Creates an uppercase marker (or subpart) name from a given name and an
	 * optional prefix, wrapping the result in three hash signs (###).
	 *
	 * Example: If the prefix is "field" and the marker name is "one", the
	 * result will be "###FIELD_ONE###".
	 *
	 * If the prefix is empty and the marker name is "one", the result will be
	 * "###ONE###".
	 */
	private function createMarkerName($markerName, $prefix = '') {
		return '###' .
			$this->createMarkerNameWithoutHashes($markerName, $prefix) . '###';
	}

	/**
	 * Creates an uppercase marker (or subpart) name from a given name and an
	 * optional prefix, but without wrapping it in hash signs.
	 *
	 * Example: If the prefix is "field" and the marker name is "one", the
	 * result will be "FIELD_ONE".
	 *
	 * If the prefix is empty and the marker name is "one", the result will be
	 * "ONE".
	 */
	private function createMarkerNameWithoutHashes($markerName, $prefix = '') {
		// If a prefix is provided, uppercases it and separates it with an
		// underscore.
		if (!empty($prefix)) {
			$prefix .= '_';
		}

		return strtoupper($prefix . trim($markerName));
	}

	/**
	 * Retrieves a named subpart, recursively filling in its inner subparts
	 * and markers. Inner subparts that are marked to be hidden will be
	 * substituted with empty strings.
	 *
	 * This function either works on the subpart with the name $key or the
	 * complete HTML template if $key is an empty string.
	 *
	 * @param string key of an existing subpart, for example 'LIST_ITEM'
	 *               (without the ###), or an empty string to use the
	 *               complete HTML template
	 *
	 * @return string the subpart content or an empty string if the
	 *                subpart is hidden or the subpart name is missing
	 */
	public function getSubpart($key = '') {
		if ($key != '') {
			if (!$this->isMarkerNameValidWithoutHashes($key)) {
				throw new Exception('The value of the parameter $key is not valid.');
			}

			if (!isset($this->templateCache[$key])) {
				throw new tx_oelib_notFoundException(
					'The parameter $key must be an existing subpart name.'
				);
			}

			if (!$this->isSubpartVisible($key)) {
				return '';
			}

			$templateCode = $this->templateCache[$key];
		} else {
			$templateCode = $this->templateCode;
		}

		// recursively replaces subparts with their contents
		$noSubpartMarkers = preg_replace_callback(
			'/<!-- *###([^#]*)### *-->(.*)' .
				'<!-- *###\1### *-->/msSU',
			array(
				$this,
				'getSubpartForCallback'
			),
			$templateCode
		);

		// replaces markers with their contents
		return str_replace(
			array_keys($this->markers), $this->markers, $noSubpartMarkers
		);
	}

	/**
	 * Retrieves a subpart.
	 *
	 * @param array numeric array with matches from
	 *              preg_replace_callback; the element #1 needs to
	 *              contain the name of the subpart to retrieve (in
	 *              uppercase without the surrounding ###)
	 *
	 * @return string the contents of the corresponding subpart or an
	 *                empty string in case the subpart does not exist
	 */
	private function getSubpartForCallback(array $matches) {
		return $this->getSubpart($matches[1]);
	}

	/**
	 * Checks whether a marker name (or subpart name) is valid (including the
	 * leading and trailing hashes ###).
	 *
	 * A valid marker name must be a non-empty string, consisting of uppercase
	 * and lowercase letters ranging A to Z, digits and underscores. It must
	 * start with a lowercase or uppercase letter ranging from A to Z. It must
	 * not end with an underscore. In addition, it must be prefixed and suffixed
	 * with ###.
	 *
	 * @param string marker name to check (with the hashes), may be empty
	 *
	 * @return boolean true if the marker name is valid, false otherwise
	 */
	private function isMarkerNameValidWithHashes($markerName) {
		return (boolean) preg_match(
			'/^###[a-zA-Z]([a-zA-Z0-9_]*[a-zA-Z0-9])?###$/', $markerName
		);
	}

	/**
	 * Checks whether a marker name (or subpart name) is valid (excluding the
	 * leading and trailing hashes ###).
	 *
	 * A valid marker name must be a non-empty string, consisting of uppercase
	 * and lowercase letters ranging A to Z, digits and underscores. It must
	 * start with a lowercase or uppercase letter ranging from A to Z. It must
	 * not end with an underscore.
	 *
	 * @param string marker name to check (without the hashes), may be empty
	 *
	 * @return boolean true if the marker name is valid, false otherwise
	 */
	private function isMarkerNameValidWithoutHashes($markerName) {
		return $this->isMarkerNameValidWithHashes('###'.$markerName.'###');
	}

	/**
	 * Resets the marker contents.
	 */
	public function resetMarkers() {
		$this->markers = array();
	}

	/**
	 * Resets the list of subparts to hide.
	 */
	public function resetSubpartsHiding() {
		$this->subpartsToHide = array();
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/oelib/class.tx_oelib_template.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/oelib/class.tx_oelib_template.php']);
}
?>