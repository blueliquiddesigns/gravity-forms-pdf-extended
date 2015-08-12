<?php

namespace GFPDF\Helper\Fields;

use GFPDF\Helper\Helper_Fields;

use GFFormsModel;

use Exception;

/**
 * Gravity Forms Field
 *
 * @package     Gravity PDF
 * @copyright   Copyright (c) 2015, Blue Liquid Designs
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0
 */

/* Exit if accessed directly */
if (! defined('ABSPATH')) {
    exit;
}

/*
    This file is part of Gravity PDF.

    Gravity PDF Copyright (C) 2015 Blue Liquid Designs

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * Controls the display and output of a Gravity Form field
 *
 * @since 4.0
 */
class Field_Survey extends Helper_Fields
{

    /**
     * Check the appropriate variables are parsed in send to the parent construct
     * @param Object $field The GF_Field_* Object
     * @param Array $entry The Gravity Forms Entry
     * @since 4.0
     */
    public function __construct($field, $entry) {
        global $gfpdf;

        /* call our parent method */
        parent::__construct($field, $entry);

        /*
         * Survey Field can be any of the following:
         * single line text, paragraph, dropdown, select, checkbox,
         * likert, rank or rating
         */
        $class = $gfpdf->misc->get_field_class($field->inputType);

        try {
            /* check load our class */
            if(class_exists($class)) {
                $this->fieldObject = new $class($field, $entry);
            } else {
                throw new Exception();
            }
        } catch(Exception $e) {
            /* Exception thrown. Load generic field loader */
            $this->fieldObject = new Field_Default($field, $entry);
        }

        /* force the fieldObject value cache */
        $this->value();
    }

    /**
     * Get the $form_data object
     * Survey field uses multiple field types so we need to account for that
     * @return Array
     * @since 4.0
     */
    private function get_form_data() {
        if( method_exists( $this->fieldObject, 'form_data' ) ) {
            return $this->fieldObject->form_data();
        }
        
        return parent::form_data();
    }

    /**
     * Used to check if the current field has a value
     * @since 4.0
     * @internal Child classes can override this method when dealing with a specific use case
     */
    public function is_empty() {
        return $this->fieldObject->is_empty();
    }

    /**
     * Return the HTML form data
     * @return Array
     * @since 4.0
     */
    public function form_data() {

        $data = array();

        /*
         * Provide backwards compatibility fixes to certain fields
         * TODO: allow standard 4.x layout in appropriate array key ($form_data[survey])
         */
        switch($this->field->inputType) {
            case 'radio':
            case 'select':
            
                $data  = $this->get_form_data();
                $value = $data['field'][ $this->field->id . '_name' ];

                /* Overriding survey radio values with name */
                array_walk( $data['field'], function(&$item, $key, $value) {
                    $item = $value;
                }, $value);

            break;

            case 'checkbox':
                $value = $this->get_value();

                /* Convert survey ID to real value */
                foreach( $this->field->choices as $choice ) {
                    
                    if( ( $key = array_search( $choice['value'], $value) ) !== false ) {
                        $value[ $key ] = $choice['text'];
                    }
                }

                $value = array( $value );
                $label = GFFormsModel::get_label($this->field);

                $data[ $this->field->id . '.' . $label ] = $value;
                $data[ $this->field->id ] = $value;
                $data[ $label ] = $value;

                $data = array( 'field' => $data );

            break;

            default:
                $data = $this->get_form_data();
            break;
        }
        

        return $data;
    }


    /**
     * Display the HTML version of this field
     * @return String
     * @since 4.0
     */
    public function html($value = '', $label = true) {
        echo $this->fieldObject->html();
    }

    /**
     * Get the standard GF value of this field
     * @return String/Array
     * @since 4.0
     */
    public function value() {
        if($this->fieldObject->has_cache()) {
            return $this->fieldObject->cache();
        }

        $value = $this->fieldObject->value();

        $this->fieldObject->cache($value);
        
        return $this->fieldObject->cache();
    }
}