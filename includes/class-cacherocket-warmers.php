<?php
/**
 * Cache warmer form helpers for the WordPress admin.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Build / normalize warmer payloads from admin form posts.
 */
class CacheRocket_Warmers {

	/**
	 * Default entitlements used when plan sync has none.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_entitlements() {
		return array(
			'maxCrawlers'              => 1,
			'maxEntryUrlsPerCrawler'   => 3,
			'maxExcludedUrlsPerCrawler'=> 5,
			'maxDepth'                 => 2,
			'maxUrlCrawlsMinute'       => 5,
			'maxUrlCrawlsDay'          => 500,
			'maxUrlCrawlsWeek'         => 2000,
			'maxUrlCrawlsMonth'        => 5000,
			'maxSitemapWarmUrls'       => 200,
			'maxPriorityWarmBatch'     => 25,
			'maxRequestTimeout'        => 15,
			'minAutoStartInterval'     => 3600,
			'maxAutoStartInterval'     => 86400,
			'minEnqueueInterval'       => 3600,
			'maxEnqueueInterval'       => 86400,
			'allowIncludeSitemaps'     => false,
			'allowUseRegex'            => false,
			'allowUseCanonical'        => true,
			'allowRewriteToHttps'      => true,
			'allowCrawlMobile'         => false,
			'allowCrawlerRegion'       => false,
			'allowWarmSchedule'        => false,
			'allowBrowserWarm'         => false,
			'allowExcludedUrls'        => true,
			'allowUrlParams'           => false,
			'allowCookies'             => false,
			'allowRequestHeaders'      => false,
			'allowUserAgents'          => false,
			'allowMobileUserAgents'    => false,
			'allowIncludePublicPosts'  => true,
			'allowAutoStartInterval'   => false,
			'allowEnqueueInterval'     => false,
			'allowCustomDepth'         => false,
			'allowRequestTimeout'      => false,
			'allowMaxUrlsPerMinute'    => false,
			'allowCdn'                 => false,
			'maxCdnPurgesDay'          => 0,
			'maxCdnBandwidthGbMonth'   => 0,
			'allowImageOptimization'   => false,
			'allowWebp'                => false,
			'allowAvif'                => false,
			'maxImageOptMonth'         => 0,
			'allowCriticalCss'         => false,
			'allowUnusedCss'           => false,
			'maxCriticalCssPagesMonth' => 0,
			'allowLqip'                => false,
			'maxLqipMonth'             => 0,
			'allowPageSpeedScores'     => false,
			'maxPageSpeedAuditsDay'    => 0,
		);
	}

	/**
	 * Entitlements from cached plan.
	 *
	 * @return array<string, mixed>
	 */
	public static function entitlements() {
		$plan = CacheRocket_Plan::get_plan();
		$ents = isset( $plan['entitlements'] ) && is_array( $plan['entitlements'] ) ? $plan['entitlements'] : array();
		return array_merge( self::default_entitlements(), $ents );
	}

	/**
	 * Warmer intervals locked to the site page-cache TTL.
	 * autoStart = TTL (rewarm as HTML expires); enqueue ≤ 1 hour (or TTL if shorter).
	 *
	 * @param int|null $ttl Optional TTL override (seconds).
	 * @return array{autoStartInterval: int, enqueueInterval: int, cacheTtl: int}
	 */
	public static function intervals_for_cache_ttl( $ttl = null ) {
		$ttl = null !== $ttl ? (int) $ttl : (int) CacheRocket_Cache::get_ttl();
		$ttl = max( 300, min( 604800, $ttl > 0 ? $ttl : CacheRocket_Cache::DEFAULT_TTL ) );
		return array(
			'autoStartInterval' => $ttl,
			'enqueueInterval'   => min( 3600, $ttl ),
			'cacheTtl'          => $ttl,
		);
	}

