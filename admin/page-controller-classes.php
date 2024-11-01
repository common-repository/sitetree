<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
class PageController implements PageViewDelegateProtocol {
    /**
     * @since 5.0
     * @var object
     */
    protected $plugin;

    /**
     * @since 5.0
     * @var object
     */
    protected $db;

    /**
     * @since 5.0
     * @var object
     */
    protected $dataController;
    
    /**
     * @since 5.0
     * @var object
     */
    protected $page;
    
    /**
     * @since 5.0
     *
     * @param object $page
     * @param object $plugin
     * @param object $dataController
     * @return object
     */
    public static function makeController( $page, $plugin, $dataController = null ) {
        $base_class       = __CLASS__;
        $controller_class = __NAMESPACE__ . '\\' . $page->controllerClass();

        $controller                 = new $controller_class;
        $controller->page           = $page;
        $controller->plugin         = $plugin;
        $controller->db             = $plugin->db();
        $controller->dataController = $dataController;

        if ( $controller instanceof $base_class ) {
            return $controller;
        }

        $message = __METHOD__ . '() cannot create objects of class ' . $controller_class;
        
        trigger_error( $message, E_USER_ERROR );
    }

    /**
     * @since 5.0
     */
    protected function __construct() {}

    /**
     * @since 5.0
     * @return object
     */
    public function getPage() {
        return $this->page;
    }

    /**
     * @since 5.0
     * @return object
     */
    public function loadPageView() {
        $sections = $this->dataController->loadPageSections( $this->page->id() );
        
        $pageView = PageView::makeView( $this->page );
        $pageView->setSections( $sections );
        $pageView->setDelegate( $this );

        return $pageView;
    }

    /**
     * @since 5.0
     *
     * @param string $action
     * @return bool
     */
    public function performUserAction( $action ) {
        if ( $action != 'update_settings' ) {
            return false;
        }

        $page_id     = $this->page->id();
        $raw_options = isset( $_POST['sitetree'] ) ? $_POST['sitetree'] : array();
		$options     = $this->dataController->sanitiseOptions( $raw_options, $this->page );
        
        switch ( $page_id ) {
        	case 'site_tree':
        		$notice_text = __( 'Settings saved. %sView Site Tree%s', 'sitetree' );
                $link_opening_tag = '<a href="' . $this->plugin->sitemapURL( $page_id ) . '" target="sitetree_admin">';

                $this->registerNotice( sprintf( $notice_text, $link_opening_tag, '</a>' ) );
	            break;

	        default:
                $this->registerNotice( __( 'Settings saved.', 'sitetree' ) );
	        	break;
        }
        
        $this->db->setOptions( $options );
        $this->plugin->flushCachedData( $page_id );

        return true;
    }
    
    /**
     * @since 5.0
     *
     * @param string $notice
     * @param string $type
     */
    protected function registerNotice( $notice, $type = 'success' ) {
        $data = array(
            'message' => $notice,
            'type'    => $type
        );

        set_transient( 'sitetree_admin_notice', $data, 30 );
    }

    /**
     * @since 5.0
     */
    protected function displayNotice() {
        $notice = get_transient( 'sitetree_admin_notice' );

        if ( $notice && is_array( $notice ) ) {
            add_settings_error( 'sitetree', 'sitetree', $notice['message'], $notice['type'] );
            settings_errors( 'sitetree' );
            delete_transient( 'sitetree_admin_notice' );
        }
    }

    /**
     * @since 5.0
     *
     * @param array $query_arguments
     * @param string $page_id
     * @return string
     */
    public function pageURL( $query_arguments = array(), $page_id = '' ) {
        if (! $page_id ) {
            $page = $this->page;
        }
        else {
            $page = $this->dataController->page( $page_id );

            if (! $page ) {
                return '';
            }
        }

        $arguments = array( 'page' => $page->namespacedID() );
        
        if ( $query_arguments ) {
            $arguments += $query_arguments;
            
            if ( isset( $arguments['action'] ) ) {
                $arguments['sitetree_nonce'] = wp_create_nonce( $arguments['action'] );
            }
        } 


        return add_query_arg( $arguments, admin_url( $page->parentSlug() ) );
    }
    
    /**
     * {@inheritdoc}
     */
    public function pageViewWillDisplayForm( $pageView ) {
        $this->displayNotice();
    }
    
