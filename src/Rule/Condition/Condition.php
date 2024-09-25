<?php

/**
 * Rule Condition
 *
 * @package PinkCrab\Comment_Moderation\Rule
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Rule\Condition;

/**
 * Rule Condition
 */
class Condition implements \JsonSerializable {

	public const TYPE_CONTAINS    = 'contains';
	public const TYPE_NOT_CONTAIN = 'not_contain';
	public const TYPE_EQUALS      = 'equals';
	public const TYPE_NOT_EQUALS  = 'not_equals';
	public const TYPE_STARTS_WITH = 'starts_with';
	public const TYPE_ENDS_WITH   = 'ends_with';
	public const TYPE_REGEX       = 'regex';
	public const TYPE_WILDCARD    = 'wildcard';

	public const ALLOWED_TYPES = array(
		self::TYPE_CONTAINS,
		self::TYPE_NOT_CONTAIN,
		self::TYPE_EQUALS,
		self::TYPE_NOT_EQUALS,
		self::TYPE_STARTS_WITH,
		self::TYPE_ENDS_WITH,
		self::TYPE_REGEX,
		self::TYPE_WILDCARD,
	);

	/**
	 * Applies to comment content.
	 *
	 * @var bool
	 */
	protected $comment_content = false;

	/**
	 * Applies to comment author.
	 *
	 * @var bool
	 */
	protected $comment_author = false;

	/**
	 * Applies to comment author email.
	 *
	 * @var bool
	 */
	protected $comment_author_email = false;

	/**
	 * Applies to comment author url.
	 *
	 * @var bool
	 */
	protected $comment_author_url = false;

	/**
	 * Applies to comment author IP.
	 *
	 * @var bool
	 */
	protected $comment_author_ip = false;

	/**
	 * Applies to comment agent.
	 *
	 * @var bool
	 */
	protected $comment_agent = false;

	/**
	 * The type of the rule.
	 *
	 * @var string
	 */
	protected $rule_type;

	/**
	 * The value of the rule.
	 *
	 * @var string
	 */
	protected $rule_value = '';

	/**
	 * Creates an instance of the Rule Condition.
	 *
	 * @param string                         $rule_type The type of the rule.
	 * @param callable(Condition): void|null $callback  The callback to set additional values.
	 */
	public function __construct( string $rule_type, ?callable $callback ) {
		$this->rule_type = $rule_type;
		if ( null !== $callback ) {
			$callback( $this );
		}
	}

	/**
	 * Sets the rule value.
	 *
	 * @param string $rule_value The value of the rule.
	 *
	 * @return self
	 */
	public function set_rule_value( string $rule_value ): self {
		$this->rule_value = $rule_value;
		return $this;
	}

	/**
	 * Sets if applies to comment content.
	 *
	 * @param boolean $comment_content Applies to comment content.
	 *
	 * @return self
	 */
	public function set_comment_content( bool $comment_content = true ): self {
		$this->comment_content = $comment_content;
		return $this;
	}

	/**
	 * Sets if applies to comment author.
	 *
	 * @param boolean $comment_author Applies to comment author.
	 *
	 * @return self
	 */
	public function set_comment_author( bool $comment_author = true ): self {
		$this->comment_author = $comment_author;
		return $this;
	}

	/**
	 * Sets if applies to comment author email.
	 *
	 * @param boolean $comment_author_email Applies to comment author email.
	 *
	 * @return self
	 */
	public function set_comment_author_email( bool $comment_author_email = true ): self {
		$this->comment_author_email = $comment_author_email;
		return $this;
	}

	/**
	 * Sets if applies to comment author url.
	 *
	 * @param boolean $comment_author_url Applies to comment author url.
	 *
	 * @return self
	 */
	public function set_comment_author_url( bool $comment_author_url = true ): self {
		$this->comment_author_url = $comment_author_url;
		return $this;
	}

	/**
	 * Sets if applies to comment author IP.
	 *
	 * @param boolean $comment_author_ip Applies to comment author IP.
	 *
	 * @return self
	 */
	public function set_comment_author_ip( bool $comment_author_ip = true ): self {
		$this->comment_author_ip = $comment_author_ip;
		return $this;
	}

	/**
	 * Sets if applies to comment agent.
	 *
	 * @param boolean $comment_agent Applies to comment agent.
	 *
	 * @return self
	 */
	public function set_comment_agent( bool $comment_agent = true ): self {
		$this->comment_agent = $comment_agent;
		return $this;
	}

	/**
	 * Get the rule type.
	 *
	 * @return string
	 */
	public function get_rule_type(): string {
		return $this->rule_type;
	}

