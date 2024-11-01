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

// Collection of messages used more than once.
// The elements of type Array contain the title of the field at index 0 and its description/tooltip at index 1.
$common_l10n = array(
	'title'		 => __( 'Hyper-list title', 'sitetree' ),
    'style'      => __( 'Hyper-list style', 'sitetree' ),
	'show_count' => __( 'Posts count', 'sitetree' ),
	'exclude'	 => array(
        __( 'Exclude', 'sitetree' ),
        __( 'Comma-separated list of IDs.', 'sitetree' )
    ),
	'order_by'	 => __( 'Order by', 'sitetree' ),
    'limit'      => array(
        __( 'Max. number of items', 'sitetree' ),
        __( 'Set to -1 to list all the items.', 'sitetree' )
    )
);

// --- Common values.

$list_style_options = array(
	'1' => __( 'Hierarchical', 'sitetree' ),
	'0' => __( 'Flat', 'sitetree' )
);

$orderby_options = array(
	'name'	=> __( 'Name', 'sitetree' ),
	'count' => __( 'Most used', 'sitetree' )
);

$post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
$taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'objects' );

// The list of authors' pages is at position 1.
$position         = 2;
$position_options = array();
$num_of_lists     = 5 + count( $post_types ) + count( $taxonomies );

for ( $i = 1; $i <= $num_of_lists; $i++ ) {
    $position_options[$i] = $i;
}

/* ************************************************************ */

if ( $load_all_sections || $this->plugin->isContentTypeIncluded( 'page', 'site_tree', true ) ) {
    $this->registerSection( new Section( __( 'Pages', 'sitetree' ), 'page', array(
        new Field( 'title', 'TextField', 'inline_html', $common_l10n['title'], '', __( 'Pages', 'sitetree' ) ),
        new Field( 'hierarchical', 'Dropdown', 'choice', $common_l10n['style'], '', '1', $list_style_options ),
        new Field( 'order_by', 'Dropdown', 'choice', $common_l10n['order_by'], '', 'menu_order', array(
            'menu_order' => __( 'Menu order &amp; Title', 'sitetree' ),
            'title'      => __( 'Title', 'sitetree' )
        )),
        new Field( 'show_home', 'Checkbox', 'bool', __( 'Home page', 'sitetree' ), 
                   __( 'Show a &lsquo;Home&rsquo; link on top of the hyper-list.', 'sitetree' ) ),
        new Field( 'exclude_children', 'Checkbox', 'bool', __( 'Only primary pages', 'sitetree' ), 
                   __( 'Exclude all the child pages.', 'sitetree' ) ),
        new Fieldset( __( 'De-hyperlink parent pages', 'sitetree' ), '', 'inline', array(
            new Field( 'dehyperlink_parents', 'Checkbox', 'bool', '', 
                       __( 'Disable the hyperlinking of parent pages up to the', 'sitetree' ) ),
            new Field( 'dehyperlinking_level', 'Dropdown', 'choice', '', __( 'level.', 'sitetree' ), '0', array(
                '0' => __( 'first', 'sitetree' ),
                '1' => __( 'second', 'sitetree' ),
                '2' => __( 'third', 'sitetree' )
            )),
        ))
    ) ));
}

