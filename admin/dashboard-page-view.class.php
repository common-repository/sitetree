<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class DashboardPageView extends PageView {
    /**
     * @since 6.0
     * @var array
     */
    private $metrics = array();
    
    /**
     * @since 6.0
     * @var int
     */
    private $numOfMetrics = 0;

    /**
     * @since 5.0
     * @var array
     */
    private $toolbarConfig = array(
        'view_url'        => '',
        'config_mode_url' => '',
        'settings_url'    => '',
        'submit_title'    => ''
    );

    /**
     * @since 6.0
     * @var string
     */
    private $metricsFreshnessMsg;

    /**
     * @since 6.0
     * @param string $time_since
     */
    public function setMetricsFreshness( $time_since ) {
        $this->metricsFreshnessMsg = sprintf( __( 'Info updated %s.', 'sitetree' ), $time_since );
    }
    
    /**
     * @since 6.0
     *
     * @param string $title
     * @param int|string $value
     * @param string $tooltip
     */
    public function registerMetric( $title, $value, $tooltip = '' ) {
        $metric = array(
            'title'   => $title,
            'value'   => $value,
            'tooltip' => $tooltip
        );

        $metric['can_display'] = ( ( $value >= 0 ) && ( $value != '0s' ) );

        $this->metrics[]     = $metric;
        $this->numOfMetrics += 1;
    }

    /**
     * @since 6.0
     */
    private function resetMetrics() {
        $this->metrics      = array();
        $this->numOfMetrics = 0;
    }
    
    /**
     * @since 5.0
     * @param array $config
     */
    public function configureToolbar( $config ) {
        $this->toolbarConfig = array_merge( $this->toolbarConfig, $config );
    }

    /**
     * @since 5.0
     */
    public function formID() {
        return $this->displayingSection->id();
    }
    
    /**
     * @see parent::displayForm()
     * @since 5.0
     */
    protected function displayForm() {
        echo '<div id="sitetree-dashboard-wrapper" class="sitetree-self-clear"><div id="sitetree-dashboard">';
        
        foreach ( $this->sections as $this->displayingSection ) {
            $form_id = $this->formID();

            echo '<div id="sitetree-', $form_id, '-dashform-area" class="sitetree-dashform-area">';

            parent::displayForm();

            /**
             * @since 5.0
             */
            do_action( 'sitetree_dashboard_page_view_did_display_form', $form_id, $this, $this->delegate );

            echo '</div>';
        }
        
        echo '</div>';

        echo $this->delegate->dashboardDidDisplayForms();

        echo '</div>';
    }
    
    /**
     * @see parent::displayFormContent()
     * @since 5.0
     */
    protected function displayFormContent() {
        $form_id = $this->formID();
        
        echo '<input type="hidden" name="sitetree_form_id" value="', $form_id, '">',
             '<div class="sitetree-toolbar"><span class="sitetree-tb-form-title">', $this->displayingSection->title(), '</span>';

        $this->delegate->dashboardWillDisplayToolbarButtons( $this, $form_id );

        if ( $this->delegate->dashboardCanDisplayMetrics( $this, $form_id ) ) {
            $last_metric_index = $this->numOfMetrics - 1;
            
            if ( $this->toolbarConfig['settings_url'] ) {
                echo '<a href="', $this->toolbarConfig['settings_url'], '" class="sitetree-tb-btn sitetree-corner-tb-btn">', 
                     __( 'Settings', 'sitetree' ), '</a>';
            }
            
            echo '<a href="', $this->toolbarConfig['config_mode_url'], '" class="sitetree-tb-btn';

            if (! $this->toolbarConfig['settings_url'] ) {
                echo ' sitetree-corner-tb-btn';  
            }
            
            echo '">', __( 'Configure', 'sitetree' ), '</a>',
                 '<a href="', $this->toolbarConfig['view_url'], '" class="sitetree-tb-btn" target="sitetree_', $form_id, '">',
                 __( 'View', 'sitetree' ), '</a>',
                 '</div><div class="sitetree-metrics"><ul class="sitetree-metrics-list sitetree-self-clear';
            
            if ( $this->numOfMetrics != 4 ) {
                echo ' sitetree-', $this->numOfMetrics, '-metrics';
            }
                
            echo '">';

            $show_freshness_message = false;
            
            for ( $i = 0; $i < $this->numOfMetrics; $i++ ) {
                $metric_container_classes = 'sitetree-metric';

                if ( $this->metrics[$i]['can_display'] ) {
                    $show_freshness_message = true;
                    $metric_value             = $this->metrics[$i]['value'];

                    if ( $this->metrics[$i]['tooltip'] ) {
                        $metric_container_classes .= ' sitetree-metric-with-tooltip-container';
                        $metric_value = '<span class="sitetree-metric-with-tooltip" title="' . $this->metrics[$i]['tooltip']
                                    . '">' . $metric_value . '</span>';
                    }
                }
                else {
                    $metric_value = '-';
                }

                echo '<li><div class="sitetree-metric-container';
                
                if ( $i == $last_metric_index ) {
                    echo ' sitetree-last-metric';
                }
                
                echo '">', $this->metrics[$i]['title'], '<div class="', $metric_container_classes, 
                     '">', $metric_value, '</div></div></li>';
            }
            
            echo '</ul>';

            if ( $show_freshness_message ) {
                echo '<p class="sitetree-metrics-freshness">', $this->metricsFreshnessMsg, '</p>';
            }

            echo '</div>';

            $this->delegate->dashboardDidDisplayMetrics( $this, $form_id );    
            $this->resetMetrics();
        }
        else {
            echo '<input type="submit" id="sitetree-primary-', $form_id, 
                 '-form-btn" class="sitetree-tb-btn sitetree-corner-tb-btn sitetree-primary-tb-btn" name="submit" value="',
                 $this->toolbarConfig['submit_title'], '"></div>';

            $this->displayingSection->setID( '' );
            $this->displayingSection->setTitle( '' );
            
            $this->displaySection();
        }
    }
}
?>