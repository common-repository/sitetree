<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 6.0
 */
final class Indexer {
    /**
     * @since 6.0
     */
    const MAX_PERMALINKS_PER_SITEMAP = 1000;

    /**
     * @since 6.0
     */
    const MAX_NUMBER_OF_SITEMAPS = 50000;

    /**
     * @since 6.0
     * @var object
     */
    private $plugin;

    /**
     * @since 6.0
     * @var object
     */
    private $db;

    /**
     * @since 6.0
     * @var object
     */
    private $wpdb;

    /**
     * @since 6.0
     * @var string
     */
    private $requestedSitemapSlug;

    /**
     * @since 6.0
     * @var string
     */
    private $requestedSitemapID = '';

    /**
     * @since 6.0
     * @var int
     */
    private $requestedSitemapNumber = 0;

    /**
     * @since 6.0
     * @var string
     */
    private $requestedSitemapContentType = '';

    /**
     * Possible values: post, taxonomy, author.
     *
     * @since 6.0
     * @var string
     */
    private $requestedSitemapContentFamily = '';

    /**
     * @since 6.0
     * @var array
     */
    private $indexOfSitemaps;

    /**
     * @since 6.0
     * @var int
     */
    private $totalNumberOfSitemaps = 0;

    /**
     * @since 6.0
     * @var int
     */
    private $totalNumberOfPermalinks = -1;

    /**
     * @since 6.0
     * @var string
     */
    private $postTypesList = '';

    /**
     * @since 6.0
     * @var int
     */
    private $maxPermalinksPerSitemap;

    /**
     * @since 6.0
     *
     * @param object $plugin
     * @param string $sitemap_slug
     * @param string $sitemap_id
     */
    public function __construct( $plugin, $sitemap_slug, $sitemap_id ) {
        global $wpdb;

        $this->plugin                 = $plugin;
        $this->db                     = $plugin->db();
        $this->wpdb                   = $wpdb;
        $this->requestedSitemapSlug   = $sitemap_slug;
        $this->requestedSitemapID     = $sitemap_id;        
    }

    /**
     * @since 6.0
     * @return string
     */
    public function getRequestedSitemapSlug() {
        return $this->requestedSitemapSlug;
    }

    /**
     * @since 6.0
     * @return string
     */
    public function getRequestedSitemapID() {
        return $this->requestedSitemapID;
    }

    /**
     * @since 6.0
     * @return string
     */
    public function getRequestedSitemapUID() {
        if ( $this->requestedSitemapID == 'index' ) {
            return '';
        }

        if ( $this->requestedSitemapNumber > 0 ) {
            return ( $this->requestedSitemapID . '-' . $this->requestedSitemapNumber );
        }

        return ( $this->requestedSitemapID . '-1' );
    }

    /**
     * @since 6.0
     * @return string
     */
    public function getRequestedSitemapContentType() {
        if (! $this->requestedSitemapContentType ) {
            $this->requestedSitemapContentType = $this->requestedSitemapID;

            if ( $this->requestedSitemapID == 'index' ) {
                if ( $this->requestedSitemapSlug == 'sitemap' ) {
                    $this->requestedSitemapContentType = 'page';
                }
                else {
                    $this->requestedSitemapContentType = 'post';
                }

                $post_types = get_post_types( array( 'public' => true ) );

                foreach ( $post_types as $post_type ) {
                    if ( isset( $this->indexOfSitemaps[$post_type] ) ) {
                        $this->requestedSitemapContentType = $post_type;

                        break;
                    }
                }
            }
        }

        return $this->requestedSitemapContentType;
    }

    /**
     * @since 6.0
     * @param int $sitemap_number
     */
    public function setRequestedSitemapNumber( $sitemap_number ) {
        $this->requestedSitemapNumber = $sitemap_number;
    }

    /**
     * @since 6.0
     * @return int
     */
    public function getRequestedSitemapNumber() {
        return $this->requestedSitemapNumber;
    }