if ( $load_all_sections || $this->plugin->isContentTypeIncluded( 'post', 'site_tree', true ) ) {
    $this->registerSection( new Section( __( 'Posts', 'sitetree' ), 'post', array(
        new Field( 'title', 'TextField', 'inline_html', $common_l10n['title'], '', __( 'Posts', 'sitetree' ) ),
        new Fieldset( __( 'Group by', 'sitetree' ), '', 'inline', array(
            new Field( 'group_by', 'Dropdown', 'choice', '', '&amp;', 'none', 
                       array(
                           'none'      => '-', 
                           'date'      => __( 'Date', 'sitetree' ),
                           'category'  => __( 'Category', 'sitetree' ),
                           'author'    => __( 'Author', 'sitetree' )
                       )
            ),
            new Field( 'hyperlink_group_title', 'Dropdown', 'choice', 
                       '', __( 'the title of each group.', 'sitetree' ), '1', 
                       array(
                            '1' => __( 'Hyperlink', 'sitetree' ), 
                            '0' => __( 'De-hyperlink', 'sitetree' )
                       )
            ),
        )),
        new Field( 'order_by', 'Dropdown', 'choice', $common_l10n['order_by'], '', 'post_date', array(
            'post_date'     => __( 'Most recent', 'sitetree' ),
            'comment_count' => __( 'Most popular', 'sitetree' ),
            'post_title'    => __( 'Title', 'sitetree' ),
            'post_date_asc' => __( 'Older', 'sitetree' )
        )),
        new Field( 'pop_stickies', 'Checkbox', 'bool', __( 'Sticky posts', 'sitetree' ), 
            __( 'Stick Featured Posts to the top of the hyper-list.', 'sitetree' )
        ),
        new Fieldset( __( 'Show excerpt', 'sitetree' ), '', 'inline', array(
            new Field( 'show_excerpt', 'Checkbox', 'bool', '', 
                __( 'Show for each post a short excerpt of', 'sitetree' )
            ),
            new Field( 'excerpt_length', 'NumberField', 'positive_number', '', 
                __( 'characters.', 'sitetree' ), 100, array( 'min_value' => 50, 'max_value' => 300 )
            ),
        )),
        new Field( 'show_comments_count', 'Checkbox', 'bool', __( 'Comments count', 'sitetree' ), 
            __( 'Show for each post the number of comments received.', 'sitetree' )
        ),
        new Field( 'show_date', 'Checkbox', 'bool', __( 'Publication date', 'sitetree' ), 
            __('Show for each post the date of publication.', 'sitetree' )
        ),
        new Field( 'limit', 'NumberField', 'positive_number', $common_l10n['limit'][0], 
                   $common_l10n['limit'][1], -1, array( 'min_value' => -1, 'max_value' => 1000 )
        )     
    ) ));
}

foreach ( $post_types as $post_type ) {
	if ( $load_all_sections || $this->plugin->isContentTypeIncluded( $post_type->name, 'site_tree' ) ) {
        $post_type_section = new Section( $post_type->label, $post_type->name );

	    $post_type_section->addField( new Field( 'title', 'TextField', 
                                                 'inline_html', $common_l10n['title'], '', $post_type->label ) );
        $post_type_section->addField( new Field( 'order_by', 'Dropdown', 
                                                 'choice', $common_l10n['order_by'], '', 'post_title', 
                                                 array(
                                    	            'post_title'    => __( 'Title', 'sitetree' ),
                                    	            'post_date'     => __( 'Most recent', 'sitetree' ),
                                    	            'post_date_asc' => __( 'Older', 'sitetree' )
                                    	         ) ));      

	    if ( $post_type->hierarchical ) {
	        $post_type_section->addField( new Field( 'hierarchical', 'Dropdown', 'choice', $common_l10n['style'], 
                                                     '', '1', $list_style_options ) );
	    }

	    $post_type_section->addField( new Field( 'limit', 'NumberField', 
                                                 'positive_number', $common_l10n['limit'][0], $common_l10n['limit'][1], -1,
                                                  array( 'min_value' => -1, 'max_value' => 1000 ) ));

	    $this->registerSection( $post_type_section );
	}
}

if ( $load_all_sections || $this->plugin->isContentTypeIncluded( 'category', 'site_tree' ) ) {
	$this->registerSection( new Section( __( 'Categories', 'sitetree' ), 'category', array(
	    new Field( 'title', 'TextField', 'inline_html', $common_l10n['title'], '', __( 'Categories', 'sitetree' ) ),
	    new Field( 'show_count', 'Checkbox', 'bool', $common_l10n['show_count'], 
	        __( 'Show for each category the number of published posts.', 'sitetree' ), true
	    ),
	    new Field( 'feed_text', 'TextField', 'plain_text', __("Text of the link to each category's RSS feed", 'sitetree' ), 
	        __( 'Leave empty to hide the link.', 'sitetree' ), '', 'small-text'
	    ),
	    new Field( 'hierarchical', 'Dropdown', 'choice', $common_l10n['style'], '', '1', $list_style_options ),
	    new Field( 'order_by', 'Dropdown', 'choice', $common_l10n['order_by'], '', 'name', $orderby_options ),
        new Field( 'exclude', 'TextField', 'list_of_ids', $common_l10n['exclude'][0], $common_l10n['exclude'][1], '' )
	) ));
}

