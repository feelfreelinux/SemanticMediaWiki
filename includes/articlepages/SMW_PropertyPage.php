<?php

use SMW\ApplicationFactory;
use SMW\DataValueFactory;
use SMW\Localizer;
use SMW\RequestOptions;
use SMW\StringCondition;
use SMW\PropertyRegistry;
use SMWDataValue as DataValue;
use SMW\DataValues\ValueFormatters\DataValueFormatter;
use SMW\DIProperty;

/**
 * Implementation of MediaWiki's Article that shows additional information on
 * property pages. Very similar to CategoryPage, but with different printout
 * that also displays values for each subject with the given property.
 *
 * @ingroup SMW
 *
 * @author Markus Krötzsch
 */
class SMWPropertyPage extends SMWOrderedListPage {

	/**
	 * @see SMWOrderedListPage::initParameters()
	 * @note We use a smaller limit here; property pages might become large.
	 */
	protected function initParameters() {
		global $smwgPropertyPagingLimit;
		$this->limit = $smwgPropertyPagingLimit;
		$this->mProperty = DIProperty::newFromUserLabel( $this->mTitle->getText() );
		$this->store = ApplicationFactory::getInstance()->getStore();
		$this->propertyValue = DataValueFactory::getInstance()->newDataItemValue( $this->mProperty );
		return true;
	}

	/**
	 * Returns the HTML which is added to $wgOut after the article text.
	 *
	 * @return string
	 */
	protected function getHtml() {

		if ( !$this->store->getRedirectTarget( $this->mProperty )->equals( $this->mProperty ) ) {
			return '';
		}

		$dv = DataValueFactory::getInstance()->newDataValueByItem(
			$this->mProperty
		);

		$title = $dv->getFormattedLabel( DataValueFormatter::WIKI_LONG );
		$this->getContext()->getOutput()->setPageTitle( $title );

		$list = $this->getSubpropertyList() . $this->getPropertyValueList();
		$result = ( $list !== '' ? Html::element( 'div', array( 'id' => 'smwfootbr' ) ) . $list : '' );

		return $result;
	}

	/**
	 * Returns an introductory text for a predefined property
	 *
	 * @note In order to enable a more detailed description for a specific
	 * predefined property a concatenated message key can be used (e.g
	 * 'smw-pa-property-predefined' + <internal property key> => '_asksi' )
	 *
	 * @since 1.9
	 *
	 * @return string
	 */
	protected function getIntroductoryText() {

		$dv = DataValueFactory::getInstance()->newDataValueByItem(
			$this->mProperty
		);

		$propertyName = $dv->getFormattedLabel();
		$message = '';

		if ( $this->mProperty->isUserDefined() ) {

			if ( $this->store->getPropertyTableInfoFetcher()->isFixedTableProperty( $this->mProperty ) ) {
				$message = Html::rawElement( 'div', array( 'class' => 'plainlinks' ), wfMessage( 'smw-property-userdefined-fixedtable', $propertyName )->parse() );
			}

			return $message;
		}

		$key = $this->mProperty->getKey();

		if ( ( $messageKey = PropertyRegistry::getInstance()->findPropertyDescriptionMsgKeyById( $key ) ) !== '' ) {
			$messageKeyLong = $messageKey . '-long';
		} else {
			$messageKey = 'smw-pa-property-predefined' . strtolower( $key );
			$messageKeyLong = 'smw-pa-property-predefined-long' . strtolower( $key );
		}

		$message .= wfMessage( $messageKey )->exists() ? wfMessage( $messageKey, $propertyName )->parse() : wfMessage( 'smw-pa-property-predefined-default', $propertyName )->parse();
		$message .= wfMessage( $messageKeyLong )->exists() ? ' ' . wfMessage( $messageKeyLong )->parse() : '';
		$message .= ' ' . wfMessage( 'smw-pa-property-predefined-common' )->parse();

		return Html::rawElement( 'div', array( 'class' => 'smw-property-predefined-intro plainlinks' ), $message );
	}

