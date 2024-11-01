<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
class PageView extends View {
	/**
	 * @since 5.0
     * @v`ar object
	 */
	protected $delegate;
	
	/**
	 * @since 5.0
     * @var array
	 */
	protected $sections;
	
	/**
	 * @since 5.0
     * @var object
	 */
	protected $displayingSection;
	
	/**
	 * @since 5.0
     * @var object
	 */
	protected $field;

    /**
     * @since 5.0
     * @var object
     */
    protected $fieldView;

    /**
     * @since 5.0
     * @return object
     */
    public function getDisplayingSection() {
        return $this->displayingSection;
    }

    /**
     * @since 5.0
     * @param array $sections
     */
    public function setSections( $sections ) {
        $this->sections = $sections;
    }

    /**
     * @since 5.0
     * @param PageViewDelegateProtocol $delegate
     */
    public function setDelegate( PageViewDelegateProtocol $delegate ) {
        $this->delegate = $delegate;
    }
	
	/**
	 * @since 5.0
	 */
	public function display() {
		ob_start();
		
        echo '<div class="wrap">',
			 '<h1>', $this->viewData->title(), '</h1>';

		echo $this->delegate->pageViewWillDisplayForm( $this );

		$this->displayForm();

        echo '</div>';
		
		ob_end_flush();
	}
	
	/**
	 * @since 5.0
     * @param string $form_id
	 */
	protected function displayForm() {
        $action = $this->delegate->pageViewFormAction( $this );
        
        echo '<form method="post">';
        
        wp_nonce_field( $action, 'sitetree_nonce', true );
        
        echo '<input type="hidden" name="action" value="', $action, '">',
             '<input type="hidden" name="sitetree_page" value="', $this->viewData->id(), '">';

		$this->displayFormContent();
		
		echo '</form>';
	}
	
	/**
	 * @since 5.0
	 */
	protected function displayFormContent() {
        foreach ( $this->sections as $this->displayingSection ) {
            $this->displaySection();
        }

        submit_button();
    }

    /**
     * @since 5.0
     */
    protected function displaySection() {
        $fields        = $this->displayingSection->fields();
        $section_title = $this->displayingSection->title();
        $section_id    = $this->displayingSection->id();

        if ( $section_title ) {
            echo '<h2 class="title">', $section_title, '</h2>';
        }
            
        echo '<table class="form-table"><tbody>';
        
        foreach ( $fields as $this->field ) {
            echo '<tr valign="top"><th scope="row">';
            echo $this->field->title(), '</th><td>';
            
            if ( $this->field instanceof Fieldset ) {
                $fieldset       = $this->field;
                $grouped_fields = $fieldset->fields();
                $line_ending    = ( ( $fieldset->isSortable() || $fieldset->isInline() ) ? "\n" : '<br>' );
                
                echo '<div class="sitetree-fieldset-container"><fieldset';

                if ( $fieldset->id() ) {
                    $fieldset_id = $fieldset->id();

                    echo ' id="', str_replace( '_', '-', $fieldset_id ), '-fieldset"';
                }
                else {
                    $fieldset_id = $section_id;
                }

                echo '>';

                foreach ( $grouped_fields as $this->field ) {
                    $this->loadFieldView( $fieldset_id );
                    $this->fieldView->display();

                    echo $line_ending;
                }

                echo '</fieldset></div>';

                $description = $fieldset->description();

                if ( $description ) {
                    echo '<p><small>', $description, '</small></p>';
                }
            }
            else {
            	$this->loadFieldView( $section_id );
		        $this->fieldView->display();
            }

            echo '</td></tr>';
        }
        
        echo '</tbody></table>';
	}

	/**
	 * @since 5.0
	 */
	protected function loadFieldView( $section_id ) {
		$value = $this->delegate->pageViewFieldValue( $this->field, $section_id );
		
        $this->fieldView = FieldView::makeView( $this->field );
		$this->fieldView->init( $value, $section_id );
	}
}
?>