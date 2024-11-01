<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 6.0
 */
final class Paginator {
    /**
     * 'Previous' and 'Next' links excluded.
     *
     * @since 6.0
     */
    const MAX_NUMBER_OF_NAV_ITEMS = 10;

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
     * @var int
     */
    private $siteTreeID;

    /**
     * @since 6.0
     * @var int
     */
    private $itemsPerPage;

    /**
     * @since 6.0
     * @var int
     */
    private $requestedPageNumber;

    /**
     * @since 6.0
     * @var int
     */
    private $totalNumberOfItems = 0;

    /**
     * List of counts of items for each content type.
     *
     * @since 6.0
     * @var array
     */
    private $counts = array();

    /**
     * @since 6.0
     * @var array
     */
    private $indexOfPages = array( 1 => array() );

    /**
     * @since 6.0
     * @var int
     */
    private $numberOfPages;

    /**
     * @since 6.0
     *
     * @param object $plugin
     * @param int $site_tree_id
     * @param int $requested_page_number
     */
    public function __construct( $plugin, $site_tree_id, $requested_page_number ) {
        global $wpdb;

        $this->plugin              = $plugin;
        $this->db                  = $plugin->db();
        $this->wpdb                = $wpdb;
        $this->siteTreeID          = $site_tree_id;
        $this->itemsPerPage        = (int) $this->db->getOption( 'pagination_threshold' );
        $this->requestedPageNumber = $requested_page_number;

        if ( $this->itemsPerPage <= 0 ) {
            $this->itemsPerPage = 100;
        }       
    }

    /**
     * @since 6.0
     * @return int
     */
    public function getRequestedPageNumber() {
        return $this->requestedPageNumber;
    }

    /**
     * @since 6.0
     * @return bool
     */
    public function requestedPageExists() {
        return isset( $this->indexOfPages[$this->requestedPageNumber] );
    }

    /**
     * @since 6.0
     * @return array
     */
    public function getContentTypesForRequestedPage() {
        $content_types = array();
        $content_types_for_requested_page = $this->indexOfPages[$this->requestedPageNumber];

        foreach ( $content_types_for_requested_page as $content_type => $limits ) {
            $content_types[$content_type] = array(
                'limit'  => $limits[1] - $limits[0] + 1,
                'offset' => $limits[0] - 1
            );
        }

        return $content_types;
    }

    /**
     * @since 6.0
     * @return string
     */
    public function getNavigationMenu() {
        $navigation_menu = '';
        $number_of_pages = $this->getNumberOfPages();

        if ( $number_of_pages <= 1 ) {
            return '';
        }

        if ( $this->requestedPageNumber < self::MAX_NUMBER_OF_NAV_ITEMS ) {
            $first_item_number = 1;
            $last_item_number  = min( self::MAX_NUMBER_OF_NAV_ITEMS, $number_of_pages );
        }
        else {
            $offset            = (int)( ( self::MAX_NUMBER_OF_NAV_ITEMS - 1 ) / 2 );
            $last_item_number  = min( ( $this->requestedPageNumber + $offset ), $number_of_pages );
            $first_item_number = $last_item_number - self::MAX_NUMBER_OF_NAV_ITEMS + 1;
        }

        $navigation_menu .= '<nav id="site-tree-nav" role="navigation">';

        if ( $this->requestedPageNumber > 1 ) {
            $prev_page_number = $this->requestedPageNumber - 1;
            $navigation_menu .= '<a href="' . $this->plugin->sitemapURL( 'site_tree', '', $prev_page_number ) 
                              . '" id="site-tree-nav-prev" class="site-tree-nav-item">' 
                              . __( 'Previous', 'sitetree' ) . '</a> ';
        }

        for ( $i = $first_item_number; $i <= $last_item_number; $i++ ) {
            if ( $i == $this->requestedPageNumber ) {
                $navigation_menu .= '<span id="site-tree-nav-current-page-item" class="site-tree-nav-item" aria-current="page">';
                $navigation_menu .= $i . '</span> ';
            }
            else {
                $navigation_menu .= '<a href="' . $this->plugin->sitemapURL( 'site_tree', '', $i ) 
                                  . '" class="site-tree-nav-item">' . $i . '</a> ';
            }
        }

        if ( $this->requestedPageNumber < $number_of_pages ) {
            $next_page_number = $this->requestedPageNumber + 1;
            $navigation_menu .= ' <a href="' . $this->plugin->sitemapURL( 'site_tree', '', $next_page_number ) 
                              . '" id="site-tree-nav-next" class="site-tree-nav-item">' 
                              . __( 'Next', 'sitetree' ) . '</a>';
        }

        $navigation_menu .= '</nav>';

        return $navigation_menu;
    }