    /**
     * @since 6.0
     * @return string
     */
    public function getRequestedSitemapContentFamily() {
        if (! $this->requestedSitemapContentFamily ) {
            return 'post';
        }

        return $this->requestedSitemapContentFamily;
    }

    /**
     * @since 6.0
     * @return int
     */
    public function getTotalNumberOfSitemaps() {
        if ( ( $this->totalNumberOfSitemaps === 0 ) && $this->indexOfSitemaps ) {
            foreach( $this->indexOfSitemaps as $number_of_sitemaps ) {
                $this->totalNumberOfSitemaps += $number_of_sitemaps;
            }
        }

        return $this->totalNumberOfSitemaps;
    }

    /**
     * @since 6.0
     * @return int
     */
    public function getTotalNumberOfPermalinks() {
        return $this->totalNumberOfPermalinks;
    }

    /**
     * @since 6.0
     * @return array
     */
    public function getIndexOfSitemaps() {
        return $this->indexOfSitemaps;
    }

    /**
     * @since 6.0
     * @return string
     */
    public function getPostTypesList() {
        return $this->postTypesList;
    }

    /**
     * @since 6.0
     * @return bool
     */
    public function hasIndexJustBeenBuilt() {
        return ( $this->totalNumberOfPermalinks > -1 );
    }

    /**
     * @since 6.0
     * @return int
     */
    public function getMaxPermalinksPerSitemap() {
        if (! $this->maxPermalinksPerSitemap ) {
            $option_key = 'max_permalinks_in_' . $this->requestedSitemapSlug;

            $this->maxPermalinksPerSitemap = (int) $this->db->getOption( $option_key, self::MAX_PERMALINKS_PER_SITEMAP );
        }
        
        return $this->maxPermalinksPerSitemap;
    }

    /**
     * @since 6.0
     * @return bool Always true.
     */
    public function buildIndex() {
        $db_key = $this->requestedSitemapSlug . '_index';
        $index  = $this->db->getNonAutoloadOption( $db_key );

        if ( is_array( $index ) ) {
            $this->indexOfSitemaps = &$index;

            return true;
        }

        /**
         * @since 6.0
         */
        do_action( 'sitetree_indexer_will_build_index', $this );

        $this->countCustomPosts();

        if ( $this->requestedSitemapSlug == 'sitemap' ) {
            $this->countAuthorsPages();
            $this->countTaxonomies();
        }

        $this->db->setNonAutoloadOption( $db_key, $this->indexOfSitemaps );

        return true;
    }

    /**
     * @since 6.0
     */
    private function countCustomPosts() {
        $meta_keys  = $this->db->prepareMetaKey( "exclude_from_{$this->requestedSitemapSlug}" );
        $post_types = get_post_types( array( 'public' => true ) );
        
        foreach ( $post_types as $post_type ) {
            if ( $this->plugin->isContentTypeIncluded( $post_type, $this->requestedSitemapSlug ) ) {
                $this->postTypesList .= "'" . $post_type . "',";
            }
        }

        // Removes the trailing comma from the string.
        $this->postTypesList = substr( $this->postTypesList, 0, -1 );

        $sitemap_orderby = '';

        if ( $this->requestedSitemapSlug == 'sitemap' ) {
            $newsmap_where_condition = '';
            $meta_keys              .= ',';
            $meta_keys              .= $this->db->prepareMetaKey( 'is_ghost_page' );

            if ( $this->plugin->isContentTypeIncluded( 'page', 'sitemap' ) ) {
                $sitemap_orderby = "CASE WHEN ( content_type = 'page' ) THEN 0 ELSE 1 END,";
            }

            if ( get_option( 'page_on_front' ) <= 0 ) {
                $this->totalNumberOfPermalinks += 1;
            } 
        }
        else {
            $newsmap_where_condition = 'AND ( p.post_date_gmt >= UTC_TIMESTAMP() - INTERVAL 2 DAY )';
        }

        $query_clauses = array(
            'SELECT'          => 'p.post_type AS content_type, COUNT( p.post_type ) AS count, MAX( p.post_modified ) AS lastmod',
            'FROM'            => "{$this->wpdb->posts} AS p",
            'LEFT_OUTER_JOIN' => "{$this->wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key IN ({$meta_keys})",
            'WHERE'           => "p.post_type IN({$this->postTypesList}) AND p.post_status = 'publish' AND 
                                  p.post_password = '' AND pm.post_id IS NULL {$newsmap_where_condition}",
            'GROUP_BY'        => 'content_type',
            'ORDER BY'        => "{$sitemap_orderby} lastmod DESC"
        );

        /**
         * @since 6.0
         */
        $query_clauses = apply_filters( 'sitetree_indexer_posts_count_query', $query_clauses, $this->postTypesList );
        
        $this->updateIndex( $this->db->getResults( $query_clauses ) );
    }

