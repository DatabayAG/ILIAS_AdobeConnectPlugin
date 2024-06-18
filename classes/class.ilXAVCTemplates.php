<?php

class ilXAVCTemplates
{
    public const TPL_DEFAULT = 'std';
    public const TPL_EXTENDED = 'ext';

    public const XAVC_TEMPLATES = [self::TPL_DEFAULT, self::TPL_EXTENDED];

    private static array $instance = [];
    private ilDBInterface $db;
    private string $type;
    private string $start_date;
    private string $start_date_hide;
    private string $duration;
    private string $duration_hide;
    private string $reuse_existing_rooms;
    private string $reuse_existing_rooms_hide;
    private string $access_level;
    private string $access_level_hide;
    private string $lang_var;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getStartDate(): string
    {
        return $this->start_date;
    }

    public function setStartDate(string $start_date): void
    {
        $this->start_date = $start_date;
    }

    public function getStartDateHide(): string
    {
        return $this->start_date_hide;
    }

    public function setStartDateHide(string $start_date_hide): void
    {
        $this->start_date_hide = $start_date_hide;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): void
    {
        $this->duration = $duration;
    }

    public function getDurationHide(): string
    {
        return $this->duration_hide;
    }

    public function setDurationHide(string $duration_hide): void
    {
        $this->duration_hide = $duration_hide;
    }

    public function getReuseExistingRooms(): string
    {
        return $this->reuse_existing_rooms;
    }

    public function setReuseExistingRooms(string $reuse_existing_rooms): void
    {
        $this->reuse_existing_rooms = $reuse_existing_rooms;
    }

    public function getReuseExistingRoomsHide(): string
    {
        return $this->reuse_existing_rooms_hide;
    }

    public function setReuseExistingRoomsHide(string $reuse_existing_rooms_hide): void
    {
        $this->reuse_existing_rooms_hide = $reuse_existing_rooms_hide;
    }

    public function getAccessLevel(): string
    {
        return $this->access_level;
    }

    public function setAccessLevel(string $access_level): void
    {
        $this->access_level = $access_level;
    }

    public function getAccessLevelHide(): string
    {
        return $this->access_level_hide;
    }

    public function setAccessLevelHide(string $access_level_hide): void
    {
        $this->access_level_hide = $access_level_hide;
    }

    public function getLangVar(): string
    {
        return $this->lang_var;
    }

    private function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
    }

    public static function _getInstanceByType($type = self::TPL_DEFAULT): ilXAVCTemplates
    {
        if (!array_key_exists($type, self::$instance) || self::$instance[$type] instanceof(ilXAVCTemplates::class)) {
            $instance = new self();
            $instance->doRead($type);
            self::$instance[$type] = $instance;
        }

        return self::$instance[$type];
    }

    private function doRead(string $type): void
    {
        $res = $this->db->queryF(
            'SELECT * FROM rep_robj_xavc_tpl WHERE type = %s',
            ['string'],
            [$type]
        );

        while ($row = $this->db->fetchAssoc($res)) {
            $this->type = $row['type'];

            switch ($row['type']) {
                case self::TPL_EXTENDED:
                    $this->lang_var = 'cb_extended';
                    break;
                default:
                case self::TPL_DEFAULT:
                    $this->lang_var = 'cb_simple';
                    break;
            }

            switch($row['setting']) {
                case 'start_date':
                    $this->start_date = $row['value'];
                    $this->start_date_hide = $row['hide'];
                    break;

                case 'duration':
                    $this->duration = $row['value'];
                    $this->duration_hide = $row['hide'];
                    break;

                case 'reuse_existing_rooms':
                    $this->reuse_existing_rooms = $row['value'];
                    $this->reuse_existing_rooms_hide = $row['hide'];
                    break;

                case 'access_level':
                    $this->access_level = $row['value'];
                    $this->access_level_hide = $row['hide'];
                    break;

            }
        }
    }
}
