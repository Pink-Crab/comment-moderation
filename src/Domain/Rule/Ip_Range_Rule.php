<?php
/**
 * Ip_Range_Rule — concrete rule whose pattern is an inclusive IPv4 range.
 *
 * Matches the admin form for the "IP Range" type in rebuild spec §4.3: a
 * start and end IPv4 address that share their first three octets (so the
 * range only varies on the final octet). Always inspects the commenter's IP
 * only — the six comment-part tick-boxes are not shown for this type.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object representing one stored IP Range rule.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class Ip_Range_Rule implements Rule {

	/**
	 * Construct with the rule's identity, IP range, response, and stats.
	 *
	 * @param integer|null $id          DB primary key, null for unsaved rules.
	 * @param string|null  $name        Optional short label.
	 * @param string|null  $description Optional longer description.
	 * @param string       $start_ip    Valid IPv4 lower bound (inclusive).
	 * @param string       $end_ip      Valid IPv4 upper bound (inclusive); must share the first three octets with $start_ip and be >= $start_ip.
	 * @param Response     $response    Outcome to apply on match.
	 * @param Usage_Stats  $usage_stats Auto-tracked stats.
	 *
	 * @throws InvalidArgumentException If the IPs are invalid, do not share their first three octets, or the range is reversed.
	 */
	public function __construct(
		private ?int $id,
		private ?string $name,
		private ?string $description,
		private string $start_ip,
		private string $end_ip,
		private Response $response,
		private Usage_Stats $usage_stats
	) {
		if ( false === filter_var( $start_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Ip_Range_Rule: start IP "%s" is not a valid IPv4 address.', $start_ip ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}
		if ( false === filter_var( $end_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Ip_Range_Rule: end IP "%s" is not a valid IPv4 address.', $end_ip ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		// Rebuild spec §4.3: "must share their first three blocks".
		$start_parts = explode( '.', $start_ip );
		$end_parts   = explode( '.', $end_ip );
		if ( array_slice( $start_parts, 0, 3 ) !== array_slice( $end_parts, 0, 3 ) ) {
			throw new InvalidArgumentException(
				'Ip_Range_Rule: the network of the IP address (first 3 parts) must match.'
			);
		}

		// ip2long is safe here because both addresses already passed FILTER_VALIDATE_IP.
		if ( ip2long( $start_ip ) > ip2long( $end_ip ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Ip_Range_Rule: start IP "%s" must be <= end IP "%s".', $start_ip, $end_ip ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}
	}

	/**
	 * DB primary key, or null for an unsaved rule.
	 *
	 * @return integer|null
	 */
	public function id(): ?int {
		return $this->id;
	}

	/**
	 * Optional short label.
	 *
	 * @return string|null
	 */
	public function name(): ?string {
		return $this->name;
	}

	/**
	 * Optional longer description.
	 *
	 * @return string|null
	 */
	public function description(): ?string {
		return $this->description;
	}

	/**
	 * Always the IP Range rule type.
	 *
	 * @return Rule_Type
	 */
	public function type(): Rule_Type {
		return Rule_Type::ip_range();
	}

	/**
	 * Inclusive lower bound of the address range.
	 *
	 * @return string
	 */
	public function start_ip(): string {
		return $this->start_ip;
	}

	/**
	 * Inclusive upper bound of the address range.
	 *
	 * @return string
	 */
	public function end_ip(): string {
		return $this->end_ip;
	}

	/**
	 * Always exactly { Comment_Part::ip() } — IP-range rules do not present
	 * the six comment-part tick-boxes on the admin screen.
	 *
	 * @return Comment_Parts
	 */
	public function comment_parts(): Comment_Parts {
		return Comment_Parts::of( Comment_Part::ip() );
	}

	/**
	 * Outcome applied when the rule fires.
	 *
	 * @return Response
	 */
	public function response(): Response {
		return $this->response;
	}

	/**
	 * Auto-tracked usage record.
	 *
	 * @return Usage_Stats
	 */
	public function usage_stats(): Usage_Stats {
		return $this->usage_stats;
	}
}