    /**
     * @since 6.0
     */
    public function buildIndexOfPages() {
        $index_of_pages = $this->db->getNonAutoloadOption( 'site_tree_index', array(), $this->siteTreeID );

        if ( isset( $index_of_pages[1] ) ) {
            $this->indexOfPages = &$index_of_pages;

            return true;
        }

        $content_types_dictionary = $this->db->getOption( 'site_tree_content_types' );

        if ( !is_array( $content_types_dictionary ) || empty( $content_types_dictionary ) ) {
            return false;
        }

        /**
         * @since 6.0
         */
        do_action( 'sitetree_paginator_will_build_index', $this );

        $this->countCustomPosts();
        $this->countCustomTaxononies();
        $this->countAuthorsPages();

        $page_number              = 1;
        $previous_end             = 0;
        $max_items_in_page        = $this->itemsPerPage;
        $min_items_can_start_page = ceil( $this->itemsPerPage * 0.15 );
        $max_items_can_end_page   = ceil( $this->itemsPerPage * 0.3 );

        reset( $content_types_dictionary );

        do {
            $content_type = key( $content_types_dictionary );

            if (! isset( $this->counts[$content_type] ) ) {
                continue;
            }

            $items_count = $this->counts[$content_type];

            if ( $max_items_in_page <= $max_items_can_end_page ) {
                $page_number      += 1;
                $max_items_in_page = $this->itemsPerPage;
            }

            while ( $items_count >= $max_items_in_page ) {
                $start             = $previous_end + 1;
                $end               = $previous_end + $max_items_in_page;
                $items_count      -= $max_items_in_page;
                $previous_end      = $end;
                $max_items_in_page = $this->itemsPerPage;

                $this->indexOfPages[$page_number++][$content_type] = array( $start, $end );
            }

            if ( $items_count > 0 ) {
                $end                                    = $previous_end + $items_count;
                $previous_page_number                   = $page_number - 1;
                $next_there_is_no_content               = ( next( $content_types_dictionary ) === false );
                $previous_page_has_current_content_type = isset( $this->indexOfPages[$previous_page_number][$content_type] );

                prev( $content_types_dictionary );

                if ( 
                    ( $next_there_is_no_content || $previous_page_has_current_content_type ) &&
                    !isset( $this->indexOfPages[$page_number] ) &&
                    ( $items_count <= $min_items_can_start_page )
                ) {
                    if ( $previous_page_has_current_content_type ) {
                        $this->indexOfPages[$previous_page_number][$content_type][1] = $end;
                    }
                    else {
                        $this->indexOfPages[$previous_page_number][$content_type] = array( 1, $end );
                    }

                    $max_items_in_page = $this->itemsPerPage;
                }
                else {
                    $start              = $previous_end + 1;
                    $max_items_in_page -= $items_count;

                    $this->indexOfPages[$page_number][$content_type] = array( $start, $end );
                }
            }

            $previous_end = 0;
        } while ( next( $content_types_dictionary ) );

        $this->db->setNonAutoloadOption( 'site_tree_index', $this->indexOfPages, $this->siteTreeID );

        return true;
    }

    /**
     * @since 6.0
     * @return int
     */
    public function getTotalNumberOfItems() {
        return $this->totalNumberOfItems;
    }

    /**
     * @since 6.0
     * @return int
     */
    public function getNumberOfPages() {
        if (! $this->numberOfPages ) {
            end( $this->indexOfPages );

            $this->numberOfPages = key( $this->indexOfPages );

            reset( $this->indexOfPages );
        }
        
        return $this->numberOfPages;
    }

