<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class MetaBoxController {
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
	 * @var array
	 */
	private $sections = array();
	
	/**
	 * @since 5.0
	 * @param object $plugin
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->db	  = $plugin->db();
	}

	/**
	 * @since 5.0
     *
     * @param string $post_type
	 * @param object $post
	 */
	public function wpDidAddMetaBoxes( $post_type, $post ) {
        if ( $this->initSections( $post ) ) {
			add_meta_box( 'sitetree', 'SiteTree', array( $this, 'displayMetaBox' ), $post_type, 'side' );
        }
	}

    /**
     * @since 5.0
     * @param object $section
     */
    public function registerSection( $section ) {
        $this->sections[] = $section;
    }

    /**
     * @since 5.0
     *
     * @param object $post
     * @return bool
     */
    private function initSections( $post ) {
        if ( $post->ID == get_option( 'page_on_front' ) ) {
            return false;
        }

        $is_sitemap_active = $this->plugin->isSitemapActive( 'sitemap' );
        $site_tree_id      = (int) $this->db->getOption( 'page_for_site_tree' );

        if (
            $is_sitemap_active &&
            ( $post->post_type == 'page' ) &&
            ( $post->ID != $site_tree_id ) &&
            ( $post->ID != get_option( 'page_for_posts' ) )
        ) {
            $ghost_section = new Section();
            $ghost_section->addField( new Field( 'is_ghost_page','MetaCheckbox', 'bool', '', 
                                                 __( 'This is a Ghost Page', 'sitetree' ), false ) );
            
            $this->registerSection( $ghost_section );
        }

        /**
         * @since 5.0
         */
        do_action( 'sitetree_meta_box_controller_will_init_exclude_section', $this, $post );

        $exclude_section = new Section( __( 'Exclude From...', 'sitetree' ), 'exclude_from' );
        
        if (
            ( $post->ID != $site_tree_id ) &&
            $this->plugin->isSitemapActive( 'site_tree' ) &&
            $this->plugin->isContentTypeIncluded( $post->post_type, 'site_tree' )
        ) {
            $exclude_section->addField(
                new Field( 'exclude_from_site_tree','MetaCheckbox', 'bool', '', 'Site Tree', false, 'site_tree' )
            );
        }

        if ( 
            $is_sitemap_active &&
            $this->plugin->isContentTypeIncluded( $post->post_type, 'sitemap' )
        ) {
            $exclude_section->addField(
                new Field( 'exclude_from_sitemap', 'MetaCheckbox', 'bool', '', 'Google Sitemap', false, 'sitemap' )
            );
        }

        if ( 
            $this->plugin->isSitemapActive( 'newsmap' ) &&
            $this->plugin->isContentTypeIncluded( $post->post_type, 'newsmap' )
        ) {
            $exclude_section->addField(
                new Field( 'exclude_from_newsmap', 'MetaCheckbox', 'bool', '', 'News Sitemap', false, 'newsmap' )
            );
        }

        $exclude_section->addField(
            new Field( 'exclude_from_shortcode_lists', 'MetaCheckbox', 'bool', '', __( 'Shortcode-generated Hyper-lists', 'sitetree' ) )
        );
        
        $this->registerSection( $exclude_section );
        
        return true;
    }

	/**
	 * @since 5.0
	 * @param object $post
	 */
	public function displayMetaBox( $post ) {
        $i = 0;

        echo '<input type="hidden" name="sitetree_nonce" value="', wp_create_nonce( 'sitetre_metadata' ), '">';
        
        foreach ( $this->sections as $section ) {
            $fields        = $section->fields();
            $section_title = $section->title();
            
            if ( $section_title ) {
                if ( $i > 0 ) {
                    echo '<h4 style="margin:30px 0 15px;">'; 
                }
                else {
                    echo '<h4 style="margin:20px 0 15px;">'; 
                } 

                echo $section->title(), '</h4>';  
            }

            echo $section->description();
           
            foreach ( $fields as $field ) {
                $value = $this->db->getPostMeta( $post->ID,
                                                 $field->id(),
                                                 $field->defaultValue() );
                
                $filter = new SiteTreeOptionsFilter( $value, $field );
                $value  = $filter->filterOption();
                
                $fieldView = FieldView::makeView( $field );
                $fieldView->init( $value );

                if ( $section_title ) {
                    echo '<div style="margin-top:10px;">';  
                }
                else {
                    echo '<div style="margin-top:20px;">';
                }

                $fieldView->display();

                echo '</div>';
            }

            $i += 1;
        }
    }
	
	/**
	 * @since 5.0
	 *
	 * @param string $post_id
	 * @param object $post
	 */
	public function wpDidSavePost( $post_id, $post ) {
        if ( 
            !isset( $_POST['sitetree_nonce'] ) || 
            ( $post->post_status == 'auto-draft' ) ||
            wp_is_post_revision( $post )
        ) {
            return false;
        }

        if (! check_admin_referer( 'sitetre_metadata', 'sitetree_nonce' ) ) {
            wp_die( 'You are being a bad fellow.' );
        }
            
        if (! current_user_can( 'edit_post', $post_id ) ) {
           wp_die( 'You are being a bad fellow.' );
        }
        
        if (! $this->initSections( $post ) ) {
           wp_die( 'You are being a bad fellow.' );
        }

        if ( isset( $_POST['sitetree'] ) && !is_array( $_POST['sitetree'] ) ) {
           wp_die( 'You are being a bad fellow.' );
        }

        $section_index = 0;
        $fields        = $this->sections[$section_index]->fields();

        if ( $fields[$section_index]->id() == 'is_ghost_page' ) {
            $section_index += 1;
            $is_ghost_page  = false;

            if ( isset( $_POST['sitetree']['is_ghost_page'] ) ) {
                $filter        = new SiteTreeOptionsFilter( $_POST['sitetree']['is_ghost_page'], $fields[0] );
                $is_ghost_page = $filter->filterOption();
            }

            if ( $is_ghost_page ) {
                $was_ghost_page = $this->db->getPostMeta( $post_id, 'is_ghost_page' );

                if (! $was_ghost_page ) {
                    $this->db->setPostMeta( $post_id, 'is_ghost_page', true );
                    $this->db->deletePostMeta( $post_id, 'exclude_from_sitemap' );
                    $this->db->deletePostMeta( $post_id, 'exclude_from_site_tree' );
                    $this->plugin->flushCachedData( 'sitemap' );
                    $this->plugin->flushCachedData( 'site_tree' );
                }
                
                return true;
            }
            
            $this->db->deletePostMeta( $post_id, 'is_ghost_page' );
        }

        while ( isset( $this->sections[$section_index] ) ) {
            $section    = $this->sections[$section_index];
            $section_id = $section->id();
            $fields     = $section->fields();

            foreach ( $fields as $field ) {
                $value    = false;
                $field_id = $field->id();
                
                if ( isset( $_POST['sitetree'][$field_id] ) ) {
                    $filter = new SiteTreeOptionsFilter( $_POST['sitetree'][$field_id], $field );
                    $value  = $filter->filterOption();
                }

                if ( $section_id == 'exclude_from' ) {
                    $this->processExcludeFlag( $post, $field, $value );
                }
                else {
                    /**
                     * @since 5.0
                     */
                    do_action( 'sitetree_meta_box_controller_is_processing_data', $this, $post, $field, $value ); 
                }
            }

            $section_index += 1;
        }

        return true;
    }

    /**
     * @since 5.0
     *
     * @param object $post
     * @param object $field
     * @param bool $exclude
     * @return bool
     */
    private function processExcludeFlag( $post, $field, $exclude ) {
        $field_id    = $field->id();
        $is_excluded = (bool) $this->db->getPostMeta( $post->ID, $field_id );

        if ( $exclude ) {
            if ( $is_excluded ) {
                return false;
            }

            $this->db->setPostMeta( $post->ID, $field_id, $exclude );
        }
        elseif ( $is_excluded ) {
            $this->db->deletePostMeta( $post->ID, $field_id );  
        }

        if ( ( $post->post_status != 'publish' ) || ( $field_id == 'exclude_from_shortcode_lists' ) ) {
            return false;
        }

        $context     = $field->additionalData();
        $is_new_post = ( strtotime( $post->post_modified ) - strtotime( $post->post_date ) < 5 );

        if ( !( $exclude && $is_new_post ) ) {
            $this->plugin->flushCachedData( $context );
        }
        
        if (
            !$exclude && 
            $is_new_post && 
            ( $context != 'site_tree' ) &&
            ( $this->db->getOption( 'automatic_pinging_on' ) || ( $context == 'newsmap' ) )
        ) {
            $this->plugin->invokeGlobalObject( 'PingController' )->ping( $context, $post );
        }

        return true;
    }
}