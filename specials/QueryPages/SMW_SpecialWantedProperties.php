<?php
/**
 * @author Markus Krötzsch
 *
 * This page shows all used attributes.
 */

if (!defined('MEDIAWIKI')) die();

global $smwgIP;
include_once( "$smwgIP/specials/QueryPages/SMW_QueryPage.php" );

class WantedPropertiesPage extends SMWQueryPage {

	function getName() {
		/// TODO: should probably use SMW prefix
		return "WantedProperties";
	}

	function isExpensive() {
		return false; /// disables caching for now
	}

	function isSyndicated() { 
		return false; ///TODO: why not?
	}

	function getPageHeader() {
		return '<p>' . wfMsg('smw_wantedproperties_docu') . "</p><br />\n";
	}

	function formatResult( $skin, $result ) {
		global $wgLang, $wgExtraNamespaces;
		$proplink = $skin->makeLinkObj($result[0], $result[0]->getText(), 'action=view');
		return wfMsg('smw_wantedproperty_template', $proplink, $result[1]);
	}
	
	function getResults($requestoptions) {
		return smwfGetStore()->getWantedPropertiesSpecial($requestoptions);
	}

}

