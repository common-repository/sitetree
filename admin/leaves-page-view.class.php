<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class LeavesPageView extends PageView {
    /**
     * @see parent::displayForm()
     * @since 5.0
     */
    protected function displayForm() {
        parent::displayForm();

        echo '<ul id="sitetree-leaves" class="sitetree-self-clear">';

        foreach ( $this->sections as $this->displayingSection ) {
            $leaf_keyword = $this->displayingSection->id();
            $leaf_url     = $this->delegate->leavesPageViewNeedsLeafURL( $this, $leaf_keyword );

            echo '<li class="sitetree-box sitetree-leaf-box"><h3 class="sitetree-lb-title">',
                 '<a href="', $leaf_url, '">', $this->displayingSection->title(), 
                 '</a></h3><p class="sitetree-lb-description">', $this->displayingSection->description(), 
                 '</p><a href="', $leaf_url, '" class="sitetree-box-default-btn sitetree-lb-explore-btn">Explore</a>';
            
            if ( $this->delegate->leavesPageViewCanShowLeafActiveBadge( $this, $leaf_keyword ) ) {
                echo '<div class="sitetree-lb-active-status-badge">Active</div>';
            }

            echo '</li>';
        }

        echo '</ul>';
    }

    /**
     * @see parent::displayFormContent()
     * @since 5.0
     */
    protected function displayFormContent() {
        echo '<div id="sitetree-access-pass-box" class="sitetree-box"><h3 id="sitetree-apb-title">Your Access Pass</h3>';
        
        echo $this->delegate->leavesPageViewIsDisplayingPassBox( $this );
        
        echo '</div>';
    }
}
?>