	/**
	 * Normalize API crawler row into form defaults.
	 *
	 * @param array<string, mixed>|null $crawler Crawler payload.
	 * @return array<string, mixed>
	 */
	public static function form_defaults( $crawler = null ) {
		$ents = self::entitlements();
		$cs = array();
		if ( is_array( $crawler ) ) {
			if ( ! empty( $crawler['CrawlSettings'] ) && is_array( $crawler['CrawlSettings'] ) ) {
				// Prisma may return a relation object or a one-item list.
				if ( isset( $crawler['CrawlSettings']['entryUrls'] ) || isset( $crawler['CrawlSettings']['id'] ) ) {
					$cs = $crawler['CrawlSettings'];
				} elseif ( isset( $crawler['CrawlSettings'][0] ) && is_array( $crawler['CrawlSettings'][0] ) ) {
					$cs = $crawler['CrawlSettings'][0];
				}
			} elseif ( ! empty( $crawler['crawlSettings'] ) && is_array( $crawler['crawlSettings'] ) ) {
				if ( isset( $crawler['crawlSettings']['entryUrls'] ) || isset( $crawler['crawlSettings']['id'] ) ) {
					$cs = $crawler['crawlSettings'];
				} elseif ( isset( $crawler['crawlSettings'][0] ) && is_array( $crawler['crawlSettings'][0] ) ) {
					$cs = $crawler['crawlSettings'][0];
				}
			}
		}

		$intervals = self::intervals_for_cache_ttl();

		return array(
			'crawlerId'          => isset( $crawler['id'] ) ? (string) $crawler['id'] : '',
			'name'               => isset( $crawler['name'] ) ? (string) $crawler['name'] : '',
			'active'             => ! empty( $crawler['active'] ),
			'region'             => isset( $crawler['region'] ) ? (string) $crawler['region'] : 'default',
			'entryUrls'          => self::urls_to_lines( isset( $cs['entryUrls'] ) ? $cs['entryUrls'] : array( home_url( '/' ) ) ),
			'excludedUrls'       => self::urls_to_lines( isset( $cs['excludedUrls'] ) ? $cs['excludedUrls'] : array() ),
			'includeSitemaps'    => ! empty( $cs['includeSitemaps'] ),
			'includePublicPosts' => array_key_exists( 'includePublicPosts', $cs ) ? ! empty( $cs['includePublicPosts'] ) : true,
			'useRegex'           => ! empty( $cs['useRegex'] ),
			'useCanonical'       => array_key_exists( 'useCanonical', $cs ) ? ! empty( $cs['useCanonical'] ) : true,
			'rewriteToHttps'     => array_key_exists( 'rewriteToHttps', $cs ) ? ! empty( $cs['rewriteToHttps'] ) : true,
			'crawlMobile'        => ! empty( $cs['crawlMobile'] ),
			'browserWarm'        => ! empty( $cs['browserWarm'] ),
			'depth'              => isset( $cs['depth'] ) ? (int) $cs['depth'] : min( 2, (int) $ents['maxDepth'] ),
			'requestTimeout'     => isset( $cs['requestTimeout'] ) ? (int) $cs['requestTimeout'] : 15,
			'maxUrlCrawlsMinute' => isset( $cs['maxUrlCrawlsMinute'] ) ? (int) $cs['maxUrlCrawlsMinute'] : min( 5, (int) $ents['maxUrlCrawlsMinute'] ),
			'autoStartInterval'  => $intervals['autoStartInterval'],
			'enqueueInterval'    => $intervals['enqueueInterval'],
			'warmScheduleJson'   => isset( $cs['warmScheduleJson'] ) ? (string) $cs['warmScheduleJson'] : '',
			'urlParams'          => self::named_pairs_to_lines( isset( $cs['urlParams'] ) ? $cs['urlParams'] : array() ),
			'cookies'            => self::named_pairs_to_lines( isset( $cs['cookies'] ) ? $cs['cookies'] : array(), true ),
			'requestHeaders'     => self::named_pairs_to_lines( isset( $cs['requestHeaders'] ) ? $cs['requestHeaders'] : array(), true ),
			'userAgents'         => self::agents_to_lines( isset( $cs['userAgents'] ) ? $cs['userAgents'] : array(), 'userAgent' ),
			'mobileUserAgents'   => self::agents_to_lines( isset( $cs['mobileUserAgents'] ) ? $cs['mobileUserAgents'] : array(), 'mobileUserAgent' ),
		);
	}

