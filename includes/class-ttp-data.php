<?php
namespace TreasuryTechPortal;

/**
 * Data handler for Treasury Tech Portal.
 *
 * @package Treasury_Tech_Portal
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Utility class for managing tool data.
 */
class TTP_Data {
	const OPTION_KEY  = 'ttp_tools';
	const CACHE_GROUP = 'treasury_tech_portal';

	/**
	 * Retrieve all tools with caching.
	 *
	 * @return array
	 */
	public static function get_all_tools() {
		$cache_key = 'all_tools';
		$tools     = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $tools ) {
			$tools = get_option( self::OPTION_KEY );
			if ( empty( $tools ) ) {
				$tools = self::load_default_tools();
			}

			wp_cache_set( $cache_key, $tools, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		return $tools;
	}

        /**
         * Save the given tools and clear cache.
         *
         * @param array $tools Tools to store.
         */
        public static function save_tools( $tools ) {
                update_option( self::OPTION_KEY, $tools );
                wp_cache_delete( 'all_tools', self::CACHE_GROUP );
        }

	/**
	 * Load default tools from bundled JSON file.
	 *
	 * @return array
	 */
	private static function load_default_tools() {
		$file = dirname( __DIR__ ) . '/data/tools.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}
                $json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}

        /**
         * Filter and search tools server-side.
         *
         * @param array $args Filter arguments.
         * @return array
         */
        public static function get_tools( $args = array() ) {
		$tools = self::get_all_tools();

                if ( ! empty( $args['category'] ) && 'ALL' !== $args['category'] ) {
			$tools = array_filter(
				$tools,
				function ( $tool ) use ( $args ) {
					return isset( $tool['category'] ) && $tool['category'] === $args['category'];
				}
			);
		}

		if ( ! empty( $args['search'] ) ) {
			$search = strtolower( $args['search'] );
			$tools  = array_filter(
				$tools,
				function ( $tool ) use ( $search ) {
					$haystack = strtolower( $tool['name'] . ' ' . ( $tool['desc'] ?? '' ) . ' ' . implode( ' ', $tool['features'] ?? array() ) );
					return strpos( $haystack, $search ) !== false;
				}
			);
		}

                if ( ! empty( $args['has_video'] ) ) {
			$tools = array_filter(
				$tools,
				function ( $tool ) {
					return ! empty( $tool['videoUrl'] );
				}
			);
		}

		$page     = max( 1, intval( $args['page'] ?? 1 ) );
		$per_page = max( 1, intval( $args['per_page'] ?? count( $tools ) ) );
		$offset   = ( $page - 1 ) * $per_page;

                $tools = array_slice( array_values( $tools ), $offset, $per_page );

                return $tools;
        }
}
