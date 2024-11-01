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

$pro_leaf = new Section( 'Wonder Leaf', 'wonder' );
$pro_leaf->setDescription( 'Ever-growing collection of little gems.' );

$this->registerSection( $pro_leaf );

$demux_leaf = new Section( 'Multilingual Leaf', 'multilingual' );
$demux_leaf->setDescription( 'Sitemaps for multilingual websites powered by WPML.' );
    
$this->registerSection( $demux_leaf );
?>