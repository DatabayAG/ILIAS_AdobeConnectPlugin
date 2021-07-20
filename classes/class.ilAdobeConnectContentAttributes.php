<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Meeting content attributes
 *
 * @author Felix Paulano
 */
class ilAdobeConnectContentAttributes
{
    
    /**
     *  array of attributes
     *
     * @var array
     */
    private $attributes;
    
    /**
     *  Constructor
     *
     * @param array $attributes
     */
    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }
    
    /**
     *  Return $attributes attribute
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
    
    /**
     *  Returns an attribute specified by $name
     *
     * @param String $name
     * @return String
     */
    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }
    
    /**
     *  Check if all attributes matching all search criteria
     *
     * @param array $search_criteria
     * @return boolean
     */
    public function match($search_criteria)
    {
        foreach ($search_criteria as $key => $attribute) {
            if ($attribute != $this->attributes[$key]) {
                return false;
            }
        }
        return true;
    }
}