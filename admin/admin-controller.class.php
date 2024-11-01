<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class AdminController {
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
     * @since 5.0
     * @var object
     */
    private $dataController;

    /**
     * @since 5.0
     * @var string
     */
    private $currentPageId;

    /**
     * Local copy of the global $pagenow.
     *
     * @since 5.0
     * @var string
     */
    private $wpAdminPageId;

    /**
     * @since 5.0
     * @param string
     */
    private $taxonomyId;

    /**
     * @since 5.0
     * @param object $plugin
     */
    public function __construct( $plugin ) {
        global $pagenow;

        $this->plugin         = $plugin;
        $this->db             = $plugin->db();
        $this->wpAdminPageId  = $pagenow;
        $this->dataController = $plugin->invokeGlobalObject( 'DataController' );
    }

    /**
     * @since 5.0
     */
    public function handleSiteTreeAdminAjaxRequest() {
        if (! isset( $_POST['sitetree_action'] ) ) {
            exit;
        }

        $action = sanitize_key( $_POST['sitetree_action'] );

        switch ( $action ) {
            case 'enable_automatic_pinging':
                if (! isset( $_POST['enable_ap'] ) ) {
                    exit;
                }

                $automatic_pinging_on = (bool) (int) $_POST['enable_ap'];

                if (! $this->db->setOption( 'automatic_pinging_on', $automatic_pinging_on ) ) {
                    exit;
                }
                break;

            default:
                /**
                 * @since 5.0
                 */
                do_action( 'sitetree_is_doing_admin_ajax', $action );
                break;
        }

        exit( 'ok' );
    }

    /**
     * @since 5.0
     */
    public function wpDidFinishLoading() {
        $this->listenForUserAction();
        $this->registerActions();
    }

    /**
     * @since 5.0
     */
    private function listenForUserAction() {
        if ( $_POST && isset( $_POST['sitetree_page'] ) ) {
            $page_id = sanitize_key( $_POST['sitetree_page'] );
        }
        elseif ( $_GET && isset( $_GET['page'], $_GET['sitetree_nonce'] ) ) {
            $namespaced_page_id = sanitize_key( $_GET['page'] );
            $page_id            = str_replace( 'sitetree-', '', $namespaced_page_id );
        }
        else {
            return false;
        }

        $this->plugin->load( 'admin/page-view-delegate-protocols.php' );
        $this->plugin->load( 'admin/page-controller-classes.php' );

        $page = $this->dataController->page( $page_id );
        
        if ( !( $page && $this->wpAdminPageId == $page->parentSlug() ) ) {
            wp_die( __( 'Request sent to a non existent page.', 'sitetree' ) );
        }

        if ( !( isset( $_REQUEST['action'] ) && current_user_can( 'manage_options' ) ) ) {
            wp_die( 'You are being a bad fellow.' );
        }
            
        if ( is_multisite() && !is_super_admin() ) {
            wp_die( 'You are being a bad fellow.' );
        }

        $action_id      = sanitize_key( $_REQUEST['action'] );
        $pageController = PageController::makeController( $page,
                                                                  $this->plugin,
                                                                  $this->dataController );

        if (! check_admin_referer( $action_id, 'sitetree_nonce' ) ) {
            wp_die( 'You are being a bad fellow.' );
        }

        $redirect_url = $pageController->performUserAction( $action_id );

        if (! $redirect_url ) {
            /**
             * @since 5.0
             */
            $redirect_url = apply_filters( 'sitetree_admin_controller_will_redirect_on_unknown_user_action', 
                                           $redirect_url, $action_id, $pageController );
        }

        if ( $redirect_url === true ) {
            wp_redirect( $pageController->pageURL() );
        }
        elseif ( filter_var( $redirect_url, FILTER_VALIDATE_URL ) === false ) {
            wp_die( 'Unknown action.' );
        }
        else {
            wp_redirect( $redirect_url );
        }
        
        exit;
    }

    /**
     * @since 5.0
     */
    private function registerActions() {
        add_action( 'admin_menu', array( $this, 'registerAdminPages' ) );

        switch ( $this->wpAdminPageId ) {
            case 'post.php':
            case 'post-new.php':
                $this->plugin->load( 'admin/field-view.class.php' );
                $this->plugin->load( 'admin/meta-box-controller.class.php' );
                
                $metaBoxController = new MetaBoxController( $this->plugin );
                
                add_action( 'add_meta_boxes', array( $metaBoxController, 'wpDidAddMetaBoxes' ), 10, 2 );
                add_action( 'edit_attachment', array( $this, 'wpDidModifyAttachment' ), 100 );
                add_action( 'delete_attachment', array( $this, 'wpDidModifyAttachment' ), 100 );
                
                // When the POST request is sent from 'post-new.php',
                // sometimes WordPress doesn't invoke wpDidSavePost()
                // if it has been registered with a priority higher than 20.
                add_action( 'save_post', array( $metaBoxController, 'wpDidSavePost' ), 20, 2 );
                add_action( 'trashed_post', array( $this, 'wpDidTrashPost' ), 100 );
                add_action( 'untrashed_post', array( $this, 'wpDidTrashPost' ), 100 );
                break;

            case 'edit.php':
                add_action( 'trashed_post', array( $this, 'wpDidTrashPost' ), 100 );
                add_action( 'untrashed_post', array( $this, 'wpDidTrashPost' ), 100 );
                break;

            case 'plugins.php':
                $filter_name = 'plugin_action_links_' . $this->plugin->basename();

                add_filter( $filter_name, array( $this, 'addDashboardLinkToActionLinks' ) );
                break;

            case 'edit-tags.php':
                if (! isset( $_REQUEST['taxonomy'] ) ) {
                    break;
                }

                $this->taxonomyId = sanitize_key( $_REQUEST['taxonomy'] );

                if (! taxonomy_exists( $this->taxonomyId ) ) {
                    break;
                }

                add_action( 'edit_' . $this->taxonomyId, array( $this, 'wpDidModifyTaxonomy' ), 100 );
                add_action( 'create_' . $this->taxonomyId, array( $this, 'wpDidModifyTaxonomy' ), 100 );
                add_action( 'delete_' . $this->taxonomyId, array( $this, 'wpDidModifyTaxonomy' ), 100 );
                break;
                
            case 'user-new.php':
                add_action( 'user_register', array( $this, 'wpDidModifyUserProfile' ), 100 );
                break;
                
            case 'user-edit.php':
            case 'profile.php': 
                add_action( 'profile_update', array( $this, 'wpDidModifyUserProfile' ), 100 );
                break;
                
            case 'users.php':
                add_action( 'delete_user', array( $this, 'wpDidModifyUserProfile' ), 100 );
                break;
        }
    }

    /**
     * @since 5.0
     */
    public function registerAdminPages() {
        $pages              = $this->dataController->pages( false );
        $first_page_menu_id = $pages[0]->namespacedID();

        add_menu_page( 'SiteTree', 'SiteTree', 'manage_options', $first_page_menu_id, 
                       '__return_false', $this->getBase64MenuIcon(), 90 );

        foreach ( $pages as $page ) {
            $menu_page_id = $page->namespacedID();
            
            if ( 
                isset( $_GET['page'] ) && 
                ( $_GET['page'] == $menu_page_id ) && 
                ( $this->wpAdminPageId == $page->parentSlug() )
            ) {
                $this->plugin->load( 'admin/field-view.class.php' );
                $this->plugin->load( 'admin/page-view.class.php' );
                $this->plugin->load( 'admin/page-view-delegate-protocols.php' );
                $this->plugin->load( 'admin/page-controller-classes.php' );

                if ( $page->viewClass() !== 'PageView' ) {
                    $this->plugin->load( 'admin/' . $page->id() . '-page-view.class.php' );
                }

                $this->currentPageId = $menu_page_id;
                
                $pageController     = PageController::makeController( $page, $this->plugin, $this->dataController );
                $menu_page_callback = array( $pageController->loadPageView(), 'display' );

                add_action( 'admin_enqueue_scripts', array( $this, 'enqueueStylesAndScripts' ) );
                add_action( 'admin_print_footer_scripts', array( $this, 'printInitScript' ) );
            }
            else {
                $menu_page_callback = '__return_false';
            }

            add_submenu_page( $first_page_menu_id, $page->title(), $page->menuTitle(), 'manage_options', 
                              $menu_page_id, $menu_page_callback );
        }
    }

    /**
     * @since 5.0
     */
    public function enqueueStylesAndScripts() {
        $version      = $this->plugin->version();
        $min_suffix   = $this->plugin->stylesAndScriptsSuffix();
        $css_file_url = $this->plugin->dirURL( 'resources/sitetree' . $min_suffix . '.css' );
        $js_file_url  = $this->plugin->dirURL( 'resources/sitetree' . $min_suffix . '.js' );
        
        wp_enqueue_style( 'sitetree', $css_file_url, null, $version );
        wp_enqueue_script( 'sitetree', $js_file_url, array( 'jquery-ui-sortable' ), $version );
    }

    /**
     * @since 5.0
     *
     * @param array $action_links
     * @return array
     */
    public function addDashboardLinkToActionLinks( $action_links ) {
        $action_links['dashboard'] = '<a href="' . $this->dashboardURL()
                                   . '">' . __( 'Dashboard', 'sitetree' )
                                   . '</a>';

        return $action_links;
    }

    /**
     * @since 5.0
     */
    public function dashboardURL() {
        $dashboard = $this->dataController->page( 'dashboard' );
        $arguments = array( 'page' => $dashboard->namespacedID() );  

        return add_query_arg( $arguments, admin_url( $dashboard->parentSlug() ) );
    }

    /**
     * @since 5.0
     */
    public function printInitScript() {
        echo '<script>SiteTree.init("', $this->currentPageId,
             '", {sftEnableBtnTitle:"', __( 'Enable Reordering', 'sitetree' ),
             '", sftCancelBtnTitle:"', __( 'Cancel', 'sitetree' ),
             '", sftSaveBtnTitle:"', __( 'Save', 'sitetree' ), 
             '", sortableFieldsetTooltip:"', __( 'Drag the content types to reorder the hyper-lists.', 'sitetree' ),
             '"});</script>';
    }

    /**
     * @since 5.0
     *
     * @param string $post_id
     * @param object $post
     */
    public function wpDidSavePost( $post_id, $post ) {
        if ( !( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return false;
        }

        if ( ( $post->post_status != 'publish' ) || !current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }

        if ( $post_id == get_option( 'page_on_front' ) ) {
            $this->plugin->flushCachedData( 'sitemap' );
        }
    }

    /**
     * @since 5.0
     * @param int $post_id
     */
    public function wpDidTrashPost( $post_id ) {
        $post = get_post( $post_id );

        if (! $post ) {
            return false;
        }

        if (
            $this->plugin->isSitemapActive( 'site_tree' ) &&
            $this->plugin->isContentTypeIncluded( $post->post_type, 'site_tree' ) && 
            !$this->db->getPostMeta( $post->ID, 'exclude_from_site_tree' )
        ) {
            $this->plugin->flushCachedData( 'site_tree' );
        }
        
        if (
            $this->plugin->isSitemapActive( 'sitemap' ) &&
            $this->plugin->isContentTypeIncluded( $post->post_type, 'sitemap' )
        ) {
            $this->plugin->flushCachedData( 'sitemap' );
        }

        if ( 
            $this->plugin->isSitemapActive( 'newsmap' ) &&
            $this->plugin->isContentTypeIncluded( $post->post_type, 'newsmap' )
        ) {
            $this->plugin->flushCachedData( 'newsmap' );
        }
    }
    
    /**
     * @since 5.0
     * @param int $user_id
     */
    public function wpDidModifyUserProfile( $user_id ) {
        if (
            $this->plugin->isSitemapActive( 'site_tree' ) &&
            (
                $this->plugin->isContentTypeIncluded( 'authors', 'site_tree' ) || 
                $this->db->getOption( 'group_by', false, 'post', 'site_tree' ) == 'author'
            )
        ) {
            $excluded_authors = explode( ', ', $this->db->getOption( 'exclude', '', 'authors', 'site_tree' ) );

            if ( 
                !( 
                    $excluded_authors && 
                    in_array( get_userdata( $user_id )->user_nicename, $excluded_authors )
                ) 
            ) {
                $this->plugin->flushCachedData( 'site_tree' );
            }            
        }
        
        if (
            $this->plugin->isSitemapActive( 'sitemap' ) &&
            $this->plugin->isContentTypeIncluded( 'authors', 'sitemap' )
        ) {
            $this->plugin->flushCachedData( 'sitemap' );
        }
    }
    
    /**
     * @since 5.0
     * @param int $term_id
     */ 
    public function wpDidModifyTaxonomy( $term_id ) {
        if ( 
            $this->plugin->isSitemapActive( 'site_tree' ) &&
            $this->plugin->isContentTypeIncluded( $this->taxonomyId, 'site_tree' )
        ) {
            $excluded_ids = $this->db->getOption( 'exclude', '', $this->taxonomyId, 'site_tree' );

            if ( !( $excluded_ids && in_array( $term_id, wp_parse_id_list( $excluded_ids ) ) ) ) {
                $this->plugin->flushCachedData( 'site_tree' );
            }
        }

        if (
            $this->plugin->isSitemapActive( 'sitemap' ) &&
            $this->plugin->isContentTypeIncluded( $this->taxonomyId, 'sitemap' )
        ) {
            $excluded_ids = $this->db->getOption( 'exclude', '', $this->taxonomyId, 'sitemap' );

            if ( !( $excluded_ids && in_array( $term_id, wp_parse_id_list( $excluded_ids ) ) ) ) {
                $this->plugin->flushCachedData( 'sitemap' );
            }
        }
    }

    /**
     * @since 5.0
     * @param int $attachment_id
     */
    public function wpDidModifyAttachment( $attachment_id ) {
        if ( $this->plugin->isSitemapActive( 'sitemap' ) ) {
            $attachment = get_post( $attachment_id );
        
            if ( 
                $attachment && 
                $attachment->post_parent &&
                !$this->db->getPostMeta( $attachment->post_parent, 'exclude_from_sitemap' )
            ) {
                $this->plugin->flushCachedData( 'sitemap' );
            }
        }
    }

    /**
     * @since 5.0
     * @return string
     */
    public function getBase64MenuIcon() {
        return 'data:image/svg+xml;base64,PHN2ZyBpZD0iQWRtaW5fTWVudV9JY29uIiBkYXRhLW5hbWU9IkFkbWluIE1lbnUgSWNvbiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB3aWR0aD0iMjU2IiBoZWlnaHQ9IjI1NiIgdmlld0JveD0iMCAwIDI1NiAyNTYiPgogIDxkZWZzPgogICAgPHN0eWxlPgogICAgICAuY2xzLTEgewogICAgICAgIGZpbGw6ICM5ZWEzYTg7CiAgICAgICAgZmlsbC1ydWxlOiBldmVub2RkOwogICAgICB9CiAgICA8L3N0eWxlPgogIDwvZGVmcz4KICA8cGF0aCBpZD0iTGl0dGxlXzMiIGRhdGEtbmFtZT0iTGl0dGxlIDMiIGNsYXNzPSJjbHMtMSIgZD0iTTEyMy42NiwxNTIuODcyQTkyLjM2Niw5Mi4zNjYsMCwwLDAsMTI4LDEzM2ExMDQuNzYsMTA0Ljc2LDAsMCwwLDUuMDc3LDIwLjQzNmMzLjU5MSw5Ljk1Niw3LjY3NSwxNS45MDgsOC4wNDgsMjUuMjI0LDAuNDQ1LDExLjEzNy00LjQ1NCwyMS45NDgtMTMuMTg1LDMyLjIzNi04LjM3LTkuNTc1LTEzLjIxMS0xOS43NTgtMTMuMTUzLTMwLjQxNEMxMTQuODQyLDE3MC4zMTYsMTE5LjkyNywxNjMuODYyLDEyMy42NiwxNTIuODcyWiIvPgogIDxwYXRoIGlkPSJMaXR0bGVfMiIgZGF0YS1uYW1lPSJMaXR0bGUgMiIgY2xhc3M9ImNscy0xIiBkPSJNMTQzLDExOWExMDQuOTcxLDEwNC45NzEsMCwwLDEtMTUsMTQsOTIuMjI4LDkyLjIyOCwwLDAsMSwyMC02YzEwLjI1Ni0xLjgyMSwxNy42LS44NDksMjYtNSw5Ljk5NC00LjkzNywxNy4xNDMtMTQuNDExLDIyLTI3LTEyLjQxMi0yLjc2OS0yMy42Ny0yLjE0OS0zMywzQzE1NC4xMzksMTAyLjg5LDE1MC43ODEsMTEwLjEzMywxNDMsMTE5WiIvPgogIDxwYXRoIGlkPSJMaXR0bGVfMSIgZGF0YS1uYW1lPSJMaXR0bGUgMSIgY2xhc3M9ImNscy0xIiBkPSJNMTEzLDExOWExMDQuOTcxLDEwNC45NzEsMCwwLDAsMTUsMTQsOTIuMjI4LDkyLjIyOCwwLDAsMC0yMC02Yy0xMC4yNTYtMS44MjEtMTcuNi0uODQ5LTI2LTUtOS45OTQtNC45MzctMTcuMTQzLTE0LjQxMS0yMi0yNywxMi40MTItMi43NjksMjMuNjctMi4xNDksMzMsM0MxMDEuODYxLDEwMi44OSwxMDUuMjE5LDExMC4xMzMsMTEzLDExOVoiLz4KICA8cGF0aCBpZD0iXzMiIGRhdGEtbmFtZT0iMyIgY2xhc3M9ImNscy0xIiBkPSJNMjE1LjE5NCwyMjUuMTcxYzguMTQ4LTEwLjYsMTAuNDc2LTI0LjM0NCw3LjQ2OC0zNy4yMDYsMTIuMjU1LTMuODk0LDIyLjY3NS0xMi40ODIsMjcuOC0yNC40MTUsNi43NTktMTUuNzM3LDQuMTQyLTM3LjA4Mi0xMC4yNjItNDguNTA1LTUuNzE4LTQuNTM1LTE3LjQ5Mi0xMC44NDQtMzAuNTA3LTcuMDkxLTEyLjQ4NywzLjYtMTguOTkyLDE2Ljc0NC0yNi4zMTEsMjEuODEyLTEzLjMsOS4yMS0zMS45MjQsMTAuMjM0LTU1LjMyMiw0LjA1NSwxNy4xNzksMTcuMDQ1LDI1LjcyOSwzMy42MjIsMjQuNTI0LDQ5Ljc1NS0wLjY2Miw4Ljg3OC04LjcwNSwyMS4xNDctNS40OCwzMy43MzMsMy4zNywxMy4xNTcsMTQuODMsMjAuMTA5LDIxLjU2NiwyMi43MTRDMTg1Ljg2OSwyNDYuNjcyLDIwNS4wOSwyMzguMzE0LDIxNS4xOTQsMjI1LjE3MVoiLz4KICA8cGF0aCBpZD0iXzIiIGRhdGEtbmFtZT0iMiIgY2xhc3M9ImNscy0xIiBkPSJNNDEuOSwyMjUuMTcxYy04LjE0OC0xMC42LTEwLjQ3NS0yNC4zNDQtNy40NjgtMzcuMjA2LTEyLjI1NS0zLjg5NC0yMi42NzUtMTIuNDgyLTI3LjgtMjQuNDE1LTYuNzU5LTE1LjczNy00LjE0Mi0zNy4wODIsMTAuMjYxLTQ4LjUwNSw1LjcxOC00LjUzNSwxNy40OTMtMTAuODQ0LDMwLjUwOC03LjA5MSwxMi40ODcsMy42LDE4Ljk5MSwxNi43NDQsMjYuMzEsMjEuODEyLDEzLjMsOS4yMSwzMS45MjUsMTAuMjM0LDU1LjMyMiw0LjA1NS0xNy4xOCwxNy4wNDUtMjUuNzI5LDMzLjYyMi0yNC41MjUsNDkuNzU1LDAuNjYzLDguODc4LDguNzA2LDIxLjE0Nyw1LjQ4MSwzMy43MzMtMy4zNzEsMTMuMTU3LTE0LjgzLDIwLjEwOS0yMS41NjYsMjIuNzE0QzcxLjIyNSwyNDYuNjcyLDUyLDIzOC4zMTQsNDEuOSwyMjUuMTcxWiIvPgogIDxwYXRoIGlkPSJfMSIgZGF0YS1uYW1lPSIxIiBjbGFzcz0iY2xzLTEiIGQ9Ik05MiwxMmMxMy4yNDUtMS44MDcsMjYuMzMxLDMsMzYsMTIsOS40NjctOC43LDIyLjEtMTMuNDc5LDM1LTEyLDE3LjAxNSwxLjk1MSwzNC4yNCwxNC44MjUsMzcsMzMsMS4xLDcuMjE2LjcyMiwyMC41NjgtOSwzMC05LjMyNyw5LjA0OS0yMy45NjYsOC4xNjUtMzIsMTItMTQuNiw2Ljk2OC0yNC43NCwyMi42MjMtMzEsNDYtNi4yNi0yMy4zNzctMTYuNC0zOS4wMzItMzEtNDYtOC4wMzQtMy44MzUtMjIuNjc4LTIuOTQ5LTMyLTEyLTkuNzQ0LTkuNDYxLTEwLjA4Ni0yMi44Ni05LTMwQzU4Ljc3MiwyNi43NzEsNzUuNTc0LDE0LjI0MSw5MiwxMloiLz4KPC9zdmc+Cg==';
    }
}
?>