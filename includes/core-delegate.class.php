<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class CoreDelegate {
    /**
     * @since 5.0
     * @var object
     */
    private $plugin;

    /**
     * @since 5.0
     * @var object
     */
    private $db;

    /**
     * @since 6.0
     * @var object
     */
    private $indexer;

    /**
     * @since 6.0
     * @var object
     */
    private $paginator;

    /**
     * Slug of the Google Sitemap to serve.
     *
     * @since 6.0
     * @var string
     */
    private $requestedSitemapSlug = '';

    /**
     * ID of the Google Sitemap to serve.
     *
     * @since 6.0
     * @var string
     */
    private $requestedSitemapID;

    /**
     * @since 6.0
     * @var int
     */
    private $requestedSitemapNumber;  

    /**
     * @since 6.0
     * @var int
     */
    private $requestedPageNumber;  

    /**
     * @since 5.0
     * @param object $plugin
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        $this->db     = $plugin->db();
    }

    /**
     * @since 5.0
     *
     * @param object $wp
     * @return bool Always true.
     */
    public function listenToPageRequest( $wp ) {
        global $wp_query;

        $page_for_site_tree = (int) $this->db->getOption( 'page_for_site_tree', 0 );
        
        if ( ( $page_for_site_tree > 0 ) && $wp_query->is_page() ) {
            $requested_page_id = ( isset( $wp_query->queried_object ) ? $wp_query->queried_object->ID : 0 );

            /**
             * @since 5.3
             */
            $can_filter = apply_filters( 'sitetree_can_filter_page_content', 
                                         $wp_query->is_page( $page_for_site_tree ), $page_for_site_tree, $requested_page_id );
            if ( $can_filter ) {
                $this->plugin->load( 'includes/paginator.class.php' );

                $raw_page_number           = $wp_query->get( 'paged' );
                $this->requestedPageNumber = ( $raw_page_number > 1 ) ? $raw_page_number : 1;

                $this->paginator = new Paginator( $this->plugin, $requested_page_id, $this->requestedPageNumber );
                $this->paginator->buildIndexOfPages();

                if ( ( $raw_page_number === 1 ) || !$this->paginator->requestedPageExists() ) {
                    wp_redirect( $this->plugin->sitemapURL( 'site_tree' ), 301 );

                    exit;
                }

                if ( $this->paginator->getNumberOfPages() > 1 ) {
                    remove_action( 'wp_head', 'rel_canonical' );
                    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
                }

                // A priority of 11 registers the method just after the wp_autop() function has run.
                add_filter( 'the_content', array( $this, 'wpWillDisplayPageContent' ), 11 );

                return true;
            } 
        }

        if (! $this->plugin->isSitemapActive( 'sitemap' ) ) {
            return false;
        }

        if ( $wp_query->is_page() && $this->db->getPostMeta( $wp_query->get_queried_object_id(), 'is_ghost_page' ) ) {
            header( 'X-Robots-Tag: noindex, nofollow' );

            // For the WP Super Cache plugin.
            if (! defined( 'DONOTCACHEPAGE' ) ) {
                define( 'DONOTCACHEPAGE', true );
            }
        }
        elseif ( $wp_query->is_robots() ) {
            $this->plugin->load( 'includes/robots-delegate.class.php' );

            $robotsDelegate = new RobotsDelegate( $this->plugin );

            add_filter( 'robots_txt', array( $robotsDelegate, 'wpDidGenerateRobotsFileContent' ), 50, 2 );
        }

        return true;
    }

    /**
     * @since 5.0
     *
     * @param array $headers
     * @param object $wp
     * @return array
     */
    public function wpWillSendHeaders( $headers, $wp ) {
        $this->requestedSitemapSlug = ( isset( $wp->query_vars['sitetree'] ) ? $wp->query_vars['sitetree'] : '' );
        
        if ( 
            $this->requestedSitemapSlug &&
            ( ( $this->requestedSitemapSlug == 'sitemap' ) || ( $this->requestedSitemapSlug == 'newsmap' ) )
        ) {
            $this->requestedSitemapID = ( isset( $wp->query_vars['id'] ) ? $wp->query_vars['id'] : '' );

            if (! $this->requestedSitemapID ) {
                wp_redirect( $this->plugin->sitemapURL( $this->requestedSitemapSlug ), 301 );

                exit;
            }

            $this->plugin->load( 'includes/indexer.class.php' );

            $this->indexer = new Indexer( $this->plugin, $this->requestedSitemapSlug, $this->requestedSitemapID );

            switch ( $this->requestedSitemapID ) {
                case 'stylesheet':
                case 'index-stylesheet':
                    $this->plugin->load( 'includes/builders/stylesheet-builder.class.php' );

                    /**
                     * @since 6.0
                     */
                    do_action( "sitetree_will_serve_{$this->requestedSitemapID}", $this->requestedSitemapSlug );

                    $stylesheetBuilder = new StylesheetBuilder( $this->plugin, $this->requestedSitemapSlug );
                    $headers           = array( 'Content-Type' => 'text/xsl; charset=UTF-8' );
               
                    if ( $this->requestedSitemapID == 'stylesheet' ) {
                        $template_redirect_callback = array( $stylesheetBuilder, 'serveStylesheet' );

                        $this->indexer->buildIndex();
                        $stylesheetBuilder->setSitemapIsPartOfCollection( $this->indexer->getTotalNumberOfSitemaps() > 1 );
                    }
                    else {
                        $template_redirect_callback = array( $stylesheetBuilder, 'serveIndexStylesheet' );
                    }
                    break;

                default:
                    global $wp_rewrite;

                    // If the sitemap is requested via query variable and a permalink
                    // structure is in place, it redirects the request to the sitemap's permalink.
                    if ( !$wp->did_permalink && $wp_rewrite->using_permalinks() ) {
                        wp_redirect( $this->plugin->sitemapURL( $this->requestedSitemapSlug ), 301 );

                        exit;
                    }

                    $template_redirect_callback = array( $this, 'serveSingleSitemap' );

                    if ( isset( $wp->query_vars['paged'] ) && ( $wp->query_vars['paged'] > 0 ) ) {
                        $this->requestedSitemapNumber = (int) $wp->query_vars['paged'];
                    }
                    else {
                        $this->requestedSitemapNumber = 0;
                    }

                    $this->indexer->setRequestedSitemapNumber( $this->requestedSitemapNumber );

                    if ( ( $this->requestedSitemapID == 'index' ) && ( $this->requestedSitemapNumber === 0 ) ) {
                        $this->indexer->buildIndex();

                        if ( $this->indexer->getTotalNumberOfSitemaps() > 1 ) {
                            $template_redirect_callback = array( $this, 'serveSitemapIndex' );  
                        }
                    }
                    elseif ( $this->indexer->sitemapIDisValid() ) {
                        if ( $this->requestedSitemapNumber === 1 ) {
                            wp_redirect( $this->plugin->sitemapURL( $this->requestedSitemapSlug, $this->requestedSitemapID ), 301 );

                            exit;
                        }

                        if (! $this->indexer->requestedSitemapExists() ) {
                            header( 'HTTP/1.0 404 Not Found' );
                            
                            exit;
                        }
                    }
                    else {
                        header( 'HTTP/1.0 404 Not Found' );
                            
                        exit;
                    }

                    $last_modified = gmdate( 'D, d M Y H:i:s', time() ) . ' GMT';
                    $headers       = array(
                        'Content-Type'  => 'application/xml; charset=UTF-8',
                        'Last-Modified' => $last_modified,
                        'Cache-Control' => 'no-cache'
                    );
                    break;
            }

            add_action( 'template_redirect', $template_redirect_callback );
            remove_filter( 'template_redirect', 'redirect_canonical' );
        }
        
        return $headers;
    }

    /**
     * @since 6.0
     */
    public function serveSitemapIndex() {
        // For the WP Super Cache plugin.
        define( 'DONOTCACHEPAGE', true );

        $linebreak      = ( WP_DEBUG ? "\n" : '' );
        $plugin_version = $this->plugin->version();
        $index          = $this->indexer->getIndexOfSitemaps();

        $markup = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<?xml-stylesheet type="text/xsl" href="' . home_url( "/{$this->requestedSitemapSlug}-index-template.xsl" ) 
                . '?ver=' . $plugin_version . '"?>' . "\n"
                . '<!-- Sitemap Index generated by SiteTree ' . $plugin_version . ' (' . $this->plugin->pluginURI() . ") -->\n"
                . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ( $index as $sitemap_id => $number_of_sitemaps ) {
            for ( $i = 1; $i <= $number_of_sitemaps; $i++ ) {
                $markup .= '<sitemap>' . $linebreak
                         . '<loc>' . $this->plugin->sitemapURL( $this->requestedSitemapSlug, $sitemap_id, $i ) . '</loc>' . $linebreak
                         . '</sitemap>' . $linebreak;
            }
        }

        $markup .= '</sitemapindex>';

        $this->updateMetrics();

        exit( $markup );
    }

    /**
     * @since 6.0
     */
    public function serveSingleSitemap() {
        // For the WP Super Cache plugin.
        define( 'DONOTCACHEPAGE', true );

        $this->plugin->load( 'includes/builders/builder-core.class.php' );
        $this->plugin->load( 'includes/builders/google-sitemap-builder.class.php' );

        /**
         * @since 5.3
         */
        do_action( 'sitetree_will_serve_sitemap', $this->requestedSitemapSlug );

        switch ( $this->requestedSitemapSlug ) {
            case 'sitemap':
                $this->plugin->load( 'includes/builders/builders-interfaces.php' );
                $this->plugin->load( 'includes/builders/sitemap-builder.class.php' );
                $this->plugin->load( 'includes/builders/image-element.class.php' );

                $builder     = new SitemapBuilder( $this->plugin, $this->indexer );
                $extra_xmlns = 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';

                /**
                 * @since 5.3
                 */
                $extra_xmlns = apply_filters( 'sitetree_extra_xmlns_namespaces', $extra_xmlns );
                break;

            case 'newsmap':
                $this->plugin->load( 'includes/builders/newsmap-builder.class.php' );

                $builder     = new NewsmapBuilder( $this->plugin, $this->indexer );
                $extra_xmlns = 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"';
                break;

            default:
                return false;
        }

        $sitemap = $builder->build();

        $this->updateMetrics( $builder );

        $plugin_version  = $this->plugin->version();
        $stylesheet_name = $this->requestedSitemapSlug . '-template.xsl';

        exit( '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?xml-stylesheet type="text/xsl" href="' . home_url( $stylesheet_name ) 
            . '?ver=' . $plugin_version . '"?>' . "\n"
            . '<!-- Sitemap generated by SiteTree ' . $plugin_version
            . ' (' . $this->plugin->pluginURI() . ") -->\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ' . $extra_xmlns 
            . '>' . $sitemap . '</urlset>' );
    }
    
    /**
     * Appends the Site Tree to the content of the page where the Site Tree must be shown.
     *
     * This method is hooked into the the_content filter hook.
     *
     * @since 5.0
     *
     * @param string $the_content
     * @return string
     */
    public function &wpWillDisplayPageContent( $the_content ) {
        if ( in_the_loop() ) {
            $builder = $this->plugin->invokeGlobalObject( 'SiteTreeBuilder' );
            $builder->setPaginator( $this->paginator );
            
            $the_content .= "<!-- Site Tree start -->\n";
            $the_content .= $builder->build();
            $the_content .= "<!-- Site Tree end -->\n";

            $this->updateMetrics( $builder );

            remove_filter( 'the_content', array( $this, 'wpWillDisplayPageContent' ), 11 );
        }

        return $the_content;
    }

    /**
     * @since 6.0
     * 
     * @param objetc $builder
     * @return bool
     */
    private function updateMetrics( $builder = null ) {
        if ( $this->requestedSitemapSlug ) {
            $sitemap_slug = $this->requestedSitemapSlug;
        }
        else {
            $sitemap_slug = ( is_object( $builder ) ? $builder->sitemapSlug() : '' );
        }
        
        $metrics = (array) $this->db->getNonAutoloadOption( 'metrics', array(), $sitemap_slug );

        switch ( $sitemap_slug ) {
            case 'sitemap':
            case 'newsmap':
                $sitemap_uid      = $this->indexer->getRequestedSitemapUID();
                $sitemap_is_index = ( $builder === null );

                if ( $this->db->getNonAutoloadOption( 'metrics', false, 'metrics_are_fresh', $sitemap_slug ) ) {
                    if ( $sitemap_is_index || isset( $metrics['metrics_per_document']['num_queries'][$sitemap_uid] ) ) {
                        return false;
                    }
                }

                if ( $this->indexer->hasIndexJustBeenBuilt() ) {
                    $metrics['tot_sitemaps'] = $this->indexer->getTotalNumberOfSitemaps();
                    $metrics['tot_items']    = $this->indexer->getTotalNumberOfPermalinks();

                    if ( $sitemap_slug == 'sitemap' ) {
                        $metrics['tot_images'] = $this->countTotalNumberOfImages();
                    }
                }

                if (! $sitemap_is_index ) {
                    $new_metrics = $builder->getMetrics();

                    $metrics['num_queries'] = $new_metrics['num_queries'];
                    $metrics['runtime']     = $new_metrics['runtime'];
                    $metrics['metrics_per_document']['runtime'][$sitemap_uid]     = $new_metrics['runtime'];
                    $metrics['metrics_per_document']['num_queries'][$sitemap_uid] = $new_metrics['num_queries'];
                    
                    if ( isset( $metrics['tot_sitemaps'] ) && ( $metrics['tot_sitemaps'] > 1 ) ) {
                        $this->computeMetricsAverageValues( $metrics );
                    }
                    else {
                        unset( $metrics['avg_num_queries'], $metrics['avg_runtime'] );
                    }
                }
                break;

            case 'site_tree':
                if ( $this->db->getNonAutoloadOption( 'metrics', false, 'metrics_are_fresh', $sitemap_slug ) ) {
                    if ( isset( $metrics['metrics_per_document']['num_queries'][$this->requestedPageNumber] ) ) {
                        return false;
                    }
                }

                $new_metrics = $builder->getMetrics();
                $tot_items = $this->paginator->getTotalNumberOfItems();
                
                // If $tot_items > 0 it means that the index has just been built.
                if ( $tot_items > 0 ) {
                    $metrics['tot_pages'] = $this->paginator->getNumberOfPages();
                    $metrics['tot_items'] = $tot_items;
                }

                $metrics['num_queries'] = $new_metrics['num_queries'];
                $metrics['runtime']     = $new_metrics['runtime'];
                $metrics['metrics_per_document']['runtime'][$this->requestedPageNumber]     = $new_metrics['runtime'];
                $metrics['metrics_per_document']['num_queries'][$this->requestedPageNumber] = $new_metrics['num_queries'];
                
                if ( $metrics['tot_pages'] > 1 ) {
                    $this->computeMetricsAverageValues( $metrics );
                }
                else {
                    unset( $metrics['avg_num_queries'], $metrics['avg_runtime'] );
                }
                break;

            default:
                return false;
        }

        $metrics['metrics_computed_on'] = time();
        $metrics['metrics_are_fresh']   = true;

        $this->db->setNonAutoloadOption( 'metrics', $metrics, $sitemap_slug );

        return true;
    }

    /**
     * @since 6.0
     * @return int
     */
    private function countTotalNumberOfImages() {
        global $wpdb;

        $post_types_list = $this->indexer->getPostTypesList();

        if (! $post_types_list ) {
            return -1;
        }

        $meta_keys  = $this->db->prepareMetaKey( 'exclude_from_sitemap' );
        $meta_keys .= ',';
        $meta_keys .= $this->db->prepareMetaKey( 'is_ghost_page' );

        $results = $wpdb->get_results(
            "SELECT COUNT( ID ) AS count
             FROM {$wpdb->posts}
             WHERE post_parent IN (
                SELECT p_temp.ID
                FROM {$wpdb->posts} AS p_temp 
                LEFT OUTER JOIN {$wpdb->postmeta} AS pm_temp
                    ON p_temp.ID = pm_temp.post_id AND pm_temp.meta_key IN ({$meta_keys})
                WHERE p_temp.post_type IN({$post_types_list}) AND 
                      p_temp.post_status = 'publish' AND p_temp.post_password = '' AND pm_temp.post_id IS NULL
             ) AND post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );

        return $results[0]->count;
    }

    /**
     * @since 6.0
     * @param array $metrics
     */
    private function computeMetricsAverageValues( &$metrics ) {
        $metrics_per_document = $metrics['metrics_per_document'];

        foreach( $metrics_per_document as $key => $metric_values ) {
            $sum     = $num_values = 0;
            $avg_key = 'avg_' . $key;

            foreach ( $metric_values as $metric_value ) {
                $num_values += 1;
                $sum        += $metric_value;
            }

            if ( $sum == (int) $sum ) {
                $metrics[$avg_key] = ceil( $sum / $num_values );
            }
            else {
                $metrics[$avg_key] = round( ( $sum / $num_values ), 3 );
            }

            unset( $metrics[$key] );
        }
    }
}
?>