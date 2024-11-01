<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class SitemapBuilder
    extends GoogleSitemapBuilder
 implements SitemapBuilderInterface {
    /**
     * @since 6.0
     */
    const SITEMAP_SLUG = 'sitemap';

    /**
     * Do not rise this limit: the plugin has not been 
     * engineered to work over the value set.
     *
     * @since 5.0
     */
    const MAX_NUMBER_OF_URLs = 10000;
    
    /**
     * @since 5.0
     * @var array
     */
    private $allowedChangeFrequencies = array(
        'hourly' => true, 'daily'   => true, 
        'weekly' => true, 'monthly' => true,
        'yearly' => true, 'always'  => true,
        'never'  => true
    );
    
    /**
     * @since 5.0
     * @var array
     */
    private $allowedPriorities = array(
        '0.0' => true, '0.1' => true, '0.2' => true,
        '0.3' => true, '0.4' => true, '0.5' => true,
        '0.6' => true, '0.7' => true, '0.8' => true,
        '0.9' => true, '1.0' => true
    );

    /**
     * Total number of image elements that have been built.
     *
     * @since 5.0
     * @var string
     */
    private $numberOfImages = 0;

    /**
     * @since 5.0
     * @var array
     */
    private $queriedPosts;

    /**
     * @since 5.0
     * @var array
     */
    private $queriedPostsIDs;

    /**
     * @since 5.0
     * @var array
     */
    private $queriedImages;

    /**
     * ID of the post whose URL element is being built.
     *
     * @since 5.3
     * @var array
     */
    private $currentID;

	/**
	 * @since 5.0
	 * @var int
	 */
	private $timezoneOffsetInSeconds;

	/**
     * @since 5.0
     *
     * @param object $plugin
     * @param object $delegate
     */
    public function __construct( $plugin, $delegate ) {
        parent::__construct( $plugin, $delegate );

        $this->timezoneOffsetInSeconds = $this->gmtOffset * HOUR_IN_SECONDS;
    }

    /**
     * @see parent::getMetrics()
     * @since 6.0
     */
    public function getMetrics() {
        $this->metrics['num_images'] = $this->numberOfImages;

        return $this->metrics;
    }

    /**
     * @see parent::runBuildingProcess()
     * @since 5.0
     */
    protected function runBuildingProcess() {
        switch ( $this->indexer->getRequestedSitemapContentFamily() ) {
            case 'post':
                if (! $this->buildPostsElements() ) {
                    $this->buildHomePageElement();
                }
                break;

            case 'taxonomy':
                $this->buildTaxonomyPagesElements();
                break;
            
            case 'author':
                $this->buildAuthorsPagesElements();
                break;
        }
        
        /**
	     * @since 5.0
	     */
        do_action( 'sitetree_is_building_sitemap', $this, $this->indexer );
    }

    /**
     * @since 5.3
     * @return int
     */
    public function getCurrentID() {
        return $this->currentID;
    }

    /**
     * @since 5.3
     * @param string $string
     */
    public function appendToOutput( $string ) {
        $this->output .= $string;
    }

    /**
     * {@inheritdoc}
     */
    public function buildURLElement( $url, $lastmod = '', $images = array(), ...$deprecated_args ) {
        $images_count = 0;
        $url = esc_url( $url );

        if ( $lastmod && isset( $this->allowedChangeFrequencies[$lastmod] ) ) {
        	_deprecated_argument( 'SiteTree::buildURLElement()', '3.0', 'The $changefreq argument is deprecated.' );

        	$lastmod = ( $deprecated_args ? $deprecated_args[0] : '' );
        }

        if ( $images && !is_array( $images ) && isset( $this->allowedPriorities[$images] ) ) {
        	_deprecated_argument( 'SiteTree::buildURLElement()', '3.0', 'The $priority argument is deprecated.' );

        	$images = ( $deprecated_args ? $deprecated_args[1] : array() );
        }

        $this->incrementItemsCounter();

        $this->output .= '<url>' . $this->lineBreak;
        $this->output .= '<loc>' . $url . '</loc>' . $this->lineBreak;
        
        if ( $lastmod ) {
        	$timestamp = ( is_int( $lastmod ) ? $lastmod : strtotime( $lastmod ) );
        	
        	if ( $timestamp ) {
        		$this->output .= '<lastmod>';
	            $this->output .= gmdate( 'Y-m-d\TH:i:s', $timestamp );
	            $this->output .= $this->timezoneOffset . '</lastmod>' . $this->lineBreak;
        	}
        }

        /**
         * @since 5.3
         */
        do_action( 'sitetree_sitemap_builder_is_making_url_element', $this, $url );
        
        foreach ( $images as $image ) {
			if ( !( $image instanceof ImageElement ) ) {
        		continue;
        	}

        	$title   = $image->title();
        	$caption = $image->caption();

            $this->output .= '<image:image>' . $this->lineBreak 
            			   . '<image:loc>' . $image->url() 
                           . '</image:loc>' . $this->lineBreak;
            
            if ( $title ) {
                $this->output .= '<image:title>' . $this->prepareAttribute( $title )
                               . '</image:title>' . $this->lineBreak;
            }
            
            if ( $caption ) {
                $this->output .= '<image:caption>' . $this->prepareAttribute( $caption, 160 )
                               . '</image:caption>' . $this->lineBreak;
            }
            
            $this->output .= '</image:image>' . $this->lineBreak;

            if ( ++$images_count >= self::IMAGES_PER_URL_ELEMENT ) {
                break;
            }
        }

        $this->currentID       = 0;
        $this->numberOfImages += $images_count;
        
        $this->output .= '</url>' . $this->lineBreak;
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function buildPostsElements() {
        if (! $this->queryPosts() ) {
            return false;
        }

        $this->queryImages();
        $this->buildHomePageElement();
        $this->buildBlogPageElement();

        foreach ( $this->queriedPosts as $post_type => $posts ) {
            $post_type_is_page  = ( $post_type == 'page' );
            
            foreach ( $posts as $post ) {
                $images            = array();
				$lastmod 		   = $post->post_modified;
                $page_has_template = false;

                $this->currentID = $post->ID;

                if ( isset( $this->queriedImages[$post->ID] ) ) {
                    $images = &$this->queriedImages[$post->ID];
                }

                if ( $post_type_is_page ) {
                	$page_templates = array(
                		"page-{$post->post_name}.php",
                		"page-{$post->ID}.php",
                	);
                	$lastmod = $this->getPageTemplateLastmod( $page_templates, $lastmod );
                }

                $this->buildURLElement( get_permalink( $post ), $lastmod, $images );
            }
        }

        unset( $this->queriedPosts, $this->queriedPostsIDs, $this->queriedImages );

        return true;
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function queryPosts() {
        $post_type_to_include = $this->indexer->getRequestedSitemapContentType();

        $meta_keys  = $this->db->prepareMetaKey( 'exclude_from_sitemap' );
        $meta_keys .= ',';
        $meta_keys .= $this->db->prepareMetaKey( 'is_ghost_page' );

        $query_clauses = array(
            'SELECT'          => 'p.ID, p.post_name, p.post_modified, p.post_parent, p.post_type, p.post_status',
            'FROM'            => "{$this->wpdb->posts} AS p",
            'LEFT_OUTER_JOIN' => "{$this->wpdb->postmeta} AS pm ON pm.post_id = p.ID AND pm.meta_key IN ({$meta_keys})",
            'WHERE'           => "p.post_type = '{$post_type_to_include}' AND p.post_status = 'publish' AND
                                  p.post_password = '' AND pm.post_id IS NULL",
            'ORDER_BY'        => 'p.post_modified DESC',
            'LIMIT'           => $this->buildingCapacityLeft(),
            'OFFSET'          => $this->getMysqlOffset()
        );

        /**
         * @since 5.3
         */
        $query_clauses = apply_filters( 'sitetree_sitemap_builder_posts_query', $query_clauses, $post_type_to_include );

        $posts = $this->db->getResults( $query_clauses );

        if (! $posts ) {
            return false;
        }

        foreach ( $posts as $post ) {
            $post = sanitize_post( $post, 'raw' );
            
            $this->queriedPostsIDs[] = $post->ID;
            $this->queriedPosts[$post->post_type][$post->ID] = $post;

            wp_cache_add( $post->ID, $post, 'posts' );
        }

        update_meta_cache( 'post', $this->queriedPostsIDs );

        /**
         * @since 5.3
         */
        do_action( 'sitetree_sitemap_builder_did_query_posts', $this->queriedPostsIDs );

        return true;
    }

    /**
     * @since 5.0
     */
    private function queryImages() {
        $list_of_ids = implode( ',', $this->queriedPostsIDs );
        
        $attachments = $this->wpdb->get_results(
            "SELECT ID, post_title, post_content, post_excerpt, post_parent, post_type
             FROM {$this->wpdb->posts}
             WHERE post_parent IN ({$list_of_ids}) AND post_type = 'attachment' AND
                   post_mime_type LIKE 'image/%'
             ORDER BY post_modified DESC"
        );
        
        if ( $attachments ) {
        	$attachmentsIDs = array();

            foreach ( $attachments as $attachment ) {
            	$attachment = sanitize_post( $attachment, 'raw' );
                
                $post_id          = $attachment->post_parent;
                $attachmentsIDs[] = $attachment->ID;

                wp_cache_add( $attachment->ID, $attachment, 'posts' );

                $this->queriedImages[$post_id][] = ImageElement::makeFromAttachment( $attachment );
            }

            update_meta_cache( 'post', $attachmentsIDs );
        }
    }

    /**
     * @since 5.0
     */
    private function buildHomePageElement() {
        $front_page_id = (int) get_option( 'page_on_front' );

        if ( 
            ( $this->indexer->getRequestedSitemapContentType() == 'page' ) && 
            ( $this->indexer->getRequestedSitemapNumber() === 0 ) 
        ) {
            if ( $front_page_id ) {
                $images = array();

                $this->currentID = $front_page_id;

                if ( isset( $this->queriedPosts['page'][$front_page_id] ) ) {
                    $frontPage = $this->queriedPosts['page'][$front_page_id];
                }
                else {
                    $frontPage = get_post( $front_page_id );
                }
                
                if ( isset( $this->queriedImages[$frontPage->ID] ) ) {
                    $images = $this->queriedImages[$frontPage->ID];
                }

                $this->buildURLElement(
                    home_url('/'),
                    $this->getPageTemplateLastmod( 'front-page.php', $frontPage->post_modified ),
                    $images
                ); 
            }
            else {
                $this->buildURLElement( home_url( '/' ), get_lastpostmodified( 'blog' ) );
            }
        }

        unset( $this->queriedPosts['page'][$front_page_id] );
    }

    /**
     * @since 5.0
     */
    private function buildBlogPageElement() {
        $blog_page_id = (int) get_option( 'page_for_posts' );

        if ( isset( $this->queriedPosts['page'][$blog_page_id] )  ) {
            $images   = array();
            $blogPage = $this->queriedPosts['page'][$blog_page_id];

            $this->currentID = $blog_page_id;
            
            if ( isset( $this->queriedPosts['post'] ) ) {
                $lastmod = reset( $this->queriedPosts['post'] )->post_modified;
            }
            else {
                $lastmod = $blogPage->post_modified;
            }

            if ( isset( $this->queriedImages[$blogPage->ID] ) ) {
                $images = $this->queriedImages[$blogPage->ID];
            }
            
            $this->buildURLElement( get_permalink( $blogPage ), $lastmod, $images );

            unset( $this->queriedPosts['page'][$blog_page_id] );
        }
    }

    /**
     * Attempts to get the modification time of a page template and
     * returns it if more recent than $default_lastmod.
     *
     * @since 5.0
     *
     * @param string|array $template_name
     * @param string $default_lastmod
     * @return int|string Timestamp or date string.
     */
    private function getPageTemplateLastmod( $template_name, $default_lastmod ) {
    	$template_filename = locate_template( $template_name );

        if ( $template_filename ) {
        	$template_mtime = filemtime( $template_filename ) + $this->timezoneOffsetInSeconds;

        	if ( $template_mtime > strtotime( $default_lastmod ) ) {
        		return $template_mtime;
        	}
        }

        return $default_lastmod;
    }

    /**
     * @since 5.0
     */
    private function buildAuthorsPagesElements() {
        $authors = $this->wpdb->get_results(
            "SELECT u.ID, u.user_nicename, MAX( p.post_modified ) AS last_post_modified
             FROM {$this->wpdb->users} AS u
             INNER JOIN {$this->wpdb->posts} AS p ON p.post_author = u.ID
             WHERE p.post_type = 'post' AND p.post_status = 'publish'
             GROUP BY p.post_author 
             ORDER BY last_post_modified DESC
             LIMIT {$this->buildingCapacityLeft()}
             OFFSET {$this->getMysqlOffset()}"
        );
        
        if (! $authors ) {
            return false;
        }

        foreach ( $authors as $author ) {
            $this->buildURLElement(
                get_author_posts_url( $author->ID, $author->user_nicename ),
                $author->last_post_modified
            );
        }
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function buildTaxonomyPagesElements() {
        $term_not_in         = '';
        $taxonomy_to_include = $this->indexer->getRequestedSitemapContentType();
        $excluded_ids        = $this->db->getOption( $taxonomy_to_include, '', 'exclude_from_sitemap' );
        
        if ( $excluded_ids ) {
            $excluded_ids = implode( ',', wp_parse_id_list( $excluded_ids ) );
            $term_not_in  = 't.term_id NOT IN (' . $excluded_ids . ') AND';
        }

        $query_clauses = array(
            'SELECT'     => 't.term_id, t.slug, tt.term_taxonomy_id, tt.taxonomy, MAX(p.post_modified) AS last_modified',
            'FROM'       => "{$this->wpdb->terms} AS t",
            'INNER_JOIN' => "{$this->wpdb->term_taxonomy} AS tt USING(term_id)
                                INNER JOIN {$this->wpdb->term_relationships} AS tr USING(term_taxonomy_id)
                                INNER JOIN {$this->wpdb->posts} AS p ON p.ID = tr.object_id",
            'WHERE'      => "{$term_not_in} tt.taxonomy = '{$taxonomy_to_include}' AND p.post_status = 'publish'",
            'GROUP_BY'   => 't.term_id, tt.taxonomy',
            'ORDER_BY'   => 'last_modified DESC',
            'LIMIT'      => $this->buildingCapacityLeft(),
            'OFFSET'     => $this->getMysqlOffset()
        );

        /**
         * @since 5.3
         */
        $query_clauses = apply_filters( 'sitetree_sitemap_builder_taxonomies_query', $query_clauses, $taxonomy_to_include );

        $terms = $this->db->getResults( $query_clauses );
        
        if (! $terms ) {
            return false;
        }

        $ids = array();

        foreach ( $terms as $term ) {
            $term = sanitize_term( $term, $term->taxonomy, 'raw' );
            
            $ids[] = $term->term_id;
            
            wp_cache_add( $term->term_id, $term, $term->taxonomy );
        }

        /**
         * @since 5.3
         */
        do_action( 'sitetree_sitemap_builder_did_query_taxonomies', $ids );

        foreach ( $terms as $term ) {
            $this->currentID = $term->term_id;
            
            $this->buildURLElement( get_term_link( $term ), $term->last_modified );
        }
    }
}
?>