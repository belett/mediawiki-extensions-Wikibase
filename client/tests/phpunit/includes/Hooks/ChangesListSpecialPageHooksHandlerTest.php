<?php

namespace Wikibase\Client\Tests\Hooks;

use FauxRequest;
use FormOptions;
use SpecialPageFactory;
use Wikibase\Client\Hooks\ChangesListSpecialPageHooksHandler;

/**
 * @covers Wikibase\Client\Hooks\ChangesListSpecialPageHooksHandler
 *
 * @group WikibaseClientHooks
 * @group WikibaseClient
 * @group Wikibase
 */
class ChangesListSpecialPageHooksHandlerTest extends \PHPUnit_Framework_TestCase {

	public function testOnChangesListSpecialPageFilters() {
		$user = $this->getUser(
			array(
				array( 'usernewrc' => 0 )
			)
		);

		$specialPage = SpecialPageFactory::getPage( 'Recentchanges' );
		$specialPage->getContext()->setUser( $user );

		$filters = array();

		ChangesListSpecialPageHooksHandler::onChangesListSpecialPageFilters(
			$specialPage,
			$filters
		);

		$expected = array(
			'hideWikibase' => array(
		        'msg' => 'wikibase-rc-hide-wikidata',
				'default' => true
			)
		);

		$this->assertSame( $expected, $filters );
	}

	public function testOnChangesListSpecialPageQuery() {
		$tables = array( 'recentchanges' );
		$fields = array( 'rc_id' );
		$conds = array();
		$query_options = array();
		$join_conds = array();

		$opts = new FormOptions();

		ChangesListSpecialPageHooksHandler::onChangesListSpecialPageQuery(
			'RecentChanges',
			$tables,
			$fields,
			$conds,
			$query_options,
			$join_conds,
			$opts
		);

		// this is just a sanity check
		$this->assertInternalType( 'array', $conds );
	}

	/**
	 * @dataProvider addWikibaseConditionsProvider
	 */
	public function testAddWikibaseConditions(
		array $expected,
		array $userOptions,
		$optionDefault
	) {
		$hookHandler = new ChangesListSpecialPageHooksHandler(
			$this->getRequest( array() ),
			$this->getUser( $userOptions ),
			'Watchlist',
			true
		);

		$opts = new FormOptions();
		$opts->add( 'hideWikibase', $optionDefault );

		$conds = array();
		$hookHandler->addWikibaseConditions( $conds, $opts );

		$this->assertEquals( $expected, $conds );
	}

	public function addWikibaseConditionsProvider() {
		return array(
			array(
				array(),
				array( 'usenewrc' => 0, 'wlshowwikibase' => 1 ),
				false
			),
			array(
				array( 'rc_type != 5' ),
				array( 'usenewrc' => 0 ),
				true
			)
		);
	}

	public function testAddWikibaseConditions_wikibaseChangesDisabled() {
		$hookHandler = new ChangesListSpecialPageHooksHandler(
			$this->getRequest( array() ),
			$this->getUser( array( 'usenewrc' => 1 ) ),
			'Watchlist',
			true
		);

		$opts = new FormOptions();

		$conds = array();
		$hookHandler->addWikibaseConditions( $conds, $opts );

		$this->assertEquals( array( 'rc_type != 5' ), $conds );
	}

	/**
	 * @dataProvider filterNotAddedWhenUsingEnhancedChangesProvider
	 */
	public function testFilterNotAddedWhenUsingEnhancedChanges(
		array $requestParams,
		array $userOptions,
		$pageName,
		$message
	) {
		$hookHandler = new ChangesListSpecialPageHooksHandler(
			$this->getRequest( $requestParams ),
			$this->getUser( $userOptions ),
			$pageName,
			true
		);

		$filters = array();
		$hookHandler->addFilterIfEnabled( $filters );

		$this->assertSame( array(), $filters, $message );
	}

	public function filterNotAddedWhenUsingEnhancedChangesProvider() {
		return array(
			array(
				array(),
				array( 'usenewrc' => 1 ),
				'Watchlist',
				'enhanced default pref for Watchlist'
			),
			array(
				array(),
				array( 'usenewrc' => 1 ),
				'RecentChanges',
				'enhanced default pref for RecentChanges'
			),
			array(
				array( 'enhanced' => 1 ),
				array( 'usenewrc' => 0 ),
				'Watchlist',
				'enhanced not default but has enhanced=1 req param'
			),
			array(
				array( 'enhanced' => 1 ),
				array( 'usenewrc' => 0 ),
				'RecentChanges',
				'enhanced not default but has enhanced=1 req param'
			),
			array(
				array( 'enhanced' => 1 ),
				array( 'usenewrc' => 1 ),
				'Watchlist',
				'enhanced default and has enhanced=1 req param'
			),
			array(
				array( 'enhanced' => 1 ),
				array( 'usenewrc' => 1 ),
				'RecentChangesLinked',
				'enhanced default and has enhanced=1 req param'
			),
		);
	}