	protected function getTopIndicator() {

		$propertyName = htmlspecialchars( $this->mTitle->getText() );
		$usageCountHtml = '';

		$requestOptions = new RequestOptions();
		$requestOptions->setLimit( 1 );
		$requestOptions->addStringCondition( $propertyName, StringCondition::COND_EQ );

		$cachedLookupList = $this->store->getPropertiesSpecial( $requestOptions );
		$usageList = $cachedLookupList->fetchList();

		if ( $usageList && $usageList !== array() ) {
			$usage = end( $usageList );
			$usageCount = $usage[1];
			$usageCountHtml = Html::rawElement(
				'div', array(
					'title' => $this->getContext()->getLanguage()->timeanddate( $cachedLookupList->getTimestamp() ),
					'class' => 'smw-page-indicator usage-count' . ( $usageCount < 25000 ? ( $usageCount > 5000 ? ' moderate' : '' ) : ' high' ) ),
				$usageCount
			);
		}

		return Html::rawElement( 'div', array(), Html::rawElement(
				'div', array(
				'class' => 'smw-page-indicator property-type',
				'title' => wfMessage( 'smw-page-indicator-type-info', $this->mProperty->isUserDefined() )->parse()
			), ( $this->mProperty->isUserDefined() ? 'U' : 'S' )
		) . $usageCountHtml );
	}

	/**
	 * Get the HTML for displaying subproperties of this property. This list
	 * is usually short and we implement no additional navigation.
	 *
	 * @return string
	 */
	protected function getSubpropertyList() {

		$more = false;
		$requestOptions = new RequestOptions();
		$requestOptions->sort = true;
		$requestOptions->ascending = true;

		// +1 look-ahead
		$requestOptions->setLimit( $GLOBALS['smwgSubPropertyListLimit'] + 1 );
		$subproperties = $this->store->getPropertySubjects( new DIProperty( '_SUBP' ), $this->getDataItem(), $requestOptions );

		// Pop the +1 look-ahead from the list
		if ( count( $subproperties ) > $GLOBALS['smwgSubPropertyListLimit'] ) {
			array_pop( $subproperties );
			$more = true;
		}

		$result = '';
		$resultCount = count( $subproperties );

		if ( $more ) {
			$message = Html::rawElement(
				'span',
				array( 'class' => 'plainlinks' ),
				wfMessage( 'smw-subpropertylist-count-with-restricted-note', $resultCount, $GLOBALS['smwgSubPropertyListLimit'] )->parse()
			);
		} else {
			$message = wfMessage( 'smw-subpropertylist-count', $resultCount )->text();
		}

		if ( $resultCount > 0 ) {
			$titleText = htmlspecialchars( $this->mTitle->getText() );
			$result .= "<div id=\"mw-subcategories\">\n<h2>" . wfMessage( 'smw_subproperty_header', $titleText )->text() . "</h2>\n<p>";

			if ( !$this->mProperty->isUserDefined() ) {
				$result .= wfMessage( 'smw_isspecprop' )->text() . ' ';
			}

			$result .= $message . "</p>"  ."\n";

			if ( $resultCount < 6 ) {
				$result .= SMWPageLister::getShortList( 0, $resultCount, $subproperties, null );
			} else {
				$result .= SMWPageLister::getColumnList( 0, $resultCount, $subproperties, null );
			}

			$result .= "\n</div>";
		}

		return $result;
	}

	/**
	 * Get the HTML for displaying values of this property, based on the
	 * current from/until and limit settings.
	 *
	 * @return string
	 */
	protected function getPropertyValueList() {
		global $smwgPropertyPagingLimit, $wgRequest;

		 // limit==0: configuration setting to disable this completely
		if ( $this->limit < 1 ) {
			return '';
		}

		$diWikiPages = array();
		$options = SMWPageLister::getRequestOptions( $this->limit, $this->from, $this->until );

		$options->limit = $wgRequest->getVal( 'limit', $smwgPropertyPagingLimit );
		$options->offset = $wgRequest->getVal( 'offset', '0' );

		if ( ( $value = $wgRequest->getVal( 'value', '' ) ) !== '' ) {
			$diWikiPages = $this->doQuerySubjectListWithValue( $value, $options );
		} else {
			$diWikiPages = $this->store->getAllPropertySubjects( $this->mProperty, $options );
		}

		if ( !$options->ascending ) {
			$diWikiPages = array_reverse( $diWikiPages );
		}

		$result = '';

		if ( count( $diWikiPages ) > 0 ) {
			$pageLister = new SMWPageLister( $diWikiPages, null, $this->limit, $this->from, $this->until );

			$this->mTitle->setFragment( '#SMWResults' ); // Make navigation point to the result list.
			$navigation = $pageLister->getNavigationLinks( $this->mTitle );

			$dvWikiPage = DataValueFactory::getInstance()->newDataValueByItem(
				$this->mProperty
			);

			// Allow the DV formatter to access a specific language code
			$dvWikiPage->setOption(
				DataValue::OPT_USER_LANGUAGE,
				Localizer::getInstance()->getUserLanguage()->getCode()
			);

			$titleText = htmlspecialchars( $dvWikiPage->getWikiValue() );
			$resultNumber = min( $this->limit, count( $diWikiPages ) );

			$result .= "<a name=\"SMWResults\"></a><div id=\"mw-pages\">\n" .
			           '<h2>' . wfMessage( 'smw_attribute_header', $titleText )->text() . "</h2>\n<p>";

			$result .= $this->getNavigationLinks( 'smw_attributearticlecount', $diWikiPages, $smwgPropertyPagingLimit ) .
			           $this->subjectObjectList( $diWikiPages ) . "\n</div>";
		}

		return $result;
	}

