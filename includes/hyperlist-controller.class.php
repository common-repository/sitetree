<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.2
 */
final class HyperlistController {
    /**
     * @since 5.2
     * @var object
     */
    private $plugin;

    /**
     * @since 5.2
     * @var array
     */
    private $contentTypes;

    /**
     * @since 5.2
     * @var array
     */
    private $postTypes;

    /**
     * @since 5.2
     * @var array
     */
    private $defaults;

    /**
     * @since 5.2
     * @var bool
     */
    private $doingShortcode = false;

    /**
     * @since 5.2
     * @param object $plugin
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * @since 5.2
     *
     * @param array $attributes
     * @return string
     */
    public function doShortcode( $attributes ) {
        if (! isset( $attributes['type'] ) ) {
            return '';
        }

        $this->doingShortcode = true;
        
        return $this->getHyperlist( $attributes['type'], $attributes );
    }

    /**
     * @since 5.2
     *
     * @param string $type
     * @param array $arguments
     * @return string
     */
    public function getHyperlist( $type, $arguments = array() ) {
        $this->loadContentTypes();

        if (! isset( $this->contentTypes[$type] ) ) {
            return '';
        }

        $this->loadDefaults();

        $content_type = $this->contentTypes[$type];

        if ( $this->doingShortcode ) {
            $list_options = shortcode_atts( $this->defaults[$content_type], $arguments, 'sitetree' );   
        }
        else {
            $list_options = wp_parse_args( $arguments, $this->defaults[$content_type] );
        }

        if ( $content_type == 'page' ) {
            // Handling backward compatibility.
            if ( $list_options['exclude_childs'] ) {
                $list_options['exclude_children'] = true; 
            }

            if ( 
                $this->doingShortcode && 
                isset( $list_options['only_children_of'] ) && 
                ( $list_options['only_children_of'] === 'this' )
            ) {
                global $post;

                if ( $post->post_type == 'page' ) {
                    $list_options['only_children_of'] = $post->ID;
                }
            }  
        }

        $builder = $this->plugin->invokeGlobalObject( 'SiteTreeBuilder' );
        $builder->setDoingHyperlist( true );
        $builder->setDoingShortcode( $this->doingShortcode );

        return $builder->buildList( $content_type, $list_options );
    }

    /**
     * @since 5.2
     * @return bool
     */
    private function loadContentTypes() {
        if ( $this->contentTypes ) {
            return false;
        }

        $this->contentTypes = array(
            'post'     => 'post',
            'page'     => 'page',
            'category' => 'category',
            'post_tag' => 'post_tag',
            'author'   => 'authors'
        );

        $this->postTypes = array(
            'post' => 'post',
            'page' => 'page'
        );

        $post_types = get_post_types( array( 'public' => true, '_builtin' => false ) );
        $taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ) );

        foreach ( $post_types as $post_type ) {
            $this->postTypes[$post_type]    = $post_type;
            $this->contentTypes[$post_type] = $post_type;
        }

        foreach ( $taxonomies as $taxonomy ) {
            $this->contentTypes[$taxonomy] = $taxonomy;
        }

        return true;
    }

    /**
     * @since 5.2
     * @return bool
     */
    private function loadDefaults() {
        if ( $this->defaults ) {
            return false;
        }

        $defaults_for_page = $this->plugin->invokeGlobalObject( 'DataController' )->defaultsForPage( 'site_tree', '', true );

        $this->defaults = &$defaults_for_page['site_tree'];

        foreach ( $this->contentTypes as $content_type ) {
            $this->defaults[$content_type]['show_title'] = true;

            if ( isset( $this->postTypes[$content_type] ) ) {
                $this->defaults[$content_type]['exclude']      = '';
                $this->defaults[$content_type]['include_only'] = '';

                if ( $this->doingShortcode ) {
                    $this->defaults[$content_type]['include_globally_excluded'] = false;
                }
            }

            if (! isset( $this->defaults[$content_type]['limit'] ) ) {
                $this->defaults[$content_type]['limit'] = 100;
            }
        }

        $this->defaults['page']['only_children_of'] = 0;

        // For backward compatibility. Deprecated since SiteTree 5.1
        $this->defaults['page']['exclude_childs'] = false;

        return true;
    }
}
?>