    /**
     * @since 6.0
     * @return bool
     */
    private function countCustomPosts() {
        $post_types_list = '';
        $post_types      = get_post_types( array( 'public' => true ) );
        
        foreach ( $post_types as $post_type ) {
            if ( $this->db->getOption( $post_type, false, 'site_tree_content_types' ) ) {
                $post_types_list .= "'" . $post_type . "',";
            }
        }

        if (! $post_types_list ) {
            return false;
        }

        // Removes the trailing comma from the string.
        $post_types_list = substr( $post_types_list, 0, -1);

        $meta_keys  = $this->db->prepareMetaKey( 'exclude_from_site_tree' );
        $meta_keys .= ',';
        $meta_keys .= $this->db->prepareMetaKey( 'is_ghost_page' );

        $query_clauses = array(
            'SELECT'          => 'p.post_type AS content_type, COUNT( p.post_type ) AS count',
            'FROM'            => "{$this->wpdb->posts} AS p",
            'LEFT_OUTER_JOIN' => "{$this->wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key IN ({$meta_keys})",
            'WHERE'           => "p.post_type IN({$post_types_list}) AND p.post_status = 'publish' AND 
                                  p.post_password = '' AND pm.post_id IS NULL",
            'GROUP_BY'        => 'content_type'
        );

        /**
         * @since 6.0
         */
        $query_clauses = apply_filters( 'sitetree_paginator_posts_count_query', $query_clauses, $post_types_list );

        $counters = $this->db->getResults( $query_clauses );

        foreach ( $counters as $counter ) {
            $max_num_of_items = (int) $this->db->getOption( 'limit', -1, $counter->content_type, 'site_tree' );
            
            if ( $max_num_of_items > 0 ) {
                $counter->count = min( $counter->count, $max_num_of_items );
            }

            $this->updateCounts( $counter );
        }
    }

    /**
     * @since 6.0
     * @return bool
     */
    private function countCustomTaxononies() {
        $taxonomies_list = $excluded_ids = $term_not_in = '';
        $taxonomies      = get_taxonomies( array( 'public' => true ) );
        
        foreach ( $taxonomies as $taxonomy ) {
            if ( $this->db->getOption( $taxonomy, false, 'site_tree_content_types' ) ) {
                $taxonomies_list .= "'" . $taxonomy . "',";
                $ids              = $this->db->getOption( 'exclude', '', $taxonomy, 'site_tree' );
            
                if ( $ids ) {
                    $excluded_ids .= ',' . $ids;
                }
            }
        }

        if (! $taxonomies_list ) {
            return false;
        }

        // Removes the trailing comma from the string.
        $taxonomies_list = substr( $taxonomies_list, 0, -1);

        if ( $excluded_ids ) {
            $excluded_ids = implode( ',', wp_parse_id_list( $excluded_ids ) );
            $term_not_in  = 't.term_id NOT IN (' . $excluded_ids . ') AND';
        }

        $query_clauses = array(
            'SELECT'     => 'tt.taxonomy AS content_type, COUNT( DISTINCT t.slug ) AS count',
            'FROM'       => "{$this->wpdb->terms} AS t",
            'INNER_JOIN' => "{$this->wpdb->term_taxonomy} AS tt USING( term_id )
                             INNER JOIN {$this->wpdb->term_relationships} AS tr USING( term_taxonomy_id ) 
                             INNER JOIN {$this->wpdb->posts} AS p ON p.ID = tr.object_id",
            'WHERE'      => "{$term_not_in} tt.taxonomy IN ({$taxonomies_list}) AND p.post_status = 'publish'",
            'GROUP_BY'   => 'content_type'
        );

        /**
         * @since 6.0
         */
        $query_clauses = apply_filters( 'sitetree_paginator_taxonomies_count_query', $query_clauses, $taxonomies_list );

        $counters = $this->db->getResults( $query_clauses );

        foreach ( $counters as $counter ) {
            $this->updateCounts( $counter );
        }
    }

    /**
     * @since 6.0
     * @return bool
     */
    private function countAuthorsPages() {
        if (! $this->db->getOption( 'authors', false, 'site_tree_content_types' ) ) {
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

        $this->updateCounts( $counters[0] );
    }

    /**
     * @since 6.0
     * @param object $counter
     */
    private function updateCounts( $counter ) {
        $this->totalNumberOfItems            += $counter->count; 
        $this->counts[$counter->content_type] = $counter->count;
    }
}
?>