	/**
	 * Build create/update API payload from $_POST (already unslashed upstream).
	 *
	 * @param array<string, mixed> $post Post data.
	 * @param bool                 $is_update Whether this is an update.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function payload_from_post( $post, $is_update = false ) {
		$name = isset( $post['cacherocket_warmer_name'] ) ? sanitize_text_field( (string) $post['cacherocket_warmer_name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'missing_name', __( 'Warmer name is required.', 'cacherocket' ) );
		}

		$active = ! empty( $post['cacherocket_warmer_active'] );
		$ents   = self::entitlements();
		$intervals = self::intervals_for_cache_ttl();

		$entry_urls = self::lines_to_urls( isset( $post['cacherocket_warmer_entry_urls'] ) ? (string) $post['cacherocket_warmer_entry_urls'] : '' );
		if ( empty( $entry_urls ) ) {
			return new WP_Error( 'missing_entry', __( 'At least one entry URL is required.', 'cacherocket' ) );
		}

		$payload = array(
			'name'        => $name,
			'active'      => $active,
			'stopRequest' => ! $active,
			'cacheTtl'    => $intervals['cacheTtl'],
			'crawlSettings' => array(
				'entryUrls'          => $entry_urls,
				'excludedUrls'       => ! empty( $ents['allowExcludedUrls'] )
					? self::lines_to_urls( isset( $post['cacherocket_warmer_excluded_urls'] ) ? (string) $post['cacherocket_warmer_excluded_urls'] : '' )
					: array(),
				'includeSitemaps'    => ! empty( $ents['allowIncludeSitemaps'] ) && ! empty( $post['cacherocket_warmer_include_sitemaps'] ),
				'includePublicPosts' => ! empty( $ents['allowIncludePublicPosts'] ) && ! empty( $post['cacherocket_warmer_include_public_posts'] ),
				'useRegex'           => ! empty( $ents['allowUseRegex'] ) && ! empty( $post['cacherocket_warmer_use_regex'] ),
				'useCanonical'       => ! empty( $ents['allowUseCanonical'] ) && ! empty( $post['cacherocket_warmer_use_canonical'] ),
				'rewriteToHttps'     => ! empty( $ents['allowRewriteToHttps'] ) && ! empty( $post['cacherocket_warmer_rewrite_https'] ),
				'crawlMobile'        => ! empty( $ents['allowCrawlMobile'] ) && ! empty( $post['cacherocket_warmer_crawl_mobile'] ),
				'browserWarm'        => ! empty( $ents['allowBrowserWarm'] ) && ! empty( $post['cacherocket_warmer_browser_warm'] ),
				'depth'              => self::clamp_int(
					isset( $post['cacherocket_warmer_depth'] ) ? (int) $post['cacherocket_warmer_depth'] : 2,
					0,
					(int) $ents['maxDepth']
				),
				'requestTimeout'     => self::clamp_int(
					isset( $post['cacherocket_warmer_request_timeout'] ) ? (int) $post['cacherocket_warmer_request_timeout'] : 15,
					1,
					(int) $ents['maxRequestTimeout']
				),
				'maxUrlCrawlsMinute' => self::clamp_int(
					isset( $post['cacherocket_warmer_max_urls_minute'] ) ? (int) $post['cacherocket_warmer_max_urls_minute'] : 5,
					1,
					(int) $ents['maxUrlCrawlsMinute']
				),
				'autoStartInterval'  => $intervals['autoStartInterval'],
				'enqueueInterval'    => $intervals['enqueueInterval'],
				'warmScheduleJson'   => ! empty( $ents['allowWarmSchedule'] ) && isset( $post['cacherocket_warmer_schedule'] )
					? sanitize_textarea_field( (string) $post['cacherocket_warmer_schedule'] )
					: null,
				'urlParams'          => ! empty( $ents['allowUrlParams'] )
					? self::lines_to_named_pairs( isset( $post['cacherocket_warmer_url_params'] ) ? (string) $post['cacherocket_warmer_url_params'] : '' )
					: array(),
				'userAgents'         => ! empty( $ents['allowUserAgents'] )
					? self::lines_to_agents( isset( $post['cacherocket_warmer_user_agents'] ) ? (string) $post['cacherocket_warmer_user_agents'] : '', 'userAgent' )
					: array(),
				'mobileUserAgents'   => ! empty( $ents['allowMobileUserAgents'] )
					? self::lines_to_agents( isset( $post['cacherocket_warmer_mobile_agents'] ) ? (string) $post['cacherocket_warmer_mobile_agents'] : '', 'mobileUserAgent' )
					: array(),
			),
		);

		// Cookies / headers are redacted on fetch — omit empty values on update so secrets are not wiped.
		if ( ! empty( $ents['allowCookies'] ) ) {
			$cookies = self::lines_to_named_pairs( isset( $post['cacherocket_warmer_cookies'] ) ? (string) $post['cacherocket_warmer_cookies'] : '', true );
			if ( ! $is_update || ! empty( $cookies ) ) {
				$payload['crawlSettings']['cookies'] = $cookies;
			}
		} else {
			$payload['crawlSettings']['cookies'] = array();
		}
		if ( ! empty( $ents['allowRequestHeaders'] ) ) {
			$headers = self::lines_to_named_pairs( isset( $post['cacherocket_warmer_headers'] ) ? (string) $post['cacherocket_warmer_headers'] : '', true );
			if ( ! $is_update || ! empty( $headers ) ) {
				$payload['crawlSettings']['requestHeaders'] = $headers;
			}
		} else {
			$payload['crawlSettings']['requestHeaders'] = array();
		}

		if ( $is_update ) {
			$id = isset( $post['cacherocket_warmer_id'] ) ? sanitize_text_field( (string) $post['cacherocket_warmer_id'] ) : '';
			if ( '' === $id ) {
				return new WP_Error( 'missing_id', __( 'Warmer id is missing.', 'cacherocket' ) );
			}
			$payload['crawlerId'] = $id;
		} else {
			// Region is not editable in the plugin yet; new warmers always use default.
			$payload['region'] = 'default';
		}

		return $payload;
	}

	/**
	 * Attach existing entry URL ids on update so the API does not create duplicates.
	 *
	 * @param array<string, mixed> $payload Update payload.
	 * @return array<string, mixed>
	 */
	public static function attach_existing_entry_ids( $payload ) {
		if ( empty( $payload['crawlerId'] ) || empty( $payload['crawlSettings']['entryUrls'] ) || ! is_array( $payload['crawlSettings']['entryUrls'] ) ) {
			return $payload;
		}

		$fetched = cacherocket_crawler_get( (string) $payload['crawlerId'] );
		if ( is_wp_error( $fetched ) || empty( $fetched['crawler'] ) || ! is_array( $fetched['crawler'] ) ) {
			return $payload;
		}

		$cs      = array();
		$crawler = $fetched['crawler'];
		if ( ! empty( $crawler['CrawlSettings'] ) && is_array( $crawler['CrawlSettings'] ) ) {
			if ( isset( $crawler['CrawlSettings']['entryUrls'] ) ) {
				$cs = $crawler['CrawlSettings'];
			} elseif ( isset( $crawler['CrawlSettings'][0]['entryUrls'] ) ) {
				$cs = $crawler['CrawlSettings'][0];
			}
		} elseif ( ! empty( $crawler['crawlSettings'] ) && is_array( $crawler['crawlSettings'] ) ) {
			if ( isset( $crawler['crawlSettings']['entryUrls'] ) ) {
				$cs = $crawler['crawlSettings'];
			} elseif ( isset( $crawler['crawlSettings'][0]['entryUrls'] ) ) {
				$cs = $crawler['crawlSettings'][0];
			}
		}

		$id_by_url = array();
		foreach ( (array) ( isset( $cs['entryUrls'] ) ? $cs['entryUrls'] : array() ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) || empty( $row['id'] ) ) {
				continue;
			}
			$url = (string) $row['url'];
			if ( ! isset( $id_by_url[ $url ] ) ) {
				$id_by_url[ $url ] = (string) $row['id'];
			}
		}

