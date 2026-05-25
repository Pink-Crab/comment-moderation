<?php
/**
 * End-to-end test for the wpdb-backed Rule_Repository.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Integration\Repository
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Integration\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PinkCrab\Comment_Moderation\Domain\Rule\Combinator;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition_Group;
use PinkCrab\Comment_Moderation\Domain\Rule\Conditional_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Ip_Range_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Filter;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Repository;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use PinkCrab\Comment_Moderation\Infrastructure\Persistence\Wpdb_Rule_Repository;
use PinkCrab\Perique\Application\App_Config;
use WP_UnitTestCase;

/**
 * Exercises Wpdb_Rule_Repository against the real `pccm_rules` custom table
 * that the migration creates on plugin activation. Every test rebuilds the
 * table state through public repository methods only, so the test doubles as
 * a contract check on the Rule_Repository port (rebuild spec §6, §7, §8).
 *
 * @group integration
 */
class Test_Wpdb_Rule_Repository extends WP_UnitTestCase {

	/**
	 * Subject under test, rebuilt for every test method.
	 */
	private Wpdb_Rule_Repository $repo;

	/**
	 * Boot a fresh repository and start each test from an empty rules table.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->repo = new Wpdb_Rule_Repository( $wpdb, new App_Config( $this->config_array() ) );
		$this->repo->clear();
	}

	/**
	 * The minimum App_Config payload the repository needs — only the `rules`
	 * table alias is read at runtime. Mirrors `config/settings.php`.
	 *
	 * @return array<string,mixed>
	 */
	private function config_array(): array {
		global $wpdb;
		return array(
			'db_tables' => array(
				'rules' => $wpdb->prefix . 'pccm_rules',
			),
		);
	}

