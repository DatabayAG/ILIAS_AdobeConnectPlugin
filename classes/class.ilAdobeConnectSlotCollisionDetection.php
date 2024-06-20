<?php

class ilAdobeConnectSlotCollisionDetection
{
    
    private int $maxConcurrent = 0;
    
    private array $usedTimeslots = [];
    
    private int $minDuration = 2;
    
    /**
     * @param int $maxConcurrent the max number of concurrent blocked timeslots at any time
     */
    public function __construct($maxConcurrent)
    {
        $this->maxConcurrent = (int) $maxConcurrent;
    }
    
    /**
     * adds a already blocked timeslot before processing the availability check
     * @param integer $from unix timestamp for starting time
     * @param integer $to unix timestamp for ending time
     */
    public function addUsedTimeslot($from, $to)
    {
        $this->usedTimeslots[] = array(
            'from' => $from,
            'to' => $to,
        );
        
        $delta = $to - $from;
        if ($delta < $this->minDuration) {
            $this->minDuration = $delta;
        }
    }
    
    /**
     * returns < 0 if $a['from'] is lower than $b['from'], 0 if they are
     * equal or > 0 if $a['from'] is greater than $b['from'].
     * Used to sort an array from small to large by the 'from' field
     * @param array $a
     * @param array $b
     * @return integer
     */
    private function sort(array $a, array $b)
    {
        return $a['from'] - $b['from'];
    }
    
    /**
     * checks if the given time period identified by $from and $to is valid (in a
     * way that the number of the max. concurrent slots is not exceeded).
     * @param integer $from unix timestamp for starting time
     * @param integer $to unix timestamp for ending time
     * @return boolean
     */
    public function checkAvailablitity($from, $to)
    {
        $stepSize = (int) $this->minDuration / 2;
        
        if ($stepSize < 1) {
            $stepSize = 1;
        }
        
        usort($this->usedTimeslots, array($this, 'sort'));
        
        $currentTime = $from;
        
        while ($currentTime <= $to) {
            
            $blockedSlots = 0;
            
            foreach ($this->usedTimeslots as $used) {
                // array is sorted... if any "from" is greater than $current, the following are as well
                if ($used['from'] > $currentTime) {
                    break;
                } else {
                    if ($used['to'] < $currentTime) {
                        continue;
                    } else {
                        $blockedSlots++;
                    }
                }
            }
            
            if ($blockedSlots >= $this->maxConcurrent) {
                return false;
            }
            
            $currentTime += $stepSize;
            
        }
        return true;
    }
}