<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 * ************************************************************ */

if (! defined( 'ABSPATH' ) ) {
    exit;
}

if ( $this->plugin->isSitemapActive( 'sitemap' ) ) {
    $seo_section = new Section( __( 'Google Sitemaps and SEO', 'sitetree' ) );
    $seo_section->addField(
        new Fieldset( __( 'Add to the robots.txt file', 'sitetree' ), '', '', array(
            new Field( 'generate_disallow_rules', 'Checkbox', 'bool', '',
                       sprintf( __( 'A %s rule for each permalink excluded from the Sitemaps.', 'sitetree' ), '<code>Disallow</code>' ) ),
            new Field( 'add_sitemap_url_to_robots', 'Checkbox', 'bool', '', __( 'The permalink of the Sitemap.', 'sitetree' ) )
        ))
    );

    $this->registerSection( $seo_section );

    $exclude_fields = array();
    $taxonomies     = get_taxonomies( array( 'public' => true ), 'objects' );

    unset( $taxonomies['post_format'] );

    foreach ( $taxonomies as $taxonomy ) {
    	if ( $this->plugin->isContentTypeIncluded( $taxonomy->name, 'sitemap' ) ) {
            $exclude_fields[] = new Field( $taxonomy->name, 'TextField', 'list_of_ids', 
                                           sprintf( __( 'Exclude %s', 'sitetree' ), strtolower( $taxonomy->label ) ), 
                                           __( 'Comma-separated list of IDs.', 'sitetree' ), '' );
    	}
    }

    if ( $exclude_fields ) {
       $this->registerSection( new Section( '', 'exclude_from_sitemap', $exclude_fields ) ); 
    }
}

$general_section = new Section( __( 'General Settings', 'sitetree' ) );
$general_section->addField(
    new Field( 'deep_uninstal', 'Checkbox', 'bool', __( 'On uninstalling', 'sitetree' ),
               __( 'Remove from the database all the metadata associated to Posts, Pages and Custom Posts, together with the information about pinging events. General settings are deleted anyway.', 'sitetree' ) )
);

$this->registerSection( $general_section );
?>