    /**
     * @since 6.0
     * @return bool
     */
    private function countTaxonomies() {
        if ( $this->maxNumberOfSitemapsToIndex() <= 0 ) {
            return false;
        }

        $taxonomies_list = $excluded_ids = $term_not_in = '';
        $taxonomies      = get_taxonomies( array( 'public' => true ) );
        
        foreach ( $taxonomies as $taxonomy_name ) {
            if ( $this->plugin->isContentTypeIncluded( $taxonomy_name, 'sitemap' ) ) {
                $taxonomies_list .= "'" . $taxonomy_name . "',";
                $ids              = $this->db->getOption( $taxonomy_name, '', 'exclude_from_sitemap' );
            
                if ( $ids ) {
                    $excluded_ids .= ',' . $ids;
                }
            }
        }

        if (! $taxonomies_list ) {
            return false;
        }

        // Removes the trailing comma from the string.
        $taxonomies_list = substr( $taxonomies_list, 0, -1 );

        if ( $excluded_ids ) {
            $excluded_ids = implode( ',', wp_parse_id_list( $excluded_ids ) );
            $term_not_in  = 't.term_id NOT IN (' . $excluded_ids . ') AND';
        }

        $query_clauses = array(
            'SELECT'     => 'tt.taxonomy AS content_type, COUNT( DISTINCT t.slug ) AS count, MAX( p.post_modified ) AS lastmod',
            'FROM'       => "{$this->wpdb->terms} AS t",
            'INNER_JOIN' => "{$this->wpdb->term_taxonomy} AS tt USING( term_id )
                             INNER JOIN {$this->wpdb->term_relationships} AS tr USING( term_taxonomy_id )
                             INNER JOIN {$this->wpdb->posts} AS p ON p.ID = tr.object_id",
            'WHERE'      => "{$term_not_in} tt.taxonomy IN ({$taxonomies_list}) AND p.post_status = 'publish'",
            'GROUP_BY'   => 'content_type',
            'ORDER_BY'   => 'lastmod DESC'
        );

        /**
         * @since 6.0
         */
        $query_clauses = apply_filters( 'sitetree_indexer_taxonomies_count_query', $query_clauses, $taxonomies_list );
        
        $this->updateIndex( $this->db->getResults( $query_clauses ) );

        return true;
    }

