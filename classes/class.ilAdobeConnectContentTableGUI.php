<?php
/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

class ilAdobeConnectContentTableGUI extends ilTable2GUI
{
    public const MODE_VIEW = 1;
    public const MODE_EDIT = 2;
    protected array $visibleOptionalColumns = [];
    protected array $optionalColumns = [];

    private int $viewMode = 1;
    private string $template_context = '';

    public function __construct(
        ?object $a_parent_obj,
        string $a_parent_cmd = "",
        string $a_template_context = "",
        $view_mode
    ) {
        $this->setId('xavc_cnt_' . $a_parent_obj->object->getId() . '_' . $view_mode);
        $this->setPrefix('xavc_cnt_' . $a_parent_obj->object->getId() . '_' . $view_mode);
        $this->viewMode = $view_mode;
        $this->template_context = $a_template_context;

        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
        // Add general table configuration here
    }

    /**
     * Set the view mode of this table, either <code>ilAdobeConnectContentTableGUI::MODE_VIEW</code>
     * or <code>ilAdobeConnectContentTableGUI::MODE_EDIT</code>
     */
    public function setViewMode(int $mode): ilAdobeConnectContentTableGUI
    {
        $this->viewMode = $mode;
        return $this;
    }

    public function getViewMode(): int
    {
        return $this->viewMode;
    }

    public function init(): ilAdobeConnectContentTableGUI
    {
        $this->setFormAction($this->parent_obj->ctrl->getFormAction($this->parent_obj, 'showContent'));

        $this->addColumn($this->parent_obj->pluginObj->txt('content_name'), 'title', '50%');

        $this->optionalColumns = (array) $this->getSelectableColumns();
        $this->visibleOptionalColumns = (array) $this->getSelectedColumns();
        foreach ($this->visibleOptionalColumns as $column) {
            $this->addColumn($this->optionalColumns[$column]['txt'], $column);
        }
        if ($this->viewMode == self::MODE_EDIT) {
            $this->addColumn($this->parent_obj->lng->txt('actions'), 'actions', '1%');
        }

        $this->setRowTemplate('tpl.meeting_content_row.html', $this->parent_obj->pluginObj->getDirectory());

        $this->setDefaultOrderField('type');
        $this->setDefaultOrderDirection('desc');

        $this->setShowRowsSelector(true);
        return $this;
    }

    public function getSelectableColumns(): array
    {
        $cols = [
            'type' => ['txt' => $this->parent_obj->pluginObj->txt('content_type'), 'default' => false],
            'date_created' => [
                'txt' => $this->parent_obj->pluginObj->txt('content_date_created'),
                'default' => false
            ]
        ];

        return $cols;
    }

    protected function formatCellValue($column, array $row)
    {
        if ($column == 'date_created') {
            return ilDatePresentation::formatDate(new ilDateTime($row['date_created'], IL_CAL_UNIX));
        }
        return $row[$column];
    }

    public function fillRow(array $a_set): void
    {
        foreach ($a_set as $key => $value) {
            $value = $this->formatCellValue($key, array($key => $value));
            if (array_key_exists($key, $this->optionalColumns)) {
                if (!$this->isColumnVisible($key)) {
                    continue;
                }

                $this->tpl->setCurrentBlock('optional_column');

                if ((string) $value === '') {
                    $this->tpl->touchBlock('optional_column');
                } else {
                    $this->tpl->setVariable('OPTIONAL_COLUMN_VAL', $value);
                }

                $this->tpl->parseCurrentBlock();
            } else {
                $this->tpl->setVariable(strtoupper($key), $this->formatField($key, $value));
            }
        }
    }

    protected function formatField(string $field, string $content): string
    {
        switch ($field) {
            case 'date_created':
                $content = ilDatePresentation::formatDate(new ilDateTime($content, IL_CAL_UNIX));
                break;
        }

        return $content;
    }

    public function numericOrdering(string $a_field): bool
    {
        switch ($a_field) {
            case 'date_created':
                return true;
        }

        return false;
    }

    protected function isColumnVisible(string $column): bool
    {
        if (array_key_exists($column, $this->optionalColumns) && !isset($this->visibleOptionalColumns[$column])) {
            return false;
        }

        return true;
    }
}