	/**
	 * Convenience — fresh stats anchored to a deterministic instant.
	 */
	private function fresh_stats(): Usage_Stats {
		return Usage_Stats::fresh( new DateTimeImmutable( '2026-05-01 00:00:00', new DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Build a Regex rule with sensible defaults.
	 *
	 * @param array<string,mixed> $overrides Field overrides.
	 */
	private function make_regex_rule( array $overrides = array() ): Regex_Rule {
		$defaults = array(
			'id'            => null,
			'name'          => 'Block casino',
			'description'   => 'Catches casino spam',
			'pattern'       => '/casino/i',
			'comment_parts' => Comment_Parts::of( Comment_Part::content() ),
			'response'      => Response::spam(),
			'usage_stats'   => $this->fresh_stats(),
		);
		$args     = array_replace( $defaults, $overrides );
		return new Regex_Rule(
			$args['id'],
			$args['name'],
			$args['description'],
			$args['pattern'],
			$args['comment_parts'],
			$args['response'],
			$args['usage_stats']
		);
	}

	/**
	 * @testdox It should be possible to insert a regex rule and get back a Rule with a populated id and fresh usage stats
	 */
	public function test_save_inserts_regex_rule_and_assigns_id(): void {
		$saved = $this->repo->save( $this->make_regex_rule() );

		$this->assertNotNull( $saved->id() );
		$this->assertSame( 'Block casino', $saved->name() );
		$this->assertSame( 0, $saved->usage_stats()->times_used() );
		$this->assertNull( $saved->usage_stats()->last_used() );
	}

	/**
	 * @testdox It should be possible to roundtrip a regex rule through save/find without losing any field
	 */
	public function test_find_roundtrips_a_regex_rule(): void {
		$saved = $this->repo->save( $this->make_regex_rule() );

		$found = $this->repo->find( (int) $saved->id() );

		$this->assertInstanceOf( Regex_Rule::class, $found );
		$this->assertSame( $saved->id(), $found->id() );
		$this->assertSame( '/casino/i', $found->pattern() );
		$this->assertSame( array( 'content' ), $found->comment_parts()->values() );
		$this->assertTrue( $found->response()->equals( Response::spam() ) );
	}

	/**
	 * @testdox It should be possible to roundtrip a wildcard rule with multiple comment parts
	 */
	public function test_find_roundtrips_a_wildcard_rule_with_multiple_parts(): void {
		$rule  = new Wildcard_Rule(
			null,
			'Block spam domain',
			null,
			'*@spam-domain.tld',
			Comment_Parts::of( Comment_Part::email(), Comment_Part::name() ),
			Response::spam(),
			$this->fresh_stats()
		);
		$saved = $this->repo->save( $rule );
		$found = $this->repo->find( (int) $saved->id() );

		$this->assertInstanceOf( Wildcard_Rule::class, $found );
		$this->assertSame( '*@spam-domain.tld', $found->pattern() );
		$this->assertSame( array( 'name', 'email' ), $found->comment_parts()->values() );
	}

	/**
	 * @testdox It should be possible to roundtrip an IP range rule keeping both endpoints intact
	 */
	public function test_find_roundtrips_an_ip_range_rule(): void {
		$rule  = new Ip_Range_Rule(
			null,
			'Noisy block',
			null,
			'203.0.113.10',
			'203.0.113.40',
			Response::trash(),
			$this->fresh_stats()
		);
		$saved = $this->repo->save( $rule );
		$found = $this->repo->find( (int) $saved->id() );

		$this->assertInstanceOf( Ip_Range_Rule::class, $found );
		$this->assertSame( '203.0.113.10', $found->start_ip() );
		$this->assertSame( '203.0.113.40', $found->end_ip() );
		$this->assertSame( array( 'ip' ), $found->comment_parts()->values() );
	}

	/**
	 * @testdox It should be possible to roundtrip a nested conditional rule and walk its tree back out
	 */
	public function test_find_roundtrips_a_nested_conditional_rule(): void {
		$root  = Condition_Group::all_of(
			new Condition( Comment_Part::content(), Operator::contains(), 'crypto' ),
			Condition_Group::any_of(
				new Condition( Comment_Part::email(), Operator::wildcard(), '*@gmail.com' ),
				new Condition( Comment_Part::name(), Operator::is(), 'bob' ),
			),
		);
		$rule  = new Conditional_Rule( null, 'Crypto trap', null, $root, Response::pending(), $this->fresh_stats() );
		$saved = $this->repo->save( $rule );
		$found = $this->repo->find( (int) $saved->id() );

		$this->assertInstanceOf( Conditional_Rule::class, $found );
		$this->assertTrue( $found->root()->combinator()->equals( Combinator::all() ) );
		$leaves = $found->root()->all_conditions();
		$this->assertCount( 3, $leaves );
		$this->assertSame( 'crypto', $leaves[0]->value() );
		$this->assertSame( array( 'name', 'email', 'content' ), $found->comment_parts()->values() );
	}

	/**
	 * @testdox It should be possible to update a rule's editable fields and find times_used / last_used preserved across the save
	 */
	public function test_update_preserves_usage_stats_and_refreshes_last_updated(): void {
		global $wpdb;
		$saved = $this->repo->save( $this->make_regex_rule() );

		// Simulate the engine recording two hits on this rule by writing
		// directly to the table — we want to prove the repo update does not
		// trample those counters.
		$wpdb->update(
			$wpdb->prefix . 'pccm_rules',
			array(
				'times_used' => 14,
				'last_used'  => '2026-04-01 12:00:00',
			),
			array( 'id' => $saved->id() ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$existing = $this->repo->find( (int) $saved->id() );
		$this->assertInstanceOf( Regex_Rule::class, $existing );
		$this->assertSame( 14, $existing->usage_stats()->times_used() );

		$edited = $this->make_regex_rule(
			array(
				'id'      => $existing->id(),
				'name'    => 'Block casino (renamed)',
				'pattern' => '/(casino|gambling)/i',
			)
		);

		$updated = $this->repo->save( $edited );

		$this->assertSame( $existing->id(), $updated->id() );
		$this->assertSame( 'Block casino (renamed)', $updated->name() );
		$this->assertSame( 14, $updated->usage_stats()->times_used() );
		$this->assertNotNull( $updated->usage_stats()->last_used() );
		$this->assertGreaterThanOrEqual(
			$existing->usage_stats()->last_updated()->getTimestamp(),
			$updated->usage_stats()->last_updated()->getTimestamp(),
			'last_updated should advance on every update.'
		);

		$reloaded = $this->repo->find( (int) $saved->id() );
		$this->assertInstanceOf( Regex_Rule::class, $reloaded );
		$this->assertSame( '/(casino|gambling)/i', $reloaded->pattern() );
		$this->assertSame( 14, $reloaded->usage_stats()->times_used() );
	}

	/**
	 * @testdox It should be possible to delete a rule by id and confirm it is gone from a subsequent find
	 */
	public function test_delete_removes_a_rule(): void {
		$saved = $this->repo->save( $this->make_regex_rule() );

		$this->assertTrue( $this->repo->delete( (int) $saved->id() ) );
		$this->assertNull( $this->repo->find( (int) $saved->id() ) );
		$this->assertFalse( $this->repo->delete( (int) $saved->id() ), 'Re-deleting a missing rule should report false.' );
	}

	/**
	 * @testdox It should be possible to clear every rule at once and find the table empty afterwards
	 */
	public function test_clear_removes_every_rule_and_reports_the_count(): void {
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'A' ) ) );
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'B' ) ) );
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'C' ) ) );

		$removed = $this->repo->clear();

		$this->assertSame( 3, $removed );
		$this->assertSame( 0, $this->repo->count( Rule_Filter::none() ) );
	}

	/**
	 * @testdox It should be possible to list rules unfiltered and get them back in ascending id order
	 */
	public function test_find_page_returns_unfiltered_rules_in_insertion_order(): void {
		$first  = $this->repo->save( $this->make_regex_rule( array( 'name' => 'A' ) ) );
		$second = $this->repo->save( $this->make_regex_rule( array( 'name' => 'B' ) ) );
		$third  = $this->repo->save( $this->make_regex_rule( array( 'name' => 'C' ) ) );

		$page = $this->repo->find_page( Rule_Filter::none(), 1 );

		$ids = array_map( static fn( $rule ) => $rule->id(), $page );
		$this->assertSame(
			array( $first->id(), $second->id(), $third->id() ),
			$ids,
			'Rules should be returned in insertion order (id ASC) — engine first-match-wins relies on this.'
		);
	}

	/**
	 * @testdox It should be possible to filter rules by search term so only rules whose name or pattern match show up
	 */
	public function test_find_page_filters_by_search_term(): void {
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Block casino', 'pattern' => '/casino/i' ) ) );
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Block gambling', 'pattern' => '/gambling/i' ) ) );

		$filter = new Rule_Filter( 'gambl' );

		$results = $this->repo->find_page( $filter, 1 );
		$this->assertCount( 1, $results );
		$this->assertSame( 'Block gambling', $results[0]->name() );

		$this->assertSame( 1, $this->repo->count( $filter ) );
	}

	/**
	 * @testdox It should be possible to filter rules by type so only those of the requested types are returned
	 */
	public function test_find_page_filters_by_type(): void {
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Regex one' ) ) );
		$this->repo->save(
			new Wildcard_Rule(
				null,
				'Wildcard one',
				null,
				'*@bad.tld',
				Comment_Parts::of( Comment_Part::email() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);

		$filter  = new Rule_Filter( '', array( Rule_Type::wildcard() ) );
		$results = $this->repo->find_page( $filter, 1 );

		$this->assertCount( 1, $results );
		$this->assertInstanceOf( Wildcard_Rule::class, $results[0] );
	}

	/**
	 * @testdox It should be possible to filter rules by which comment part they inspect and not match rules that share a substring of the part name
	 */
	public function test_find_page_filters_by_comment_part_without_substring_collision(): void {
		// 'name' must not match 'user_name'-style tokens — the comma-wrapped storage prevents this.
		$this->repo->save(
			$this->make_regex_rule(
				array(
					'name'          => 'Name scan',
					'comment_parts' => Comment_Parts::of( Comment_Part::name() ),
				)
			)
		);
		$this->repo->save(
			$this->make_regex_rule(
				array(
					'name'          => 'User-agent only',
					'comment_parts' => Comment_Parts::of( Comment_Part::user_agent() ),
				)
			)
		);

		$filter  = new Rule_Filter( '', array(), Comment_Parts::of( Comment_Part::name() ) );
		$results = $this->repo->find_page( $filter, 1 );

		$this->assertCount( 1, $results );
		$this->assertSame( 'Name scan', $results[0]->name() );
	}

	/**
	 * @testdox It should be possible to filter rules by response so only the requested outcomes come back
	 */
	public function test_find_page_filters_by_response(): void {
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Spam one', 'response' => Response::spam() ) ) );
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Trash one', 'response' => Response::trash() ) ) );
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Pending one', 'response' => Response::pending() ) ) );

		$filter  = new Rule_Filter( '', array(), null, array( Response::trash(), Response::pending() ) );
		$results = $this->repo->find_page( $filter, 1 );

		$this->assertCount( 2, $results );
		$names = array_map( static fn( $rule ) => $rule->name(), $results );
		$this->assertContains( 'Trash one', $names );
		$this->assertContains( 'Pending one', $names );
	}

	/**
	 * @testdox It should be possible to paginate through the rules in 25-per-page chunks
	 */
	public function test_find_page_paginates_in_chunks_of_twenty_five(): void {
		for ( $index = 1; $index <= 30; $index++ ) {
			$this->repo->save( $this->make_regex_rule( array( 'name' => sprintf( 'Rule %02d', $index ) ) ) );
		}

		$page_one = $this->repo->find_page( Rule_Filter::none(), 1 );
		$page_two = $this->repo->find_page( Rule_Filter::none(), 2 );

		$this->assertCount( Rule_Repository::PAGE_SIZE, $page_one );
		$this->assertCount( 5, $page_two );
		$this->assertSame( 30, $this->repo->count( Rule_Filter::none() ) );
	}
}