    /**
     * {@inheritdoc}
     */
    public function pageViewFormAction( $pageView ) { 
    	return 'update_settings';
    }
    
    /**
     * {@inheritdoc}
     */
    public function pageViewFieldValue( $field, $section_id ) {
    	$context = ( $this->page->id() == 'site_tree' ) ? $this->page->id() : '';
        $value   = $this->db->getOption( $field->id(), $field->defaultValue(), $section_id, $context );
        $filter  = new SiteTreeOptionsFilter( $value, $field );
        
        return $filter->filterOption();
    }
}


/**
 * @since 5.0
 */
final class DashboardController
    extends PageController
 implements DashboardDelegateProtocol {
    /**
     * @since 5.0
     */
    private $configMode;

    /**
     * @since 5.0
     */
    private $showLicenceKeyErrorMsg;

    /**
     * @since 5.0
     */
    protected function __construct() {
        if ( isset( $_GET['config'] ) ) {
    		$this->configMode = sanitize_key( $_GET['config'] );
    	}
    }

    /**
     * @see parent::performUserAction()
     * @since 5.0
     */
    public function performUserAction( $action ) {
        switch ( $action ) {
            case 'send_pings':
                if (! isset( $_GET['sitemap_id'] ) ) {
                    return false;
                }

                if ( $this->plugin->isWebsiteLocal() ) {
                    return false;
                }

                $sitemap_id = sanitize_key( $_GET['sitemap_id'] );
                
                $this->plugin->invokeGlobalObject( 'PingController' )->ping( $sitemap_id );
                break;

            case 'configure':
            	if (! $this->doConfigureAction() ) {
            		return false;
            	}
            	break;
            
            case 'deactivate':
                if (! isset( $_POST['sitetree_form_id'] ) ) {
                    return false;
                }

                $form_id = sanitize_key( $_POST['sitetree_form_id'] );

                switch( $form_id ) {
                    case 'sitemap':
                    case 'newsmap':
                        $this->db->setOption( $form_id, false, 'is_sitemap_active' );
                        flush_rewrite_rules( false );      
                        break;

                    case 'site_tree':
                        $this->db->setOption( 'page_for_site_tree', 0 );
                        break;

                    default:
                        return false;
                }

                $this->plugin->flushCachedData( $form_id );
                break;
            
            default:
                return false;
        }

        return true;
    }

    /**
     * @since 5.0
     * @return bool
     */
    public function doConfigureAction() {
        if (! isset( $_POST['sitetree_form_id'] ) ) {
            return false;
        }
        
        $raw_options       = isset( $_POST['sitetree'] ) ? $_POST['sitetree'] : array();
        $form_id           = sanitize_key( $_POST['sitetree_form_id'] );
        $sanitised_options = $this->dataController->sanitiseOptions( $raw_options, $this->page, $form_id );
        $config_options    = $sanitised_options[$form_id];
        $sitemap_active    = $this->plugin->isSitemapActive( $form_id );

        $sitemap_filename_has_changed = false;

        switch ( $form_id ) {
            case 'site_tree':
                $old_site_tree_id = (int) $this->db->getOption( 'page_for_site_tree' );
                break;

            case 'sitemap':
                $old_sitemap_filename = $this->db->getOption( 'sitemap_filename' );
                break;
        }
        
        $content_types_id = $form_id . '_content_types';
        $content_flags    = $config_options[$content_types_id];
        $at_least_one_content_type_is_included = false;

        foreach ( $content_flags as $content_type_included ) {
            if ( $content_type_included ) {
                $at_least_one_content_type_is_included = true;

                break;
            }
        }

        if (! $at_least_one_content_type_is_included ) {
            if ( $form_id === 'newsmap' ) {
                $config_options[$content_types_id]['post'] = true;
            }
            else {
                $config_options[$content_types_id]['page'] = true;
            }
        }

        if (
            ( !$this->db->setOptions( $config_options ) && $sitemap_active ) ||
            ( !$sitemap_active && isset( $_POST['save_order'] ) )            
        ) {
            return true;
        }

        if ( $sitemap_active ) {
            $this->plugin->flushCachedData( $form_id );
        }
        
        switch ( $form_id ) {
            case 'site_tree':
                $content_options     = array();
                $old_content_options = $this->db->getOption( $form_id );
                $defaults            = $this->dataController->defaultsForPage( $form_id );

                if ( is_array( $old_content_options ) ) {
                    $content_options[$form_id] = array_merge( $defaults[$form_id], $old_content_options );
                }
                else {
                    $content_options[$form_id] = $defaults[$form_id];
                }

                $this->db->setOptions( $content_options );

                $site_tree_id = $config_options['page_for_site_tree'];

                if ( $site_tree_id != $old_site_tree_id ) {
                    if ( $old_site_tree_id > 0 ) {
                        $this->db->deletePostMeta( $old_site_tree_id, 'exclude_from_site_tree' );
                    }

                    if ( $site_tree_id > 0 ) {
                        $this->db->setPostMeta( $site_tree_id, 'exclude_from_site_tree', true );
                    }
                }
                break;

            case 'sitemap':
                $sitemap_filename_has_changed = ( $config_options['sitemap_filename'] != $old_sitemap_filename );
                // Break omitted.

            case 'newsmap':   
                if ( !$sitemap_active || $sitemap_filename_has_changed ){
                    $this->db->setOption( $form_id, true, 'is_sitemap_active' );
                    $this->plugin->registerRewriteRules();

                    flush_rewrite_rules( false );
                }
                break;

            default:
                return false;
        }

        if ( $this->configMode ) {
            $message = __( 'Configuration saved.', 'sitetree' );

            if ( $sitemap_filename_has_changed ) {
                $link_opening_tag = '<a href="https://search.google.com/search-console/about">';

                $message .= ' ';
                $message .= __( 'Please note that as you changed the filename of the Google Sitemap, you shall re-submit its URL on %1$sthe Google Search Console%2$s.', 'sitetree' );
                $message = sprintf( $message, $link_opening_tag, '</a>' );
            }
            
            $this->registerNotice( $message );     
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function pageViewFormAction( $pageView ) {
    	$form_id = $pageView->formID();

    	if ( $this->plugin->isSitemapActive( $form_id ) && ( $this->configMode != $form_id ) ) {
            return 'deactivate';
        }

        return 'configure';
    }

    /**
     * {@inheritdoc}
     */
    public function dashboardWillDisplayToolbarButtons( $dashboardPageView, $form_id ) {
        $config = array();
        
        if ( $this->plugin->isSitemapActive( $form_id ) ) {
            if ( $this->configMode == $form_id ) {
                $config['submit_title'] = __( 'Save Changes', 'sitetree' );
                
                echo '<a href="', $this->pageURL(), '" class="sitetree-aux-tb-btn">', __( 'Cancel', 'sitetree' ), '</a>';
            }
            else {
                echo '<input type="submit" class="sitetree-aux-tb-btn sitetree-deactivate-tb-btn sitetree-hidden-tb-btn" name="submit" value="', __( 'Deactivate', 'sitetree' ), '">';
 
                $config['view_url']        = $this->plugin->sitemapURL( $form_id );
                $config['config_mode_url'] = $this->pageURL( array( 'config' => $form_id ) );
                $config['settings_url']    = $this->pageURL( array(), $form_id );
            }
        }
        else {
            $config['submit_title'] = __( 'Activate', 'sitetree' );
        }

        $dashboardPageView->configureToolbar( $config );
    }
    
    /**
     * {@inheritdoc}
     */
    public function dashboardCanDisplayMetrics( $dashboardPageView, $form_id ) {
        if (
            !$this->plugin->isSitemapActive( $form_id ) ||
            ( $this->configMode == $form_id )
        ) {
            return false;
        }

        $items_count_metric = (int) $this->db->getNonAutoloadOption( 'metrics', -1, 'tot_items', $form_id );

        switch ( $form_id ) {
            case 'site_tree':
                $dashboardPageView->registerMetric( __( 'Items', 'sitetree' ), $items_count_metric );
                break;

            case 'sitemap':
                $dashboardPageView->registerMetric( __( 'Permalinks', 'sitetree' ), $items_count_metric );
                $dashboardPageView->registerMetric( __( 'Images', 'sitetree' ), 
                                                  $this->db->getNonAutoloadOption( 'metrics', -1, 'tot_images', $form_id ) );
                break;

            case 'newsmap':
                $dashboardPageView->registerMetric( __( 'News', 'sitetree' ), $items_count_metric );
                break;
        }

        if ( $this->db->nonAutoloadOptionExists( 'metrics', 'avg_num_queries', $form_id ) ) {
            $key_prefix         = 'avg_';
            $queries_metric_title = __( 'Avg. Queries', 'sitetree' );
            $runtime_metric_title = __( 'Avg. Runtime', 'sitetree' );
        }
        else {
            $key_prefix         = '';
            $queries_metric_title = __( 'Queries', 'sitetree' );
            $runtime_metric_title = __( 'Runtime', 'sitetree' );
        }

        $queries_metric = (int) $this->db->getNonAutoloadOption( 'metrics', -1, "{$key_prefix}num_queries", $form_id );
        $runtime_metric = (float) $this->db->getNonAutoloadOption( 'metrics', 0, "{$key_prefix}runtime", $form_id ) . 's';

        $dashboardPageView->registerMetric( $queries_metric_title, $queries_metric );
        $dashboardPageView->registerMetric( $runtime_metric_title, $runtime_metric );

        $metrics_computed_on = $this->db->getNonAutoloadOption( 'metrics', 0, 'metrics_computed_on', $form_id );
        $metrics_computed_on = \sitetree_fn\time_since( $metrics_computed_on );
        
        $dashboardPageView->setMetricsFreshness( $metrics_computed_on );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function dashboardDidDisplayMetrics( $dashboardPageView, $form_id ) {
        if ( $form_id == 'site_tree' ) {
            return false;
        }

        $can_ping             = false;
        $website_is_local     = $this->plugin->isWebsiteLocal();
        $automatic_pinging_on = ( $this->db->getOption( 'automatic_pinging_on' ) || ( $form_id == 'newsmap' ) );

        if (! $website_is_local ) {
            $pingController = $this->plugin->invokeGlobalObject( 'PingController' );
            $info           = $pingController->getPingInfo( $form_id );
            $can_ping       = $pingController->canPingOnRequest( $form_id );
        }

        if ( $website_is_local ) {
            $status_class = 'sitetree-ping-notice';
        }
        elseif ( $automatic_pinging_on ) {
            $status_class = 'sitetree-automatic-pinging-on';
        }
        else {
            $status_class = 'sitetree-automatic-pinging-off';
        }

        echo '<div class="sitetree-pinging-bar sitetree-self-clear"><div class="sitetree-automatic-pinging-ui ', $status_class,
             '"><div class="sitetree-ap-bubble ', $status_class, '">';
        
        if ( $website_is_local ) {
            echo '<p class="sitetree-ap-status">', __( 'Pinging Disabled', 'sitetree' ), '</p>';
        }
        else {
            $checked_attribute     = '';
            $hidden_off_status_msg = '';
            $hidden_on_status_msg  = ' sitetree-ap-status-hidden';

            if ( $automatic_pinging_on ) {
                $checked_attribute     = ' checked';
                $hidden_off_status_msg = $hidden_on_status_msg;
                $hidden_on_status_msg  = '';
            }

            if ( $form_id == 'sitemap' ) {
                echo '<div class="sitetree-ap-switch"><input type="checkbox" id="sitetree-', $form_id, 
                     '-aps-control" class="sitetree-ap-switch-control"', $checked_attribute,
                     '><label for="sitetree-', $form_id, '-aps-control"></label></div>';  
            }

            echo '<p class="sitetree-ap-status sitetree-ap-on-status-msg', $hidden_on_status_msg, 
                 '">', __( 'Automatic Pinging ON', 'sitetree' ),
                 '</p><p class="sitetree-ap-status sitetree-ap-off-status-msg', $hidden_off_status_msg, 
                 '">', __( 'Automatic Pinging OFF', 'sitetree' ), '</p>';
        }

        echo '</div></div><p class="sitetree-ping-status-msg';

        if ( $website_is_local ) {
            echo ' sitetree-psm-on-pinging-disabled">';

            _e( "I'm sorry but I cannot send pings from your website, because its address appears to be a known local development environment URL.", 'sitetree' );
        }
        else {
            echo '">';

            if ( isset( $info['ping_failed'] ) && $info['ping_failed'] ) {
                echo '<strong>', __( 'Warning:', 'sitetree' ), '</strong> ';
            }

            echo $info['status_msg'];
        }

        echo '</p>';

        if ( $can_ping ) {
            $args  = array(
                'action'     => 'send_pings',
                'sitemap_id' => $form_id
            );

            echo '<a href="'. $this->pageURL( $args ) 
                   . '" class="sitetree-ping-btn">' . $info['ping_btn_title'] . '</a>';
        }
        elseif (! $website_is_local ) {
            $message = sprintf( __( 'Pinging-on-request idle for %s.', 'sitetree' ), 
                                $pingController->getTimeToNextPingInWords( $form_id ) );

            echo '<p class="sitetree-time-to-next-ping">' . $message . '</p>';
        }

        echo '</div>';
    }

    /**
     * {@inheritdoc}
     */
    public function dashboardDidDisplayForms() {
        $now = time();

    	$markup = '<aside id="sitetree-sidebar"><h3>SiteTree Goes Premium</h3><p>Financial needs together with the wish to keep the SiteTree project alive are the main reasons behind this decision, which I really hope will not deter you from upgrading to SiteTree 7.0 — ';

        if ( $now < strtotime( '2021-06-21' ) ) {
            $markup .= 'soon available.</p><p><a href="' . $this->plugin->authorURI( '/blog/sitetree-goes-premium/' ) . '">Here you can read more about the transition.</a></p>';
        }
        elseif ( $now < strtotime( '2021-07-31' ) ) {
            $markup .= '<a href="' . $this->plugin->pluginURI() . '">available now at a discounted price</a>.</p>';
        }
        else {
            $markup .= '<a href="' . $this->plugin->pluginURI() . '">now available</a>.</p>';
        }

        if ( $this->db->getOption( 'installed_on' ) < ( $now - WEEK_IN_SECONDS ) ) {
            $markup .= '<p>Thank you for your continued use of SiteTree.<p>';
        }
        else {
            $markup .= '<p>Thank you for choosing SiteTree.<p>';
        }
        
        $markup .= '<p>&mdash; Luigi Cavalieri</p></aside>';

        return $markup;
    }
}

/**
 * @since 5.0
 */
final class LeavesPageController
    extends PageController
 implements LeavesPageDelegateProtocol {
    /**
     * @since 6.0
     * @var string
     */
    private $targetLeafKeyword = '';

    /**
     * {@inheritdoc}
     */
    public function pageViewFormAction( $leavesPageView ) {
        $leaves = $this->plugin->getLeaves();

        if (! $leaves ) {
            return '';
        }

        reset( $leaves );

        $target_leaf = current( $leaves );
        
        while ( next( $leaves ) ) {
            $leaf = current( $leaves );

            if ( $leaf->baseLeafVersion() === $target_leaf->baseLeafVersion() ) {
                continue;
            }

            if ( version_compare( $leaf->baseLeafVersion(), $target_leaf->baseLeafVersion(), '>' ) ) {
                $target_leaf = $leaf;
            }
        }

        $this->targetLeafKeyword = $target_leaf->getLeafKeyword();

        /**
         * @since 6.0
         */
        return apply_filters( "sitetree_leaves_page_pass_form_action_{$this->targetLeafKeyword}", '', $this );
    }

    /**
     * {@inheritdoc}
     */
    public function leavesPageViewNeedsLeafURL( $leavesPageView, $leaf_keyword ) {
        $relative_url = 'leaves/' . $leaf_keyword . '/';

        return $this->plugin->pluginURI( $relative_url );
    }

    /**
     * {@inheritdoc}
     */
    public function leavesPageViewCanShowLeafActiveBadge( $leavesPageView, $leaf_keyword ) {
        return (bool) $this->plugin->getLeaf( $leaf_keyword );
    }

    /**
     * {@inheritdoc}
     */
    public function leavesPageViewIsDisplayingPassBox( $leavesPageView ) {
        $default_content = '<p id="sitetree-apb-descr">A Pass is everything needed to access and update all the Leaves for SiteTree for a timespan of 1 year.</p><a href="' . $this->plugin->authorURI( '/buy-pass/' ) . '" id="sitetree-apb-buy-btn" class="sitetree-box-default-btn">Buy Access Pass</a>';
        
        if ( $this->targetLeafKeyword ) {
            /**
             * @since 6.0
             */
            return apply_filters( "sitetree_leaves_page_pass_form_content_{$this->targetLeafKeyword}", $default_content, $this );
        }
        
        return $default_content;
    }
 }
?>