<?php

namespace Wikibase\Client\Usage\Sql;

use DatabaseBase;
use DBError;
use Exception;
use InvalidArgumentException;
use ResultWrapper;
use Wikibase\Client\Store\Sql\ConnectionManager;
use Wikibase\Client\Usage\SubscriptionManager;
use Wikibase\Client\Usage\UsageTrackerException;
use Wikibase\DataModel\Entity\EntityId;

/**
 * SubscriptionManager implementation backed by an SQL table.
 *
 * @see docs/usagetracking.wiki
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class SqlSubscriptionManager implements SubscriptionManager {

	/**
	 * @var ConnectionManager
	 */
	private $connectionManager;

	/**
	 * @param ConnectionManager $connectionManager
	 */
	public function __construct( ConnectionManager $connectionManager ) {
		$this->connectionManager = $connectionManager;
	}

	/**
	 * @param EntityId[] $entityIds
	 *
	 * @return string[]
	 */
	private function idsToString( array $entityIds ) {
		return array_map( function( EntityId $id ) {
			return $id->getSerialization();
		}, $entityIds );
	}

	/**
	 * @see SubscriptionManager::subscribe
	 *
	 * @param string $subscriber
	 * @param EntityId[] $entityIds
	 *
	 * @throws InvalidArgumentException
	 * @throws UsageTrackerException
	 * @throws Exception
	 */
	public function subscribe( $subscriber, array $entityIds ) {
		if ( !is_string( $subscriber ) ) {
			throw new InvalidArgumentException( '$subscriber must be a string.' );
		}

		$subscriptions = $this->idsToString( $entityIds );
		$db = $this->connectionManager->beginAtomicSection( __METHOD__ );

		try {
			$oldSubscriptions = $this->querySubscriptions( $db, $subscriber, $subscriptions );
			$newSubscriptions = array_diff( $subscriptions, $oldSubscriptions );
			$this->insertSubscriptions( $db, $subscriber, $newSubscriptions );

			$this->connectionManager->commitAtomicSection( $db, __METHOD__ );
		} catch ( Exception $ex ) {
			$this->connectionManager->rollbackAtomicSection( $db, __METHOD__ );

			if ( $ex instanceof DBError ) {
				throw new UsageTrackerException( $ex->getMessage(), $ex->getCode(), $ex );
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * @see SubscriptionManager::unsubscribe
	 *
	 * @param string $subscriber Global site ID of the client
	 * @param EntityId[] $entityIds The entities to subscribe to.
	 *
	 * @throws InvalidArgumentException
	 * @throws UsageTrackerException
	 * @throws Exception
	 */
	public function unsubscribe( $subscriber, array $entityIds ) {
		if ( !is_string( $subscriber ) ) {
			throw new InvalidArgumentException( '$subscriber must be a string.' );
		}

		$unsubscriptions = $this->idsToString( $entityIds );
		$db = $this->connectionManager->beginAtomicSection( __METHOD__ );

		try {
			$oldSubscriptions = $this->querySubscriptions( $db, $subscriber, $unsubscriptions );
			$obsoleteSubscriptions = array_intersect( $unsubscriptions, $oldSubscriptions );
			$this->deleteSubscriptions( $db, $subscriber, $obsoleteSubscriptions );

			$this->connectionManager->commitAtomicSection( $db, __METHOD__ );
		} catch ( Exception $ex ) {
			$this->connectionManager->rollbackAtomicSection( $db, __METHOD__ );

			if ( $ex instanceof DBError ) {
				throw new UsageTrackerException( $ex->getMessage(), $ex->getCode(), $ex );
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * For a set of potential subscriptions, returns the existing subscriptions.
	 *
	 * @param DatabaseBase $db
	 * @param string $subscriber
	 * @param string[] $subscriptions
	 *
	 * @return string[] Entity ID strings from $subscriptions which $subscriber is already subscribed to.
	 */
	private function querySubscriptions( DatabaseBase $db, $subscriber, array $subscriptions ) {
		if ( $subscriptions ) {
			$rows = $db->select(
				'wb_changes_subscription',
				'cs_entity_id',
				array(
					'cs_subscriber_id' => $subscriber,
					'cs_entity_id' => $subscriptions,
				),
				__METHOD__
			);

			$subscriptions = $this->extractField( $rows, 'cs_entity_id' );
		}

		return $subscriptions;
	}

	/**
	 * Inserts a set of subscriptions.
	 *
	 * @param DatabaseBase $db
	 * @param string $subscriber
	 * @param string[] $subscriptions
	 */
	private function insertSubscriptions( DatabaseBase $db, $subscriber, array $subscriptions ) {
		$rows = $this->makeSubscriptionRows( $subscriber, $subscriptions );

		$db->insert(
			'wb_changes_subscription',
			$rows,
			__METHOD__,
			array( 'IGNORE' )
		);
	}

	/**
	 * Inserts a set of subscriptions.
	 *
	 * @param DatabaseBase $db
	 * @param string $subscriber
	 * @param string[] $subscriptions
	 */
	private function deleteSubscriptions( DatabaseBase $db, $subscriber, array $subscriptions ) {
		if ( $subscriptions ) {
			$db->delete(
				'wb_changes_subscription',
				array(
					'cs_subscriber_id' => $subscriber,
					'cs_entity_id' => $subscriptions,
				),
				__METHOD__
			);
		}
	}

	/**
	 * Returns a list of rows for insertion, using DatabaseBase's multi-row insert mechanism.
	 * Each row is represented as array( $subscriber, $entityId ).
	 *
	 * @param string $subscriber
	 * @param string[] $subscriptions
	 *
	 * @return array[] rows
	 */
	private function makeSubscriptionRows( $subscriber, array $subscriptions ) {
		$rows = array();

		foreach ( $subscriptions as $entityId ) {
			$rows[] = array(
				'cs_entity_id' => $entityId,
				'cs_subscriber_id' => $subscriber
			);
		}

		return $rows;
	}

	/**
	 * @param object[]|ResultWrapper $rows Plain objects
	 * @param string $field The name of the field to extract from each plain object
	 *
	 * @return array
	 */
	private function extractField( $rows, $field ) {
		$values = array();

		foreach ( $rows as $row ) {
			$values[] = $row->$field;
		}

		return $values;
	}

}