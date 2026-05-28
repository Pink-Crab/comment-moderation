<?php
/**
 * Usage_Stats value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use DateTimeImmutable;
use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use WP_UnitTestCase;

/**
 * Proves Usage_Stats records hits, preserves stats across an edit, and
 * rejects a negative hit count.
 *
 * @group unit
 */
class Test_Usage_Stats extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to build fresh stats for a brand-new rule with zero hits and a null last-used timestamp
	 */
	public function test_fresh_starts_at_zero_with_null_last_used(): void {
		$now   = new DateTimeImmutable( '2026-05-01 09:10:00' );
		$stats = Usage_Stats::fresh( $now );

		$this->assertSame( 0, $stats->times_used() );
		$this->assertNull( $stats->last_used() );
		$this->assertSame( $now, $stats->last_updated() );
	}

	/**
	 * @testdox It should be possible to construct stats directly with an explicit hit count and timestamps
	 */
	public function test_constructor_accepts_explicit_values(): void {
		$last_used    = new DateTimeImmutable( '2026-05-03 14:02:00' );
		$last_updated = new DateTimeImmutable( '2026-05-01 09:10:00' );

		$stats = new Usage_Stats( 14, $last_used, $last_updated );

		$this->assertSame( 14, $stats->times_used() );
		$this->assertSame( $last_used, $stats->last_used() );
		$this->assertSame( $last_updated, $stats->last_updated() );
	}

	/**
	 * @testdox It should be possible to reject a negative hit count with a clear error
	 */
	public function test_constructor_rejects_negative_count(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Usage_Stats: times_used must be >= 0, got -1.' );

		new Usage_Stats( -1, null, new DateTimeImmutable() );
	}

	/**
	 * @testdox It should be possible to record a hit and get a new stats object with the count incremented and last-used set without mutating the original
	 */
	public function test_record_hit_returns_new_stats(): void {
		$start   = new DateTimeImmutable( '2026-05-01 09:10:00' );
		$at      = new DateTimeImmutable( '2026-05-03 14:02:00' );
		$initial = Usage_Stats::fresh( $start );

		$next = $initial->record_hit( $at );

		$this->assertNotSame( $initial, $next );
		$this->assertSame( 0, $initial->times_used() );
		$this->assertNull( $initial->last_used() );
		$this->assertSame( 1, $next->times_used() );
		$this->assertSame( $at, $next->last_used() );
		// Last updated does NOT move when a hit is recorded.
		$this->assertSame( $start, $next->last_updated() );
	}

	/**
	 * @testdox It should be possible to chain multiple hits and have the count grow each time
	 */
	public function test_record_hit_chains(): void {
		$start = new DateTimeImmutable( '2026-05-01 09:10:00' );
		$first = new DateTimeImmutable( '2026-05-03 14:02:00' );
		$last  = new DateTimeImmutable( '2026-05-05 18:00:00' );

		$stats = Usage_Stats::fresh( $start )
			->record_hit( $first )
			->record_hit( $last );

		$this->assertSame( 2, $stats->times_used() );
		$this->assertSame( $last, $stats->last_used() );
	}

	/**
	 * @testdox It should be possible to refresh the last-updated timestamp on an edit while preserving the accumulated hit history
	 */
	public function test_touch_preserves_hit_history(): void {
		$start = new DateTimeImmutable( '2026-05-01 09:10:00' );
		$hit   = new DateTimeImmutable( '2026-05-03 14:02:00' );
		$edit  = new DateTimeImmutable( '2026-05-10 11:00:00' );

		$stats = Usage_Stats::fresh( $start )->record_hit( $hit );
		$after = $stats->touch( $edit );

		$this->assertSame( 1, $after->times_used() );
		$this->assertSame( $hit, $after->last_used() );
		$this->assertSame( $edit, $after->last_updated() );
	}
}
