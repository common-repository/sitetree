<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
abstract class View {
    /**
     * @since 5.0
     * @var object
     */
    protected $viewData;

    /**
     * @since 5.0
     *
     * @param object $viewData
     * @return object
     */
    public static function makeView( $viewData ) {
        $base_class     = __CLASS__;
        $view_class     = __NAMESPACE__ . '\\' . $viewData->viewClass();
        $view           = new $view_class;
        $view->viewData = $viewData;

        if ( $view instanceof $base_class ) {
            return $view;
        }

        $message = __METHOD__ . '() cannot create objects of class ' . $view_class;
        
        trigger_error( $message, E_USER_ERROR );
    }

    /**
     * @since 5.0
     */
    private function __construct() {}

    /**
     * @since 5.0
     */
    abstract public function display();
}


/**
 * @since 5.0
 */
abstract class FieldView extends View {
	/**
	 * @since 5.0
     * @var string
	 */
	protected $id = 'sitetree-';
    
	/**
	 * @since 5.0
     * @var string
	 */
	protected $name = 'sitetree';
	
	/**
	 * @since 5.0
     * @var string
	 */
	protected $value;
	
	/**
	 * @since 5.0
     *
     * @param mixed $value
     * @param string $section_id
	 */
	public function init( $value, $section_id = '' ) {
        $raw_id = $this->viewData->id();

        $this->value = $value;
        
        if ( $section_id ) {
            $this->name .= '[' . $section_id . ']';
            $this->id   .= $section_id . '-' . $raw_id;
        }
        else {
            $this->id .= $raw_id;
        }
            
        $this->name .= '[' . $raw_id . ']';
        $this->id    = str_replace( '_', '-', $this->id );
    }
	
	/**
	 * @since 5.0
	 */
	public function display() {
		$this->displayField();
		$this->displayTooltip();
	}

	/**
	 * @since 5.0
	 */
	abstract protected function displayField();

	/**
	 * @since 5.0
	 */
	protected function displayTooltip() {
        if (! $this->viewData->tooltip ) {
            return false;
        }

        echo "\n";

        if ( 
            ( $this->viewData->viewClass() != 'Checkbox' ) && 
            preg_match( '/^\p{Lu}/u',  $this->viewData->tooltip )
        ) {
            echo '<span class="description">', $this->viewData->tooltip, '</span>';
        }
        else {
            echo '<label for="', $this->id, '">', $this->viewData->tooltip, '</label>';
        }
    }
}


/**
 * @since 5.0
 */
class Checkbox extends FieldView {
	/**
	 * @see parent::displayField()
     * @since 5.0
	 */
	protected function displayField() {
        echo '<input type="checkbox" id="', $this->id, '" name="', $this->name, 
             '" value="1"', checked( true, $this->value, false ), '>';
    }
}


/**
 * @since 5.0
 */
class MetaCheckbox extends Checkbox {
    /**
     * @since 5.0
     */
    public function display() {
        echo '<label>';

        $this->displayField();

        echo '&nbsp;', $this->viewData->tooltip, '</label>';
    }
}


/**
 * @since 5.0
 */
class Dropdown extends FieldView {
	/**
	 * @see parent::displayField()
     * @since 5.0
	 */
	protected function displayField() {
        echo '<select id="', $this->id, '" name="', $this->name, '">';
        
        foreach ( $this->viewData->moreData as $value => $label ) {
            echo '<option value="', esc_attr( $value ), '"', selected( $value, $this->value, false ), 
                 '>', $label, '</option>';
        }
        
        echo '</select>';
    }
}


/**
 * @since 5.0
 */
class TextField extends FieldView {
	/**
	 * @see parent::displayField()
     * @since 5.0
	 */
	protected function displayField() {
        echo '<input type="text" id="', $this->id, '" name="', $this->name,
             '" value="', esc_html( $this->value ), '" class="regular-text">';
    }
}


/**
 * @since 5.0
 */
class NumberField extends FieldView {
	/**
	 * @see parent::displayField()
     * @since 5.0
	 */
	protected function displayField() {
        echo '<input type="number" id="', $this->id, '" name="', $this->name, 
             '" value="', esc_attr( $this->value ), '"';

        if ( isset( $this->viewData->conditions['min_value'] ) ) {
            echo ' min="', $this->viewData->conditions['min_value'], '"';
        }

        if ( isset( $this->viewData->conditions['max_value'] ) ) {
            echo ' max="', $this->viewData->conditions['max_value'], '"';
        }
        
        echo ' class="small-text">';
    }
}
?>