	/**
	 * Format $diWikiPages chunked by letter in a table that shows subject
	 * articles in one column and object articles/values in the other one.
	 *
	 * @param $diWikiPages array
	 * @return string
	 */
	protected function subjectObjectList( array $diWikiPages ) {
		global $wgContLang, $smwgMaxPropertyValues;

		$ac = count( $diWikiPages );

		if ( $ac > $this->limit ) {
			if ( $this->until !== '' ) {
				$start = 1;
			} else {
				$start = 0;
				$ac = $ac - 1;
			}
		} else {
			$start = 0;
		}

		$r = '<table class="property-page-results" style="width: 100%;" cellspacing="0" cellpadding="0">';
		$prev_start_char = 'None';

		for ( $index = $start; $index < $ac; $index++ ) {
			$diWikiPage = $diWikiPages[$index];
			$dvWikiPage = DataValueFactory::getInstance()->newDataValueByItem( $diWikiPage, null );

			$sortkey = $this->store->getWikiPageSortKey( $diWikiPage );
			$start_char = $wgContLang->convert( $wgContLang->firstChar( $sortkey ) );

			// Header for index letters
			if ( $start_char != $prev_start_char ) {
				$r .= '<tr class="header-row" ><th class="smwpropname"><div class="header-title">' . htmlspecialchars( $start_char ) . "</div></th><th></th></tr>\n";
				$prev_start_char = $start_char;
			}

			// Property name
			$searchlink = SMWInfolink::newBrowsingLink( '+', $dvWikiPage->getWikiValue() );
			$r .= '<tr class="value-row" ><td class="smwpropname">' . $dvWikiPage->getShortHTMLText( smwfGetLinker() ) .
			      '&#160;' . $searchlink->getHTML( smwfGetLinker() ) . '</td><td class="smwprops">';

			// Property values
			$ropts = new RequestOptions();
			$ropts->limit = $smwgMaxPropertyValues + 1;
			$values = $this->store->getPropertyValues( $diWikiPage, $this->mProperty, $ropts );
			$i = 0;

			foreach ( $values as $di ) {
				if ( $i != 0 ) {
					$r .= ', ';
				}
				$i++;

				if ( $i < $smwgMaxPropertyValues + 1 ) {
					$dv = DataValueFactory::getInstance()->newDataValueByItem( $di, $this->mProperty );

					$dv->setOutputFormat( 'LOCL' );

					$r .= $dv->getShortHTMLText( smwfGetLinker() ) . $dv->getInfolinkText( SMW_OUTPUT_HTML, smwfGetLinker() );
				} else {
					$searchlink = SMWInfolink::newInversePropertySearchLink( '…', $dvWikiPage->getWikiValue(), $this->mTitle->getText() );
					$r .= $searchlink->getHTML( smwfGetLinker() );
				}
			}

			$r .= "</td></tr>\n";
		}

		$r .= '</table>';

		return $r;
	}

	private function doQuerySubjectListWithValue( $value, $options ) {

		$applicationFactory = ApplicationFactory::getInstance();

		$dataValue = $applicationFactory->getDataValueFactory()->newDataValueByProperty( $this->mProperty );
		$dataValue->setOption( DataValue::OPT_QUERY_CONTEXT, true );
		$dataValue->setUserValue( $value );
		$queryFactory = $applicationFactory->getQueryFactory();

		$description = $queryFactory->newDescriptionFactory()->newFromDataValue(
			$dataValue
		);

		$query = $queryFactory->newQuery( $description );
		$query->setLimit( $options->limit );
		$query->setOffset( $options->offset );
		$query->setSortKeys( array( '' => 'asc' ) );

		return $this->store->getQueryResult( $query )->getResults();
	}

}
