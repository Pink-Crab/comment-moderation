<?php
/**
 * Comment_Submission — the fact-bundle a rule evaluator is handed for a
 * single submitted comment.
 *
 * Carries the six commenter-facing fields the rule engine inspects (rebuild
 * spec §9): the commenter's name, email, website URL, IP address, browser
 * user-agent, and the comment body text. The submission is immutable; the
 * engine never mutates it, and integrations that wish to enrich the body
 * (e.g. folding a custom "subject" field into the scanned content per spec
 * §11) do so by handing the engine a new Comment_Submission rather than
 * editing one in place.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Engine
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Engine;

use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;

/**
 * Immutable value object carrying the comment data the engine evaluates.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.LongVariable")
 */
final class Comment_Submission {

	/**
	 * Construct from the six commenter-facing fields. Missing fields are
	 * permitted as empty strings — the engine treats them as "nothing to
	 * match" rather than failing, so rules that inspect (say) the URL still
	 * evaluate cleanly when the commenter left it blank.
	 *
	 * @param string $name       Commenter display name; may be empty.
	 * @param string $email      Commenter email address; may be empty.
	 * @param string $url        Commenter website URL; may be empty.
	 * @param string $ip         Commenter IP address; may be empty.
	 * @param string $user_agent Commenter browser user-agent string; may be empty.
	 * @param string $content    Comment body text; may be empty.
	 */
	public function __construct(
		private string $name = '',
		private string $email = '',
		private string $url = '',
		private string $ip = '',
		private string $user_agent = '',
		private string $content = ''
	) {}

	/**
	 * Return the string value of a single comment part. Drives the rule
	 * evaluators: rather than each evaluator switching on the part enum, they
	 * ask the submission for its string slice and apply their operator to it.
	 *
	 * @param Comment_Part $part Slice of the comment to read.
	 *
	 * @return string
	 */
	public function part( Comment_Part $part ): string {
		return match ( $part->value() ) {
			Comment_Part::NAME       => $this->name,
			Comment_Part::EMAIL      => $this->email,
			Comment_Part::URL        => $this->url,
			Comment_Part::IP         => $this->ip,
			Comment_Part::USER_AGENT => $this->user_agent,
			Comment_Part::CONTENT    => $this->content,
			default                  => '',
		};
	}

	/**
	 * Commenter display name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Commenter email address.
	 *
	 * @return string
	 */
	public function email(): string {
		return $this->email;
	}

	/**
	 * Commenter website URL.
	 *
	 * @return string
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Commenter IP address. Used by the IP-range evaluator and by any
	 * IP-scoped Regex / Wildcard / Conditional rule.
	 *
	 * @return string
	 */
	public function ip(): string {
		return $this->ip;
	}

	/**
	 * Commenter browser user-agent string.
	 *
	 * @return string
	 */
	public function user_agent(): string {
		return $this->user_agent;
	}

	/**
	 * Comment body text.
	 *
	 * @return string
	 */
	public function content(): string {
		return $this->content;
	}
}