if ( $load_all_sections || $this->plugin->isContentTypeIncluded( 'post_tag', 'site_tree' ) ) {
	$this->registerSection( new Section( __( 'Tags', 'sitetree' ), 'post_tag', array(
	    new Field( 'title', 'TextField', 'inline_html', $common_l10n['title'], '', __( 'Tags', 'sitetree' ) ),
	    new Field( 'show_count', 'Checkbox', 'bool', $common_l10n['show_count'], 
                           __( 'Show the number of posts published under each tag.', 'sitetree' )
	    ),
	    new Field( 'order_by', 'Dropdown', 'choice', $common_l10n['order_by'], '', 'name', $orderby_options ),
        new Field( 'exclude', 'TextField', 
                           'list_of_ids', $common_l10n['exclude'][0], $common_l10n['exclude'][1], '' )
	) ));
}

foreach ( $taxonomies as $taxonomy ) {
    if ( $load_all_sections || $this->plugin->isContentTypeIncluded( $taxonomy->name, 'site_tree' ) ) {
        $taxonomy_section = new Section( $taxonomy->label, $taxonomy->name );
        $taxonomy_section->addField( new Field( 'title', 'TextField', 
                                                'inline_html', $common_l10n['title'], '', $taxonomy->label ) );
        $taxonomy_section->addField( new Field( 'order_by', 'Dropdown', 
                                                'choice', $common_l10n['order_by'], '', 'name', $orderby_options ) );

        if ( $taxonomy->hierarchical ) {
            $taxonomy_section->addField( new Field( 'hierarchical', 'Dropdown', 'choice', $common_l10n['style'], 
                                                    '', '1', $list_style_options ) );
        }

        $taxonomy_section->addField( new Field( 'exclude', 'TextField', 
                                                'list_of_ids', $common_l10n['exclude'][0], $common_l10n['exclude'][1], 
                                                '' ) );
        $this->registerSection( $taxonomy_section );
    }
}

if ( $load_all_sections || $this->plugin->isContentTypeIncluded( 'authors', 'site_tree' ) ) {
	$this->registerSection( new Section( __( "Authors' Pages", 'sitetree' ), 'authors', array(
	    new Field( 'title', 'TextField', 'inline_html', $common_l10n['title'], '', __( 'Authors', 'sitetree' ) ),
	    new Field( 'show_count', 'Checkbox', 'bool', $common_l10n['show_count'],
	        __( 'Show the number of posts published by each author.', 'sitetree' ), true
	    ),
	    new Field( 'show_avatar', 'Checkbox', 'bool', __( 'Avatar', 'sitetree' ), __("Show the author's avatar.", 'sitetree' ) ),
	    new Field( 'avatar_size', 'NumberField', 'positive_number', __( 'Avatar size', 'sitetree' ), 
	        __( 'Choose a value between 20px and 512px.', 'sitetree' ), 60, array( 'min_value' => 20, 'max_value' => 512 )
	    ),
	    new Field( 'show_bio', 'Checkbox', 'bool', __( 'Biographical info', 'sitetree' ), 
	        sprintf( __('Show the biographical information set in the author&apos;s %1$sprofile page%2$s.', 'sitetree' ), 
	                 '<a href="' . admin_url( 'users.php' ) . '">', '</a>' )
	    ),
	    new Field( 'order_by', 'Dropdown', 'choice', $common_l10n['order_by'], '', 'display_name', array(
	        'display_name'  => __( 'Name', 'sitetree' ),
	        'posts_count'   => __( 'Published posts', 'sitetree' )
	    )),
        new Field( 'exclude', 'TextField', 'list_of_nicknames', $common_l10n['exclude'][0], 
            __( 'Comma-separated list of nicknames.', 'sitetree' ), ''
        )
	) ));
}
?>