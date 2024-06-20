<?php

class ilAdobeConnectParticipantsNullObject extends ilParticipants
{
    public function __construct()
    {
    }
    
    public function getParticipants(): array
    {
        return [];
    }
    
    public function getMembers(): array
    {
        return [];
    }
    
    public function getAdmins(): array
    {
        return [];
    }
    
    public function getTutors(): array
    {
        return [];
    }
}