	/**
	 * @dataProvider filter_withoutShowWikibaseEditsByDefaultPreference
	 */
	public function testFilter_withoutShowWikibaseEditsByDefaultPreference(
		array $requestParams,
		array $userOptions,
		$expectedFilterName,
		$expectedToggleDefault,
		$specialPageName
	) {
		$hookHandler = new ChangesListSpecialPageHooksHandler(
			$this->getRequest( $requestParams ),
			$this->getUser( $userOptions ),
			$specialPageName,
			true
		);

		$filters = array();
		$hookHandler->addFilterIfEnabled( $filters );

		$expected = array(
			$expectedFilterName => array(
				'msg' => 'wikibase-rc-hide-wikidata',
				'default' => $expectedToggleDefault
			)
		);

		$this->assertSame( $expected, $filters );
	}

	public function filter_withoutShowWikibaseEditsByDefaultPreference() {
		return array(
			array(
				array(),
				array( 'usenewrc' => 0 ),
				'hideWikibase',
				true,
				'Watchlist'
			),
			array(
				array( 'enhanced' => 0 ),
				array( 'usenewrc' => 1 ),
				'hideWikibase',
				true,
				'RecentChanges'
			),
			array(
				array(),
				array( 'usenewrc' => 0 ),
				'hideWikibase',
				true,
				'RecentChangesLinked'
			),
			array(
				array( 'action' => 'submit', 'hideWikibase' => 0 ),
				array( 'usenewrc' => 0 ),
				'hideWikibase',
				false,
				'Watchlist'
			),
			array(
				array( 'action' => 'submit', 'hideWikibase' => 1 ),
				array( 'usenewrc' => 0 ),
				'hideWikibase',
				true,
				'Watchlist'
			),
			array(
				array( 'action' => 'submit' ),
				array( 'usenewrc' => 0 ),
				'hideWikibase',
				false,
				'Watchlist'
			)
		);
	}

	/**
	 * @dataProvider filter_withShowWikibaseEditsByDefaultPreference
	 */
	public function testFilter_withShowWikibaseEditsByDefaultPreference(
		array $requestParams,
		array $userOptions,
		$expectedFilterName,
		$expectedToggleDefault,
		$specialPageName
	) {
		$hookHandler = new ChangesListSpecialPageHooksHandler(
			$this->getRequest( $requestParams ),
			$this->getUser( $userOptions ),
			$specialPageName,
			true
		);

		$filters = array();
		$hookHandler->addFilterIfEnabled( $filters );

		$expected = array(
			$expectedFilterName => array(
				'msg' => 'wikibase-rc-hide-wikidata',
				'default' => $expectedToggleDefault
			)
		);

		$this->assertSame( $expected, $filters );
	}

	public function filter_withShowWikibaseEditsByDefaultPreference() {
		return array(
			array(
				array(),
				array( 'wlshowwikibase' => 1, 'usenewrc' => 0 ),
				'hideWikibase',
				false,
				'Watchlist'
			),
			array(
				array( 'enhanced' => 0 ),
				array( 'rcshowwikidata' => 1, 'usenewrc' => 1 ),
				'hideWikibase',
				false,
				'RecentChanges'
			),
			array(
				array(),
				array( 'rcshowwikidata' => 1, 'usenewrc' => 0 ),
				'hideWikibase',
				false,
				'RecentChangesLinked'
			),
			array(
				array( 'action' => 'submit', 'hideWikibase' => 0 ),
				array( 'wlshowwikibase' => 1, 'usenewrc' => 0 ),
				'hideWikibase',
				false,
				'Watchlist'
			),
			array(
				array( 'action' => 'submit' ),
				array( 'wlshowwikibase' => 1, 'usenewrc' => 0 ),
				'hideWikibase',
				false,
				'Watchlist'
			)
		);
	}

	/**
	 * @dataProvider filterNotAddedWhenExternalRecentChangesDisabledProvider() {
	 */
	public function testFilterNotAddedWhenExternalRecentChangesDisabled( $specialPageName ) {
		$hookHandler = new ChangesListSpecialPageHooksHandler(
			$this->getRequest( array() ),
			$this->getUser( array( 'usenewrc' => 0 ) ),
			$specialPageName,
			false
		);

		$filters = array();
		$hookHandler->addFilterIfEnabled( $filters );

		$this->assertSame( array(), $filters );
	}

	public function filterNotAddedWhenExternalRecentChangesDisabledProvider() {
		return array(
			array( 'Watchlist' ),
			array( 'RecentChanges' ),
			array( 'RecentChangesLinked' )
		);
	}

	private function getRequest( array $requestParams ) {
		return new FauxRequest( $requestParams );
	}

	private function getUser( array $userOptions ) {
		$user = $this->getMockBuilder( 'User' )
			->disableOriginalConstructor()
			->getMock();

		$user->expects( $this->any() )
			->method( 'getOption' )
			->will( $this->returnCallback( function( $optionName ) use ( $userOptions ) {
				foreach ( $userOptions as $key => $value ) {
					if ( $optionName === $key ) {
						return $value;
					}
				}

				return null;
			} ) );

		return $user;
	}

}