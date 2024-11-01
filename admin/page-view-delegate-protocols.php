<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
interface PageViewDelegateProtocol {
    /**
     * @since 5.0
     * @param object $pageView
     */
    public function pageViewWillDisplayForm( $pageView );

    /**
     * @since 5.0
     *
     * @param object $field
     * @param string $section_id
     * @return mixed
     */
    public function pageViewFieldValue( $field, $section_id );

    /**
     * @since 5.0
     *
     * @param object $pageView
     * @return string
     */
    public function pageViewFormAction( $pageView );
}


/**
 * @since 5.0
 */
interface DashboardDelegateProtocol {
    /**
     * @since 5.0
     *
     * @param object $dashboardPageView
     * @param string $form_id
     */
    public function dashboardWillDisplayToolbarButtons( $dashboardPageView, $form_id );

    /**
     * @since 6.0
     *
     * @param object $dashboardPageView
     * @param string $form_id
     * @return bool
     */
    public function dashboardCanDisplayMetrics( $dashboardPageView, $form_id );

    /**
     * @since 6.0
     *
     * @param object $dashboardPageView
     * @param string $form_id
     */
    public function dashboardDidDisplayMetrics( $dashboardPageView, $form_id );

    /**
     * @since 5.0
     * @return string
     */
    public function dashboardDidDisplayForms();
}

/**
 * @since 5.0
 */
interface LeavesPageDelegateProtocol {
    /**
     * @since 6.0
     *
     * @param object $leavesPageView
     * @param string $leaf_keyword
     * @return string
     */
    public function leavesPageViewNeedsLeafURL( $leavesPageView, $leaf_keyword );

    /**
     * @since 6.0
     *
     * @param object $leavesPageView
     * @param string $leaf_keyword
     * @return bool
     */
    public function leavesPageViewCanShowLeafActiveBadge( $leavesPageView, $leaf_keyword );

    /**
     * @since 6.0
     *
     * @param object $leavesPageView
     * @return string
     */
    public function leavesPageViewIsDisplayingPassBox( $leavesPageView );
}