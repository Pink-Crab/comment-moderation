<?php

/**
 * Tool to help with the logging of mock data from the wpdb class.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Tests\Tools;

/**
 * Tool to help with the logging of mock data from the wpdb class.
 */
trait wpdb_logger {

	/**
	 * The Log of all queries.
	 *
	 * @var array<string>
	 */
	protected static $query_log = array();

	/**
	 * The Log of all prepared queries.
	 *
	 * @var array<string>
	 */
	protected static $prepared_query_log = array();

    /**
     * Log of all insert/update queries.
     * 
     * @var array{
     *  insert:bool, 
     *  table:string, 
     *  rows:array<string, mixed>, 
     *  format:array<string>,
     *  where:array<string, mixed>,
     *  where_format:array<string>
     * }[]
     */
    protected static $insert_update_log = array();

	/**
	 * The result to return from the query.
	 *
	 * @var mixed
	 */
	protected static $result;

    /**
     * Clears all logs.
     * 
     * @return void
     */
    public static function clear_logs(): void {
        self::clear_query_log();
        self::clear_prepared_query_log();
        self::clear_result();
        self::clear_insert_update_log();
    }

	/**
	 * Get the query log.
	 *
	 * @return array<string>
	 */
	public static function get_query_log(): array {
		return self::$query_log;
	}

	/**
	 * Get the prepared query log.
	 *
	 * @return array<string>
	 */
	public static function get_prepared_query_log(): array {
		return self::$prepared_query_log;
	}

    /**
     * Get the insert/update log.
     * 
     * @return object{
     *  insert:bool, 
     *  table:string, 
     *  rows:array<string, mixed>, 
     *  format:array<string>,
     *  where:array<string, mixed>,
     *  where_format:array<string>
     * }[]
     */
    public static function get_insert_update_log(): array {
        return self::$insert_update_log;
    }

	/**
	 * Clear the query log.
	 *
	 * @return void
	 */
	public static function clear_query_log(): void {
		self::$query_log = array();
	}

	/**
	 * Clear the prepared query log.
	 *
	 * @return void
	 */
	public static function clear_prepared_query_log(): void {
		self::$prepared_query_log = array();
	}

    /**
     * Clear the insert/update log.
     * 
     * @return void
     */
    public static function clear_insert_update_log(): void {
        self::$insert_update_log = array();
    }


	/**
	 * Log a query.
	 *
	 * @param string $query
	 * @return void
	 */
	public static function log_query( string $query ): void {
		self::$query_log[] = $query;
	}

	/**
	 * Log a prepared query.
	 *
	 * @param string $query
	 * @return void
	 */
	public static function log_prepared_query( string $query, array $args = array() ): void {
		self::$prepared_query_log[] = (object) array(
			'query' => $query,
			'args'  => $args,
		);
	}

    /**
     * Log update|insert query.
     * 
     * @param bool $insert
     * @param string $table
     * @param array<string, mixed> $rows
     * @param array<string> $format
     * @param array<string, mixed> $where
     * @param array<string> $where_format
     * 
     * @return void
     */
    public static function log_insert_update_query( bool $insert, string $table, array $rows, array $format, array $where, array $where_format ): void {
        self::$insert_update_log[] = (object) array(
            'insert' => $insert,
            'table' => $table,
            'rows' => $rows,
            'format' => $format,
            'where' => $where,
            'where_format' => $where_format,
        );
    }

	/**
	 * Set the result to return from the query.
	 *
	 * @param mixed $result
	 */
	public static function set_result( $result ): void {
		self::$result = $result;
	}

	/**
	 * Clear the result.
	 *
	 * @return void
	 */
	public static function clear_result(): void {
		self::$result = null;
	}

	/**
	 * Get the result to return from the query.
	 *
	 * @return mixed
	 */
	public static function get_result() {
		return self::$result;
	}

	/**
	 * Get WPDB instance with logging.
	 *
	 * @return \wpdb
	 */
	public function get_wpdb_with_logger(): \wpdb {
		// Logs a query callback.
		$log_query_cb = function ( $query ) {
			$this->log_query( $query );
			return self::get_result();
		};

		$wpdb = $this->getMockBuilder( \wpdb::class )
			->disableOriginalConstructor()
			->getMock();

		$wpdb->method( 'query' )->willReturnCallback( $log_query_cb );
		$wpdb->method( 'get_row' )->willReturnCallback( $log_query_cb );
		$wpdb->method( 'get_results' )->willReturnCallback( $log_query_cb );
		$wpdb->method( 'get_var' )->willReturnCallback( $log_query_cb );

		$wpdb->method( 'prepare' )
			->willReturnCallback(
				function ( $query, $args ) use ( $wpdb ) {
					$this->log_prepared_query( $query, is_array( $args ) ? $args : array( $args ) );
					return $GLOBALS['wpdb']->prepare( $query, $args );
				}
			);

        $wpdb->method( 'update' )
            ->willReturnCallback(
                function ( $table, $rows, $where, $format, $where_format ) use ( $wpdb ) {
					$this->log_insert_update_query( false, $table, $rows, $format, $where, $where_format );
                    return self::get_result();
                }
            );

        $wpdb->method( 'insert' )
            ->willReturnCallback(
                function ( $table, $rows, $format ) use ( $wpdb ) {
                    $wpdb->insert_id = self::get_result();
                    $this->log_insert_update_query( true, $table, $rows, $format, array(), array() );
                    return self::get_result();
                }
            );

		return $wpdb;
	}
}
