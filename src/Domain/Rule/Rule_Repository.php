<?php
/**
 * Rule_Repository — domain port through which the engine and the admin
 * screen persist, retrieve, and list moderation rules.
 *
 * Defines the persistence contract without binding it to any storage
 * mechanism. Stage 11 ships a `$wpdb`-backed implementation in the
 * Infrastructure layer; a future in-memory test double or alternative store
 * can plug into the same interface.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use DateTimeImmutable;

/**
 * CRUD + filtered-listing contract for moderation rules.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
interface Rule_Repository {

	/**
	 * Fixed page size — rebuild spec §8 ("a fixed number of rules at a time
	 * (25)"). Exposed so callers (filter UI, pagination buttons) can refer to
	 * the same constant instead of hard-coding 25.
	 *
	 * @var integer
	 */
	public const PAGE_SIZE = 25;

	/**
	 * Persist a rule. A rule with a null `id()` is inserted; a rule with an
	 * id is updated. The returned Rule is the canonical post-save value:
	 * either the freshly-saved rule with its new id, or the updated rule
	 * with its `usage_stats()` reconciled against the row already in the
	 * database (rebuild spec §6 — editing preserves the times-used / last-
	 * used history; only `last_updated` moves forward).
	 *
	 * @param Rule $rule Rule to save.
	 *
	 * @return Rule Saved rule with id and reconciled stats.
	 */
	public function save( Rule $rule ): Rule;

	/**
	 * Find a rule by primary key, or null if no such row exists.
	 *
	 * @param integer $id Primary key.
	 *
	 * @return Rule|null
	 */
	public function find( int $id ): ?Rule;

	/**
	 * Delete a rule by primary key. Returns true if a row was removed.
	 *
	 * @param integer $id Primary key.
	 *
	 * @return boolean
	 */
	public function delete( int $id ): bool;

	/**
	 * Remove every rule — rebuild spec §6 "Clear All Rules". Returns the
	 * number of rows removed so the caller can report it back to the admin.
	 *
	 * @return integer Number of rows removed.
	 */
	public function clear(): int;

	/**
	 * Return a single 25-rule page of rules matching $filter, ordered by id
	 * ascending — the engine's first-match-wins iteration order (rebuild spec
	 * §9). Page numbers are 1-indexed.
	 *
	 * @param Rule_Filter $filter Filter criteria; pass Rule_Filter::none() for an unfiltered list.
	 * @param integer     $page   One-indexed page number; clamped to >= 1 by the implementation.
	 *
	 * @return array<int,Rule>
	 */
	public function find_page( Rule_Filter $filter, int $page = 1 ): array;

	/**
	 * Total count of rules matching $filter — used to drive the "Showing X of
	 * Y" counter (rebuild spec §3.4) and to decide whether to render the
	 * "Show More Rules" button (§8).
	 *
	 * @param Rule_Filter $filter Filter criteria.
	 *
	 * @return integer
	 */
	public function count( Rule_Filter $filter ): int;

	/**
	 * Atomically bump the matched rule's hit count and stamp `last_used`
	 * (rebuild spec §9: "that rule's 'times used' count is incremented and its
	 * 'last used' time stamped"). Implemented as a single conditional UPDATE
	 * so two concurrent comment submissions cannot lose a count between a
	 * read-modify-write. `last_updated` is left untouched — only an admin edit
	 * moves that forward (§6).
	 *
	 * @param integer           $id   Primary key of the rule that fired.
	 * @param DateTimeImmutable $when Wall-clock instant of the hit.
	 *
	 * @return boolean True when a row was updated.
	 */
	public function record_hit( int $id, DateTimeImmutable $when ): bool;
}
