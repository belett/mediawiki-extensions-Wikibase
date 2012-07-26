<?php

namespace Diff\Test;
use \Diff\GenericArrayObject as GenericArrayObject;

/**
 * Tests for the Diff\GenericArrayObject and deriving classes.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Diff
 * @ingroup Test
 * @group Diff
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class GenericArrayObjectTest extends \MediaWikiTestCase {

	public abstract function elementInstancesProvider();

	public abstract function instanceProvider();

	public abstract function getInstanceClass();

	/**
	 * @return GenericArrayObject
	 */
	protected function getNew( array $elements = array() ) {
		$class = $this->getInstanceClass();
		return new $class( $elements );
	}

	/**
	 * @dataProvider elementInstancesProvider
	 * @param array $elements
	 */
	public function testConstructor( array $elements ) {
		$arrayObject = $this->getNew( $elements );

		$this->assertEquals( count( $elements ), $arrayObject->count() );
	}

	/**
	 * @dataProvider elementInstancesProvider
	 * @param array $elements
	 */
	public function testIsEmpty( array $elements ) {
		$arrayObject = $this->getNew( $elements );

		$this->assertEquals( $elements === array(), $arrayObject->isEmpty() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param GenericArrayObject $list
	 */
	public function testUnset( GenericArrayObject $list ) {
		if ( !$list->isEmpty() ) {
			$offset = $list->getIterator()->key();
			$count = $list->count();
			$list->offsetUnset( $offset );
			$this->assertEquals( $count - 1, $list->count() );
		}

		if ( !$list->isEmpty() ) {
			$offset = $list->getIterator()->key();
			$count = $list->count();
			unset( $list[$offset] );
			$this->assertEquals( $count - 1, $list->count() );
		}

		$exception = null;
		try { $list->offsetUnset( 'sdfsedtgsrdysftu' ); } catch ( \Exception $exception ){}
		$this->assertInstanceOf( '\Exception', $exception );
	}

	/**
	 * @dataProvider elementInstancesProvider
	 * @param array $elements
	 */
	public function testAppend( array $elements ) {
		$list = $this->getNew();

		$listSize = count( $elements );

		foreach ( $elements as $element ) {
			$list->append( $element );
		}

		$this->assertEquals( $listSize, $list->count() );

		$list = $this->getNew();

		foreach ( $elements as $element ) {
			$list[] = $element;
		}

		$this->assertEquals( $listSize, $list->count() );

		$this->checkTypeChecks( function( GenericArrayObject $list, $element ) {
			$list->append( $element );
		} );
	}

	protected function checkTypeChecks( $function ) {
		$excption = null;
		$list = $this->getNew();

		$elementClass = $list->getObjectType();

		foreach ( array( 42, 'foo', array(), new \stdClass(), 4.2 ) as $element ) {
			$validValid = $element instanceof $elementClass;

			try{
				call_user_func( $function, $list, $element );
				$valid = true;
			}
			catch ( \MWException $exception ) {
				$valid = false;
			}

			$this->assertEquals(
				$validValid,
				$valid,
				'Object of invalid type got successfully added to a GenericArrayObject'
			);
		}
	}

	/**
	 * @dataProvider elementInstancesProvider
	 * @param array $elements
	 */
	public function testOffsetSet( array $elements ) {
		if ( $elements === array() ) {
			$this->assertTrue( true );
			return;
		}

		$list = $this->getNew();

		$element = reset( $elements );
		$list->offsetSet( 42, $element );
		$this->assertEquals( $element, $list->offsetGet( 42 ) );

		$list = $this->getNew();

		$element = reset( $elements );
		$list['oHai'] = $element;
		$this->assertEquals( $element, $list['oHai'] );

		$list = $this->getNew();

		$element = reset( $elements );
		$list->offsetSet( 9001, $element );
		$this->assertEquals( $element, $list[9001] );

		$list = $this->getNew();

		$element = reset( $elements );
		$list->offsetSet( null, $element );
		$this->assertEquals( $element, $list[0] );

		$list = $this->getNew();
		$offset = 0;

		foreach ( $elements as $element ) {
			$list->offsetSet( null, $element );
			$this->assertEquals( $element, $list[$offset++] );
		}

		$this->assertEquals( count( $elements ), $list->count() );

		$this->checkTypeChecks( function( GenericArrayObject $list, $element ) {
			$list->offsetSet( mt_rand(), $element );
		} );
	}

}
