<?php

class ilAdobeConnectContents
{
    private array $contents = [];
    
    public function __construct()
    {
        $this->contents = array();
    }
    
    public function addContent($attributes): void
    {
        $this->contents[] = new ilAdobeConnectContent($attributes);
    }
    
    public function search($search_criteria = null): array
    {
        $results = array();
        /**
         * @var $content  ilAdobeConnectContent
         */
        foreach ($this->contents as $content) {
            if ($search_criteria != null) {
                if ($content->getAttributes()->match($search_criteria)) {
                    $results[] = $content;
                }
            } else {
                $results[] = $content;
            }
        }
        return $results;
    }
    
    public function getContents(): array
    {
        return $this->contents;
    }
}
