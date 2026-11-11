<?php
/**
 * Schema.org JSON-LD emitter.
 *
 * Builds structured data per template and emits it in wp_head priority 20.
 * Every builder reads real stored data; empty fields are omitted, not
 * emitted as empty strings.
 *
 * @package AIFirst
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Schema.
 *
 * @since 0.7.0
 */
class AIFirst_Schema {

	/**
	 * Hook emission.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'maybe_emit_schema' ), 20 );
	}

	/**
	 * Decide what to emit for the current request.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public function maybe_emit_schema() {
		$blocks = array();

		if ( is_singular( 'aifirst_tool' ) ) {
			$blocks[] = $this->build_tool_schema( get_post() );
			$blocks[] = $this->build_breadcrumbs_schema( $this->breadcrumbs_for_tool( get_post() ) );
			$blocks[] = $this->build_faq_for_tool( get_post() );
		} elseif ( is_singular( 'aifirst_comparison' ) ) {
			$blocks[] = $this->build_comparison_schema( get_post() );
			$blocks[] = $this->build_breadcrumbs_schema(
				array(
					array( 'name' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
					array( 'name' => __( 'Compare', 'aifirstsaas' ), 'url' => home_url( '/compare/' ) ),
					array( 'name' => get_the_title( get_post() ), 'url' => get_permalink( get_post() ) ),
				)
			);
		} elseif ( is_tax( 'aifirst_category' ) ) {
			$term     = get_queried_object();
			$blocks[] = $this->build_tax_schema( $term, 'aifirst_category' );
			$blocks[] = $this->build_breadcrumbs_schema(
				array(
					array( 'name' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
					array( 'name' => __( 'Categories', 'aifirstsaas' ), 'url' => home_url( '/categories/' ) ),
					array( 'name' => $term ? $term->name : '', 'url' => $term ? get_term_link( $term ) : '' ),
				)
			);
			if ( $term ) {
				$blocks[] = $this->build_faq_block( AIFirst_Query::faq( array( 'category_id' => $term->slug ) ) );
			}
		} elseif ( is_tax( 'aifirst_feature' ) ) {
			$term     = get_queried_object();
			$blocks[] = $this->build_tax_schema( $term, 'aifirst_feature' );
			$blocks[] = $this->build_breadcrumbs_schema(
				array(
					array( 'name' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
					array( 'name' => __( 'AI Features', 'aifirstsaas' ), 'url' => home_url( '/features/' ) ),
					array( 'name' => $term ? $term->name : '', 'url' => $term ? get_term_link( $term ) : '' ),
				)
			);
			if ( $term ) {
				$blocks[] = $this->build_faq_block( AIFirst_Query::faq( array( 'category_id' => $term->slug ) ) );
			}
		} elseif ( is_post_type_archive( 'aifirst_tool' ) ) {
			$blocks[] = $this->build_tools_archive_schema();
			$blocks[] = $this->build_breadcrumbs_schema(
				array(
					array( 'name' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
					array( 'name' => __( 'All Tools', 'aifirstsaas' ), 'url' => get_post_type_archive_link( 'aifirst_tool' ) ),
				)
			);
			$blocks[] = $this->build_faq_block( AIFirst_Query::faq() );
		} elseif ( is_singular( 'post' ) ) {
			$blocks[] = $this->build_post_schema( get_post() );
		}

		foreach ( $blocks as $b ) {
			if ( empty( $b ) ) {
				continue;
			}
			$this->emit( $b );
		}
	}

	/**
	 * Build SoftwareApplication schema for a tool.
	 *
	 * @since 0.7.0
	 * @param WP_Post $post Tool post.
	 * @return array|null
	 */
	public function build_tool_schema( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$meta = AIFirst_Query::tool_meta( $post->ID );
		if ( ! $meta ) {
			return null;
		}
		$tool_id = (string) $meta->tool_id;

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'SoftwareApplication',
			'name'     => get_the_title( $post ),
			'url'      => get_permalink( $post ),
		);

		$desc = (string) ( get_the_excerpt( $post ) ?: wp_strip_all_tags( $post->post_content ) );
		if ( '' !== $desc ) {
			$schema['description'] = wp_strip_all_tags( $desc );
		}

		$cats = get_the_terms( $post->ID, 'aifirst_category' );
		if ( $cats && ! is_wp_error( $cats ) ) {
			$schema['applicationCategory'] = $cats[0]->name;
		}
		$schema['operatingSystem'] = 'Web';

