<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class Core extends BasePlugin {
    /**
     * @since 6.0
     * @var array
     */
    private $leaves = array();

    /**
     * @see parent::getInfoToRetrieve()
     * @since 6.0
     */
    protected function getInfoToRetrieve() {
        $info_to_retrieve                    = parent::getInfoToRetrieve();
        $info_to_retrieve['supportedLeaves'] = 'Supported Leaves';

        return $info_to_retrieve;
    }

    /**
     * @since 6.0
     *
     * @param object $leaf
     * @return bool|int
     */
    public function registerLeaf( $leaf ) {
        if ( ! ( is_object( $leaf ) && method_exists( $leaf, 'getLeafKeyword' ) ) ) {
            return false;
        }

        if ( is_string( $this->supportedLeaves ) ) {
            $support_data = explode( ',' , $this->supportedLeaves );

            if (! $support_data ) {
                return false;
            }

            $this->supportedLeaves = array();

            foreach ( $support_data as $string ) {
                if ( preg_match ( '/^\s*([a-z]+)\s+([.0-9]+)$/', $string, $matches ) ) {
                    $this->supportedLeaves[$matches[1]] = $matches[2];
                }
            }
        }

        $leaf_keyword = $leaf->getLeafKeyword();

        if ( isset( $this->supportedLeaves[$leaf_keyword] ) ) {
            if ( version_compare( $leaf->version(), $this->supportedLeaves[$leaf_keyword], '>=' ) ) {
                $this->leaves[$leaf_keyword] = $leaf;

                return true;
            }
            
            $message = sprintf( __( 'SiteTree %1$s no longer supports %2$s, please update your Leaf.', 'sitetree' ), 
                                $this->version(), ( $leaf->name() . ' ' . $leaf->version() ) );

            $this->registerAdminNoticeActionWithMessage( $message );
            
            return -1;
        }
        else {
            $message = sprintf( __( "%s has not been loaded, because SiteTree couldn't verify its compatibility.", 'sitetree' ), 
                                ( $leaf->name() . ' ' . $leaf->version() ) ); 
        }

        $this->registerAdminNoticeActionWithMessage( $message );

        return false;
    }

    /**
     * @since 6.0
     *
     * @param string $leaf_keyword
     * @return object|bool
     */
    public function getLeaf( $leaf_keyword ) {
        if ( isset( $this->leaves[$leaf_keyword] ) ) {
            return $this->leaves[$leaf_keyword];
        }

        return false;
    }

    /**
     * @since 6.0
     * @return array
     */
    public function getLeaves() {
        return $this->leaves;
    }

    /**
     * @see parent::finishLaunching()
     * @since 5.0
     */
    public function finishLaunching() {
        if (! $this->verifyWordPressCompatibility() ) {
            return false;
        }

        $this->initDB();

        if ( $this->isUninstalling ) {
            return true;
        }

        $this->load( 'library/functions.php' );

        $is_admin = is_admin();

        if ( $is_admin && wp_doing_ajax() ) {
            add_action( 'wp_ajax_handleSiteTreeAdminAjaxRequest', 
                        array( $this->invokeGlobalObject( 'AdminController' ), 'handleSiteTreeAdminAjaxRequest' ) );

            return true;
        }
        
        if ( !$is_admin && $this->isSitemapActive( 'sitemap' ) ) {
            add_filter( 'wp_sitemaps_enabled', '__return_false' ); 
        }
        
        add_action( 'init', array( $this, 'pluginDidFinishLaunching' ) );
        
        return true;
    }

    /**
     * @see parent::pluginDidFinishLaunching()
     * @since 5.0
     */
    public function pluginDidFinishLaunching() {
        $is_sitemap_active                = $this->isSitemapActive( 'sitemap' );
        $there_are_google_sitemaps_active = ( $is_sitemap_active || $this->isSitemapActive( 'newsmap' ) );

        $this->verifyVersionOfStoredData();

        if ( $there_are_google_sitemaps_active ) {
            global $wp;

            $wp->add_query_var( 'sitetree' );
            $wp->add_query_var( 'id' );

            $this->registerRewriteRules();
        }

        if ( is_admin() ) {
            add_action( 'wp_loaded', array( $this->invokeGlobalObject( 'AdminController' ), 'wpDidFinishLoading' ) );
        }
        elseif ( strpos( $_SERVER['REQUEST_URI'], '/wp-json/' ) === 0 ) {
            $adminController = $this->invokeGlobalObject( 'AdminController' );

            add_action( 'trashed_post', array( $adminController, 'wpDidTrashPost' ) );

            if ( $is_sitemap_active ) {
                add_action( 'save_post', array( $adminController, 'wpDidSavePost' ), 10, 2 );
            }
        }
        else {
            $this->load( 'includes/core-delegate.class.php' );
            
            $sitetreeDelegate = new CoreDelegate( $this );
            
            add_action( 'wp', array( $sitetreeDelegate, 'listenToPageRequest' ), 5 );
            add_shortcode( 'sitetree', array( $this->invokeGlobalObject( 'HyperlistController' ), 'doShortcode' ) );

            if ( $there_are_google_sitemaps_active ) {
                add_filter( 'wp_headers', array( $sitetreeDelegate, 'wpWillSendHeaders' ), 10, 2 );  
            }
        }
        
        return true;
    }
    
    /**
     * Verifies that the data stored into the database are compatible with 
     * this version of the plugin and if needed invokes the upgrader.
     *
     * @since 5.0
     * @return bool
     */
    private function verifyVersionOfStoredData() {
        $current_version = $this->db->getOption( 'version' );
        
        if ( $current_version === $this->version ) {
            return true;
        }

        if ( $current_version ) {
            $this->load( 'library/plugin-upgrader.class.php' );
            $this->load( 'includes/upgrader.class.php' );

            $upgrader = new Upgrader( $this );
            $upgrader->upgrade( $current_version );
        }

        $now = time();

        if ( !$current_version || version_compare( $current_version, '1.5.3', '<=' ) ) {
            $this->db->setOption( 'installed_on', $now );
        }

        $this->db->setOption( 'last_updated', $now );
        $this->db->setOption( 'version', $this->version );
        
        return true;
    }

    /**
     * @since 5.0
     * @return bool|int
     */
    public function registerRewriteRules() {
        add_action( 'generate_rewrite_rules', array( $this, 'wpRewriteDidGenerateRules' ) );
    }

    /**
     * @since 5.0
     * @param object $wp_rewrite
     */
    public function wpRewriteDidGenerateRules( $wp_rewrite ) {
        $sitetree_rules = array(
            '^(sitemap|newsmap)-template\.xsl$'       => 'index.php?sitetree=$matches[1]&id=stylesheet',
            '^(sitemap|newsmap)-index-template\.xsl$' => 'index.php?sitetree=$matches[1]&id=index-stylesheet'
        );

        if ( $this->isSitemapActive( 'newsmap' ) ) {
            $sitetree_rules['^news-sitemap\.xml$']                         = 'index.php?sitetree=newsmap&id=index';
            $sitetree_rules['^([_a-z]+)-news-sitemap(?:-([0-9]+))?\.xml$'] = 'index.php?sitetree=newsmap&id=$matches[1]&paged=$matches[2]';
        }

        if ( $this->isSitemapActive( 'sitemap' ) ) {
            $sitemap_filename = $this->getSitemapFilename();
            
            $sitetree_rules["^{$sitemap_filename}\.xml$"]                         = 'index.php?sitetree=sitemap&id=index';
            $sitetree_rules["^([_a-z]+)-{$sitemap_filename}(?:-([0-9]+))?\.xml$"] = 'index.php?sitetree=sitemap&id=$matches[1]&paged=$matches[2]';
        }

        /**
         * @since 5.0
         */
        $sitetree_rules = apply_filters( 'sitetree_did_generate_rewrite_rules', $sitetree_rules, $this );

        $wp_rewrite->rules = $sitetree_rules + $wp_rewrite->rules;
    }

    /**
     * @since 5.1
     * @return string
     */
    public function getSitemapFilename() {
        $filename = sanitize_key( $this->db->getOption( 'sitemap_filename', 'sitemap' ) );

        return ( $filename ? $filename : 'sitemap' );
    }

    /**
     * @since 5.0
     *
     * @param string $sitemap_id
     * @return bool
     */
    public function isSitemapActive( $sitemap_id ) {
        if ( $sitemap_id == 'site_tree' ) {
            return (bool) $this->db->getOption( 'page_for_site_tree' );    
        }

        return (bool) $this->db->getOption( $sitemap_id, false, 'is_sitemap_active' );
    }

    /**
     * @since 5.0
     *
     * @param string $sitemap_slug
     * @param string $sitemap_id
     * @param int $sitemap_number
     * @return string
     */
    public function sitemapURL( $sitemap_slug, $sitemap_id = '', $sitemap_number = 0 ) {
        global $wp_rewrite;

        switch ( $sitemap_slug ) {
            case 'sitemap':
            case 'newsmap':
                if ( $wp_rewrite->using_permalinks() ) {
                    if (! $sitemap_id ) {
                        if ( $sitemap_slug == 'sitemap' ) {
                            $relative_url = '/' . $this->getSitemapFilename() . '.xml';

                            return home_url( $relative_url );
                        }
                        
                        return home_url( '/news-sitemap.xml' );
                    }

                    $relative_url = '/' . $sitemap_id;

                    if ( $sitemap_slug == 'sitemap' ) {
                        $relative_url .= '-' . $this->getSitemapFilename();
                    }
                    else {
                        $relative_url .= '-news-sitemap';
                    }

                    if ( $sitemap_number > 1 ) {
                        $relative_url .= '-' . $sitemap_number;
                    }

                    $relative_url .= '.xml';

                    return home_url( $relative_url );
                }

                $arguments = array( 'sitetree' => $sitemap_slug );

                if ( $sitemap_id ) {
                    $arguments['id'] = $sitemap_id;
                }

                if ( $sitemap_number > 1 ) {
                    $arguments['paged'] = $sitemap_number;
                }
                
                return add_query_arg( $arguments, home_url( '/' ) );

            case 'site_tree':
                $permalink = get_permalink( $this->db->getOption( 'page_for_site_tree' ) );
                
                if ( $sitemap_number > 1 ) {
                    if ( $wp_rewrite->using_permalinks() ) {
                        $permalink .= 'page/' . $sitemap_number . '/';
                    }
                    else {
                        return add_query_arg( 'paged', $sitemap_number, $permalink );
                    }
                }

                return $permalink;
        }

        return '';
    }

    /**
     * @since 5.1.1
     *
     * @param string $content_type
     * @param string $sitemap_slug
     * @param bool $default
     * @return bool
     */
    public function isContentTypeIncluded( $content_type, $sitemap_slug, $default = false ) {
        $option_key_group = $sitemap_slug . '_content_types';

        return (bool) $this->db->getOption( $content_type, $default, $option_key_group );
    }

    /**
     * @since 5.0
     * @return bool
     */
    public function isWebsiteLocal() {
        if ( WP_DEBUG ) {
            return false;
        }

        $site_url = site_url();

        if ( strpos( $site_url, '.' ) === false ) {
            return true;
        }
        
        $known_local_patterns = array(
            '#\.local$#i',
            '#\.localhost$#i',
            '#\.test$#i',
            '#\.staging$#i',     
            '#\.stage$#i',
            '#^dev\.#i',
            '#^stage\.#i',
            '#^staging\.#i',
        );

        $host = parse_url( $site_url, PHP_URL_HOST );

        foreach( $known_local_patterns as $pattern ) {
            if ( preg_match( $pattern, $host ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * @since 5.0
     * @param string $sitemap_slug
     */
    public function flushCachedData( $sitemap_slug ) {
        $sitemap_index_key = $sitemap_slug . '_index';

        switch ( $sitemap_slug ) {
            case 'site_tree':
                if ( defined( 'WP_CACHE' ) && WP_CACHE && function_exists( 'wpsc_delete_url_cache' ) ) {
                    $index_of_pages = (array) $this->db->getNonAutoloadOption( $sitemap_index_key, 
                                                                               array(), 
                                                                               (int) $this->db->getOption( 'page_for_site_tree', 0 ) );
                    
                    reset( $index_of_pages );

                    do {
                        $page_number = key( $index_of_pages );

                        wpsc_delete_url_cache( $this->sitemapURL( $sitemap_slug, '', $page_number ) );
                    } while( next( $index_of_pages ) );
                }
                break;

            case 'advanced':
                $sitemap_slug = 'sitemap';
                break;
        }
        
        $this->db->deleteNonAutoloadOption( $sitemap_index_key );
        $this->db->setNonAutoloadOption( 'metrics', false, 'metrics_are_fresh', $sitemap_slug );

        /**
         * @since 5.0
         */
        do_action( 'sitetree_did_flush_cached_data', $sitemap_slug );
    }
}
?>