<?php

namespace Wikibase\Repo\Api;

use ApiBase;
use ApiMain;
use DataValues\DataValue;
use DataValues\DataValueFactory;
use DataValues\IllegalValueException;
use DataValues\StringValue;
use InvalidArgumentException;
use LogicException;
use UsageException;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\TypedValueFormatter;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module for using value formatters.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Addshore
 * @author Marius Hoch
 */
class FormatSnakValue extends ApiBase {

	/**
	 * @var OutputFormatValueFormatterFactory
	 */
	private $valueFormatterFactory;

	/**
	 * @var OutputFormatSnakFormatterFactory
	 */
	private $snakFormatterFactory;

	/**
	 * @var DataValueFactory
	 */
	private $dataValueFactory;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 *
	 * @see ApiBase::__construct
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $this->getContext() );

		$this->setServices(
			$wikibaseRepo->getValueFormatterFactory(),
			$wikibaseRepo->getSnakFormatterFactory(),
			$wikibaseRepo->getDataValueFactory(),
			$apiHelperFactory->getErrorReporter( $this )
		);
	}

	public function setServices(
		OutputFormatValueFormatterFactory $valueFormatterFactory,
		OutputFormatSnakFormatterFactory $snakFormatterFactory,
		DataValueFactory $dataValueFactory,
		ApiErrorReporter $apiErrorReporter
	) {
		$this->valueFormatterFactory = $valueFormatterFactory;
		$this->snakFormatterFactory = $snakFormatterFactory;
		$this->dataValueFactory = $dataValueFactory;
		$this->errorReporter = $apiErrorReporter;
	}

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.1
	 */
	public function execute() {
		$this->getMain()->setCacheMode( 'public' );

		$params = $this->extractRequestParams();
		$this->requireMaxOneParameter( $params, 'property', 'datatype' );

		$value = $this->decodeDataValue( $params['datavalue'] );
		$dataTypeId = $this->getDataTypeId( $params );

		$valueFormatter = $this->getValueFormatter();

		$snak = null;
		if ( isset( $params['property'] ) ) {
			$snak = $this->decodeSnak( $params['property'], $value );
		}

		if ( $snak ) {
			$snakFormatter = $this->getSnakFormatter();
			$formattedValue = $snakFormatter->formatSnak( $snak );
		} elseif ( $valueFormatter instanceof TypedValueFormatter ) {
			// use data type id, if we can
			$formattedValue = $valueFormatter->formatValue( $value, $dataTypeId );
		} else {
			// rely on value type
			$formattedValue = $valueFormatter->format( $value );
		}

		$this->getResult()->addValue(
			null,
			'result',
			$formattedValue
		);
	}

	/**
	 * @throws LogicException
	 * @return ValueFormatter
	 */
	private function getValueFormatter() {
		$params = $this->extractRequestParams();

		$options = $this->getOptionsObject( $params['options'] );
		$formatter = $this->valueFormatterFactory->getValueFormatter( $params['generate'], $options );

		// Paranoid check:
		// should never fail since we only accept well known values for the 'generate' parameter
		if ( $formatter === null ) {
			throw new LogicException(
				'Could not obtain a ValueFormatter instance for ' . $params['generate']
			);
		}

		return $formatter;
	}

	/**
	 * @throws LogicException
	 * @return SnakFormatter
	 */
	private function getSnakFormatter() {
		$params = $this->extractRequestParams();

		$options = $this->getOptionsObject( $params['options'] );
		$formatter = $this->snakFormatterFactory->getSnakFormatter( $params['generate'], $options );

		return $formatter;
	}

	/**
	 * @param string $propertyIdSerialization
	 * @param DataValue $dataValue
	 *
	 * @return PropertyValueSnak
	 */
	private function decodeSnak( $propertyIdSerialization, DataValue $dataValue ) {
		try {
			$propertyId = new PropertyId( $propertyIdSerialization );
		} catch ( InvalidArgumentException $ex ) {
			$this->errorReporter->dieException( $ex, 'badpropertyid' );
		}

		return new PropertyValueSnak( $propertyId, $dataValue );
	}

	/**
	 * @param string $json A JSON-encoded DataValue
	 *
	 * @throws UsageException
	 * @throws LogicException
	 * @return DataValue
	 */
	private function decodeDataValue( $json ) {
		$data = json_decode( $json, true );

		if ( !is_array( $data ) ) {
			$this->errorReporter->dieError( 'Failed to decode datavalue', 'baddatavalue' );
		}

		try {
			$value = $this->dataValueFactory->newFromArray( $data );
			return $value;
		} catch ( IllegalValueException $ex ) {
			$this->errorReporter->dieException( $ex, 'baddatavalue' );
		}

		throw new LogicException( 'ApiErrorReporter::dieException did not throw a UsageException' );
	}

	/**
	 * @param string|null $optionsParam
	 *
	 * @return FormatterOptions
	 */
	private function getOptionsObject( $optionsParam ) {
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( ValueFormatter::OPT_LANG, $this->getLanguage()->getCode() );

		if ( is_string( $optionsParam ) && $optionsParam !== '' ) {
			$options = json_decode( $optionsParam, true );

			if ( is_array( $options ) ) {
				foreach ( $options as $name => $value ) {
					$formatterOptions->setOption( $name, $value );
				}
			}
		}

		return $formatterOptions;
	}

	/**
	 * Returns the data type ID specified by the parameters.
	 *
	 * @param array $params
	 *
	 * @return string|null
	 */
	private function getDataTypeId( array $params ) {
		//TODO: could be looked up based on a property ID
		return $params['datatype'];
	}

	/**
	 * @see ApiBase::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array(
			'generate' => array(
				self::PARAM_TYPE => array(
					SnakFormatter::FORMAT_PLAIN,
					SnakFormatter::FORMAT_WIKI,
					SnakFormatter::FORMAT_HTML,
					SnakFormatter::FORMAT_HTML_WIDGET,
				),
				self::PARAM_DFLT => SnakFormatter::FORMAT_WIKI,
				self::PARAM_REQUIRED => false,
			),
			'datavalue' => array(
				self::PARAM_TYPE => 'text',
				self::PARAM_REQUIRED => true,
			),
			'datatype' => array(
				self::PARAM_TYPE => WikibaseRepo::getDefaultInstance()->getDataTypeFactory()->getTypeIds(),
				self::PARAM_REQUIRED => false,
			),
			'property' => array(
				self::PARAM_TYPE => 'text',
				self::PARAM_REQUIRED => false,
			),
			'options' => array(
				self::PARAM_TYPE => 'text',
				self::PARAM_REQUIRED => false,
			),
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		$query = 'action=' . $this->getModuleName();
		$hello = new StringValue( 'hello' );
		$acme = new StringValue( 'http://acme.org' );

		return array(
			$query . '&' . wfArrayToCgi( array(
				'datavalue' => json_encode( $hello->toArray() ),
			) ) => 'apihelp-wbformatvalue-example-1',

			$query . '&' . wfArrayToCgi( array(
				'datavalue' => json_encode( $acme->toArray() ),
				'datatype' => 'url',
				'generate' => 'text/html',
			) ) => 'apihelp-wbformatvalue-example-2',

			//TODO: example for the options parameter, once we have something sensible to show there.
		);
	}

}
