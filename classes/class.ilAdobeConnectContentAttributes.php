<?php

class ilAdobeConnectContentAttributes
{
    private array $attributes = [];
    
    public function __construct($attributes)
    {
        $this->attributes = (array) $attributes;
    }
    
    public function getAttributes(): array
    {
        return $this->attributes;
    }
    
    public function getAttribute(string $name): string
    {
        return (string) $this->attributes[$name];
    }
    
    /**
     *  Check if all attributes matching all search criteria
     */
    public function match(array $search_criteria): bool
    {
        foreach ($search_criteria as $key => $attribute) {
            if ($attribute != $this->attributes[$key]) {
                return false;
            }
        }
        return true;
    }
}