    /**
     * @since 6.0
     * @return bool
     */
    private function countAuthorsPages() {
        if ( $this->maxNumberOfSitemapsToIndex() <= 0 ) {
            return false;
        }

        if (! $this->plugin->isContentTypeIncluded( 'authors', 'sitemap' ) ) {
            return false;
        }

        $nicename_not_in  = '';
        $excluded_authors = $this->db->getOption( 'exclude', '', 'authors', 'site_tree' );

        if ( $excluded_authors ) {
            $excluded_authors_list = '';
            $excluded_authors      = explode( ',', $excluded_authors );

            foreach ( $excluded_authors as $author_nickname ) {
                $excluded_authors_list .= "'" . sanitize_text_field( $author_nickname ) . "',";
            }

            // Removes the trailing comma from the string.
            $excluded_authors_list = substr( $excluded_authors_list, 0, -1);
            $nicename_not_in       = "u.user_nicename NOT IN ({$excluded_authors_list}) AND";
        }

        $counters = $this->wpdb->get_results(
            "SELECT 'authors' AS content_type, COUNT( DISTINCT u.ID ) AS count
             FROM {$this->wpdb->users} AS u
             INNER JOIN {$this->wpdb->posts} AS p ON p.post_author = u.ID
             WHERE $nicename_not_in p.post_type = 'post' AND p.post_status = 'publish'"
        );

        $this->updateIndex( $counters );

        return true;
    }

    /**
     * @since 6.0
     * @return int
     */
    private function maxNumberOfSitemapsToIndex() {
        $number_of_sitemaps_that_can_be_listed = ( self::MAX_NUMBER_OF_SITEMAPS - $this->totalNumberOfSitemaps );

        return $number_of_sitemaps_that_can_be_listed;
    }

    /**
     * @since 6.0
     * @param array $counters
     */
    private function updateIndex( $counters ) {
        $max_permalinks_per_sitemap = $this->getMaxPermalinksPerSitemap();

        foreach ( $counters as $counter ) {
            if ( $counter->count > 0 ) {
                $number_of_sitemaps                    = ceil( $counter->count / $max_permalinks_per_sitemap );
                $number_of_sitemaps_that_can_be_listed = $this->maxNumberOfSitemapsToIndex();

                if ( $number_of_sitemaps > $number_of_sitemaps_that_can_be_listed ) {
                    $number_of_permalinks = ( $number_of_sitemaps_that_can_be_listed * $max_permalinks_per_sitemap );

                    $this->totalNumberOfSitemaps                  += $number_of_sitemaps_that_can_be_listed;
                    $this->totalNumberOfPermalinks                += $number_of_permalinks;
                    $this->indexOfSitemaps[$counter->content_type] = $number_of_sitemaps_that_can_be_listed;

                    break;
                }

                $this->totalNumberOfSitemaps                  += $number_of_sitemaps;
                $this->totalNumberOfPermalinks                += $counter->count;
                $this->indexOfSitemaps[$counter->content_type] = $number_of_sitemaps;
            }
        }

        if ( $this->totalNumberOfPermalinks > -1 ) {
            $this->totalNumberOfPermalinks += 1;
        }
    }

    /**
     * @since 6.0
     * @return bool
     */
    public function sitemapIDisValid() {
        if ( $this->requestedSitemapID == 'authors' ) {
            $this->requestedSitemapContentFamily = 'author';

            return true;
        }

        $post_types = get_post_types( array( 'public' => true ) );
        
        foreach ( $post_types as $post_type ) {
            if ( $this->requestedSitemapID == $post_type ) {
                $this->requestedSitemapContentFamily = 'post';

                return true;
            }
        }

        $taxonomies = get_taxonomies( array( 'public' => true ) );
        
        foreach ( $taxonomies as $taxonomy_name ) {
            if ( $this->requestedSitemapID == $taxonomy_name ) {
                $this->requestedSitemapContentFamily = 'taxonomy';

                return true;
            }
        }

        $this->requestedSitemapID = '';

        return false;
    }

    /**
     * @since 6.0
     * @return bool
     */
    public function requestedSitemapExists() {
        $this->buildIndex();

        if (! isset( $this->indexOfSitemaps[$this->requestedSitemapID] ) ) {
            return false;
        }

        $number_of_sitemaps = $this->indexOfSitemaps[$this->requestedSitemapID];

        return ( $this->requestedSitemapNumber <= $number_of_sitemaps );
    }
}
?>