		if ( has_post_thumbnail( $post ) ) {
			$img = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'full' );
			if ( $img ) {
				$schema['image'] = $img;
			}
		} elseif ( '' !== (string) $meta->logo_url ) {
			$schema['image'] = (string) $meta->logo_url;
		}

		// Offers — one per plan.
		$plans  = AIFirst_Query::plans( $tool_id );
		$offers = array();
		foreach ( (array) $plans as $p ) {
			if ( '' === (string) $p->plan_name ) {
				continue;
			}
			$offer = array(
				'@type' => 'Offer',
				'name'  => (string) $p->plan_name,
			);
			if ( null !== $p->price_usd_monthly ) {
				$offer['price']         = (string) $p->price_usd_monthly;
				$offer['priceCurrency'] = 'USD';
			} elseif ( (int) $p->is_free === 1 ) {
				$offer['price']         = '0';
				$offer['priceCurrency'] = 'USD';
			}
			$offers[] = $offer;
		}
		if ( ! empty( $offers ) ) {
			$schema['offers'] = 1 === count( $offers ) ? $offers[0] : $offers;
		}

		// aggregateRating — average G2 + Capterra when either is present.
		$senti = AIFirst_Query::sentiment( $tool_id );
		$want  = array( 'g2', 'capterra' );
		$sum   = 0.0;
		$n     = 0;
		$reviews_total = 0;
		foreach ( (array) $senti as $s ) {
			if ( in_array( strtolower( (string) $s->source ), $want, true ) && null !== $s->score ) {
				$sum += (float) $s->score;
				++$n;
			}
			$reviews_total += (int) $s->review_count;
		}
		if ( $n > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $sum / $n, 2 ),
				'bestRating'  => 5,
				'worstRating' => 0,
				'reviewCount' => max( 1, (int) $reviews_total ),
			);
		}

		// Review entries from stored quotes.
		$quotes = AIFirst_Query::quotes( $tool_id );
		$reviews = array();
		foreach ( (array) $quotes as $q ) {
			if ( '' === trim( (string) $q->quote_text ) ) {
				continue;
			}
			$r = array(
				'@type'      => 'Review',
				'reviewBody' => (string) $q->quote_text,
			);
			if ( '' !== (string) $q->role ) {
				$r['author'] = array(
					'@type' => 'Person',
					'name'  => (string) $q->role,
				);
			}
			$r['publisher'] = array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			);
			$reviews[] = $r;
		}
		if ( ! empty( $reviews ) ) {
			$schema['review'] = $reviews;
		}

		return $schema;
	}

	/**
	 * Build Article schema for a comparison post.
	 *
	 * @since 0.7.0
	 * @param WP_Post $post Comparison post.
	 * @return array|null
	 */
	public function build_comparison_schema( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => get_the_title( $post ),
			'url'           => get_permalink( $post ),
			'datePublished' => mysql2date( DATE_W3C, $post->post_date_gmt ?: $post->post_date ),
			'dateModified'  => mysql2date( DATE_W3C, $post->post_modified_gmt ?: $post->post_modified ),
		);
		$excerpt = get_the_excerpt( $post );
		if ( '' !== $excerpt ) {
			$schema['description'] = $excerpt;
		}

		$slugs = AIFirst_Comparison::get_tool_ids( $post->ID );
		$about = array();
		foreach ( $slugs as $slug ) {
			$meta = AIFirst_Query::tool_meta_by_id( $slug );
			if ( ! $meta ) {
				continue;
			}
			$about[] = array(
				'@type' => 'SoftwareApplication',
				'name'  => get_the_title( (int) $meta->post_id ),
				'url'   => get_permalink( (int) $meta->post_id ),
			);
		}
		if ( ! empty( $about ) ) {
			$schema['about'] = $about;
		}
		return $schema;
	}

	/**
	 * Build a CollectionPage + ItemList for a taxonomy archive.
	 *
	 * @since 0.7.0
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy slug.
	 * @return array|null
	 */
	public function build_tax_schema( $term, $taxonomy ) {
		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		if ( 'aifirst_feature' === $taxonomy ) {
			$tools = AIFirst_Query::tools_with_feature( $term->slug );
		} else {
			$tools = get_posts(
				array(
					'post_type'      => 'aifirst_tool',
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'tax_query'      => array(
						array(
							'taxonomy' => $taxonomy,
							'field'    => 'term_id',
							'terms'    => (int) $term->term_id,
						),
					),
				)
			);
		}

		$items = array();
		$pos   = 1;
		foreach ( (array) $tools as $t ) {
			$post_id = isset( $t->post_id ) ? (int) $t->post_id : ( isset( $t->ID ) ? (int) $t->ID : 0 );
			if ( 0 === $post_id ) { continue; }
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'url'      => get_permalink( $post_id ),
				'name'     => get_the_title( $post_id ),
			);
			++$pos;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'CollectionPage',
			'name'     => $term->name,
			'url'      => get_term_link( $term ),
		);
		if ( '' !== (string) $term->description ) {
			$schema['description'] = wp_strip_all_tags( $term->description );
		}
		if ( ! empty( $items ) ) {
			$schema['mainEntity'] = array(
				'@type'           => 'ItemList',
				'numberOfItems'   => count( $items ),
				'itemListElement' => $items,
			);
		}
		return $schema;
	}

	/**
	 * Build a CollectionPage + ItemList for the All Tools archive.
	 *
	 * @since 0.7.0
	 * @return array|null
	 */
	public function build_tools_archive_schema() {
		$tools = get_posts(
			array(
				'post_type'      => 'aifirst_tool',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$items = array();
		$pos   = 1;
		foreach ( $tools as $t ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'url'      => get_permalink( $t ),
				'name'     => get_the_title( $t ),
			);
			++$pos;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'CollectionPage',
			'name'     => __( 'All Tools', 'aifirstsaas' ),
			'url'      => get_post_type_archive_link( 'aifirst_tool' ),
		);
		if ( ! empty( $items ) ) {
			$schema['mainEntity'] = array(
				'@type'           => 'ItemList',
				'numberOfItems'   => count( $items ),
				'itemListElement' => $items,
			);
		}
		return $schema;
	}

	/**
	 * Build BlogPosting schema for a core post.
	 *
	 * @since 0.7.0
	 * @param WP_Post $post Post.
	 * @return array|null
	 */
	public function build_post_schema( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'BlogPosting',
			'headline'      => get_the_title( $post ),
			'url'           => get_permalink( $post ),
			'datePublished' => mysql2date( DATE_W3C, $post->post_date_gmt ?: $post->post_date ),
			'dateModified'  => mysql2date( DATE_W3C, $post->post_modified_gmt ?: $post->post_modified ),
		);
		$excerpt = get_the_excerpt( $post );
		if ( '' !== $excerpt ) {
			$schema['description'] = $excerpt;
		}
		$author = get_userdata( (int) $post->post_author );
		if ( $author ) {
			$schema['author'] = array(
				'@type' => 'Person',
				'name'  => $author->display_name,
			);
		}
		if ( has_post_thumbnail( $post ) ) {
			$img = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'large' );
			if ( $img ) {
				$schema['image'] = $img;
			}
		}
		return $schema;
	}

	/**
	 * BreadcrumbList schema.
	 *
	 * @since 0.7.0
	 * @param array $crumbs [{name,url}, …]
	 * @return array|null
	 */
	public function build_breadcrumbs_schema( $crumbs ) {
		if ( empty( $crumbs ) ) {
			return null;
		}
		$items = array();
		$pos   = 1;
		foreach ( $crumbs as $c ) {
			if ( empty( $c['name'] ) ) {
				continue;
			}
			$item = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => (string) $c['name'],
			);
			if ( ! empty( $c['url'] ) ) {
				$item['item'] = (string) $c['url'];
			}
			$items[] = $item;
			++$pos;
		}
		if ( empty( $items ) ) {
			return null;
		}
		return array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BreadcrumbList',
			'itemListElement'  => $items,
		);
	}

	/**
	 * Build the crumb list used for a single tool page.
	 *
	 * @since 0.7.0
	 * @param WP_Post $post Tool post.
	 * @return array
	 */
	protected function breadcrumbs_for_tool( $post ) {
		$crumbs = array(
			array( 'name' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
			array( 'name' => __( 'Tools', 'aifirstsaas' ), 'url' => get_post_type_archive_link( 'aifirst_tool' ) ),
		);
		$cats = get_the_terms( $post->ID, 'aifirst_category' );
		if ( $cats && ! is_wp_error( $cats ) ) {
			$crumbs[] = array(
				'name' => $cats[0]->name,
				'url'  => get_term_link( $cats[0] ),
			);
		}
		$crumbs[] = array(
			'name' => get_the_title( $post ),
			'url'  => get_permalink( $post ),
		);
		return $crumbs;
	}

	/**
	 * FAQ schema from an aifirst_faq row set.
	 *
	 * @since 0.7.0
	 * @param array $rows aifirst_faq rows.
	 * @return array|null
	 */
	public function build_faq_block( $rows ) {
		if ( empty( $rows ) ) {
			return null;
		}
		$entities = array();
		foreach ( $rows as $f ) {
			if ( '' === trim( (string) $f->question ) || '' === trim( (string) $f->answer ) ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => (string) $f->question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( (string) $f->answer ),
				),
			);
		}
		if ( empty( $entities ) ) {
			return null;
		}
		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}

	/**
	 * Convenience: FAQ for a single tool.
	 *
	 * @since 0.7.0
	 * @param WP_Post $post Tool post.
	 * @return array|null
	 */
	protected function build_faq_for_tool( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$tool_id = get_post_meta( $post->ID, AIFirst_Intake::TOOL_ID_META_KEY, true );
		if ( '' === (string) $tool_id ) {
			$tool_id = $post->post_name;
		}
		return $this->build_faq_block( AIFirst_Query::faq( array( 'tool_id' => (string) $tool_id ) ) );
	}

	/**
	 * Emit one JSON-LD block.
	 *
	 * @since 0.7.0
	 * @param array $data Schema data.
	 * @return void
	 */
	protected function emit( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\">" . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