	/**
	 * Get the rule value.
	 *
	 * @return string
	 */
	public function get_rule_value(): string {
		return $this->rule_value;
	}

	/**
	 * Get if applies to comment content.
	 *
	 * @return boolean
	 */
	public function is_comment_content(): bool {
		return $this->comment_content;
	}

	/**
	 * Get if applies to comment author.
	 *
	 * @return boolean
	 */
	public function is_comment_author(): bool {
		return $this->comment_author;
	}

	/**
	 * Get if applies to comment author email.
	 *
	 * @return boolean
	 */
	public function is_comment_author_email(): bool {
		return $this->comment_author_email;
	}

	/**
	 * Get if applies to comment author url.
	 *
	 * @return boolean
	 */
	public function is_comment_author_url(): bool {
		return $this->comment_author_url;
	}

	/**
	 * Get if applies to comment author IP.
	 *
	 * @return boolean
	 */
	public function is_comment_author_ip(): bool {
		return $this->comment_author_ip;
	}

	/**
	 * Get if applies to comment agent.
	 *
	 * @return boolean
	 */
	public function is_comment_agent(): bool {
		return $this->comment_agent;
	}

	/**
	 * Get the fields the rule applies to.
	 *
	 * @return array<string, bool>
	 */
	public function get_fields(): array {
		return array_filter(
			array(
				'comment_content'      => $this->comment_content,
				'comment_author'       => $this->comment_author,
				'comment_author_email' => $this->comment_author_email,
				'comment_author_url'   => $this->comment_author_url,
				'comment_author_ip'    => $this->comment_author_ip,
				'comment_agent'        => $this->comment_agent,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return array(
			'type'                 => 'condition',
			'rule_type'            => $this->rule_type,
			'rule_value'           => $this->rule_value,
			'comment_content'      => $this->comment_content,
			'comment_author'       => $this->comment_author,
			'comment_author_email' => $this->comment_author_email,
			'comment_author_url'   => $this->comment_author_url,
			'comment_author_ip'    => $this->comment_author_ip,
			'comment_agent'        => $this->comment_agent,
		);
	}

	/**
	 * Create from JSON
	 *
	 * @param string $json JSON string.
	 *
	 * @return self
	 *
	 * @throws \JsonException If invalid JSON.
	 */
	public static function from_json( string $json ): self {
		$data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

		try {
			$condition = self::from_json( $data );
		} catch ( \Throwable $th ) {
			throw new \JsonException( esc_html( $th->getMessage() ) );
		}

		return $condition;
	}

	/**
	 * Creates a new instance of the Con\dition from an array.
	 *
	 * @param array<string, mixed> $data The data to create the condition from.
	 *
	 * @return self
	 *
	 * @throws \InvalidArgumentException If invalid data.
	 */
	public static function from_array( array $data ): self {

		// If doesnt contain rule_type, throw exception.
		if ( ! array_key_exists( 'rule_type', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing rule_type' );
		}
		$condition = new self( esc_html( $data['rule_type'] ), null );

		// If doesnt contain rule_value, throw exception.
		if ( ! array_key_exists( 'rule_value', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing rule_value' );
		}
		$condition->set_rule_value( $data['rule_value'] );

		// If doesnt contain comment_content, throw exception.
		if ( ! array_key_exists( 'comment_content', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing comment_content' );
		}
		$condition->set_comment_content( (bool) $data['comment_content'] );

		// If doesnt contain comment_author, throw exception.
		if ( ! array_key_exists( 'comment_author', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing comment_author' );
		}
		$condition->set_comment_author( (bool) $data['comment_author'] );

		// If doesnt contain comment_author_email, throw exception.
		if ( ! array_key_exists( 'comment_author_email', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing comment_author_email' );
		}
		$condition->set_comment_author_email( (bool) $data['comment_author_email'] );

		// If doesnt contain comment_author_url, throw exception.
		if ( ! array_key_exists( 'comment_author_url', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing comment_author_url' );
		}
		$condition->set_comment_author_url( (bool) $data['comment_author_url'] );

		// If doesnt contain comment_author_ip, throw exception.
		if ( ! array_key_exists( 'comment_author_ip', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing comment_author_ip' );
		}
		$condition->set_comment_author_ip( (bool) $data['comment_author_ip'] );

		// If doesnt contain comment_agent, throw exception.
		if ( ! array_key_exists( 'comment_agent', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid data, missing comment_agent' );
		}
		$condition->set_comment_agent( (bool) $data['comment_agent'] );

		return $condition;
	}
}