		$mapped = array();
		foreach ( $payload['crawlSettings']['entryUrls'] as $url ) {
			$url = is_array( $url ) && isset( $url['url'] ) ? (string) $url['url'] : (string) $url;
			if ( isset( $id_by_url[ $url ] ) ) {
				$mapped[] = array(
					'id'  => $id_by_url[ $url ],
					'url' => $url,
				);
			} else {
				$mapped[] = $url;
			}
		}
		$payload['crawlSettings']['entryUrls'] = $mapped;

		return $payload;
	}

	/**
	 * @param mixed $urls URL rows.
	 * @return string
	 */
	private static function urls_to_lines( $urls ) {
		$lines = array();
		$seen  = array();
		foreach ( (array) $urls as $row ) {
			if ( is_string( $row ) ) {
				$url = $row;
			} elseif ( is_array( $row ) && isset( $row['url'] ) ) {
				$url = (string) $row['url'];
			} else {
				continue;
			}
			$url = trim( $url );
			if ( '' === $url || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$lines[]      = $url;
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param string $text Lines of URLs.
	 * @return string[]
	 */
	private static function lines_to_urls( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$url = esc_url_raw( trim( (string) $line ) );
			if ( $url ) {
				$out[] = $url;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $pairs Named pairs.
	 * @param bool  $skip_redacted Skip [REDACTED] values.
	 * @return string
	 */
	private static function named_pairs_to_lines( $pairs, $skip_redacted = false ) {
		$lines = array();
		foreach ( (array) $pairs as $pair ) {
			if ( ! is_array( $pair ) || empty( $pair['name'] ) ) {
				continue;
			}
			$value = isset( $pair['value'] ) ? (string) $pair['value'] : '';
			if ( $skip_redacted && '[REDACTED]' === $value ) {
				continue;
			}
			$lines[] = $pair['name'] . '=' . $value;
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param string $text name=value lines.
	 * @param bool   $allow_empty_value Allow empty values.
	 * @return array<int, array{name:string,value:string}>
	 */
	private static function lines_to_named_pairs( $text, $allow_empty_value = false ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $name, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$name = sanitize_text_field( $name );
			if ( '' === $name ) {
				continue;
			}
			if ( '' === $value && ! $allow_empty_value ) {
				continue;
			}
			$out[] = array(
				'name'  => $name,
				'value' => sanitize_text_field( $value ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed  $agents Agent rows.
	 * @param string $key    Field key.
	 * @return string
	 */
	private static function agents_to_lines( $agents, $key ) {
		$lines = array();
		foreach ( (array) $agents as $row ) {
			if ( is_string( $row ) ) {
				$ua = $row;
			} elseif ( is_array( $row ) && isset( $row[ $key ] ) ) {
				$ua = (string) $row[ $key ];
			} else {
				continue;
			}
			$ua = trim( $ua );
			if ( '' !== $ua ) {
				$lines[] = $ua;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param string $text Lines.
	 * @param string $key  Field key.
	 * @return array<int, array<string, string>>
	 */
	private static function lines_to_agents( $text, $key ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$ua = sanitize_text_field( trim( (string) $line ) );
			if ( '' !== $ua ) {
				$out[] = array( $key => $ua );
			}
		}
		return $out;
	}

	/**
	 * @param int $value Value.
	 * @param int $min   Min.
	 * @param int $max   Max.
	 * @return int
	 */
	private static function clamp_int( $value, $min, $max ) {
		$value = (int) $value;
		$min   = (int) $min;
		$max   = (int) $max;
		if ( $max < $min ) {
			$max = $min;
		}
		return max( $min, min( $max, $value ) );
	}
}
