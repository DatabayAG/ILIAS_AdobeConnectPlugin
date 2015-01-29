<?php
/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Table/classes/class.ilTable2GUI.php';
require_once 'Services/Calendar/classes/class.ilDatePresentation.php';

/**
 *
 * Inherited Table2GUI
 *
 * @author Michael Jansen <mjansen@databay.de>
 *
 */
class ilAdobeConnectRecordsTableGUI extends ilTable2GUI
{
	const MODE_VIEW = 1;
	const MODE_EDIT = 2;
	protected $visibleOptionalColumns = array();
	protected $optionalColumns = array();
	/**
	 *
	 * View mode
	 *
	 * @var integer
	 *
	 */
	private $viewMode = self::MODE_VIEW;
	/**
	 * @var string
	 */
	private $template_context = '';

	/**
	 *
	 * Constructor
	 *
	 * @param ilObjectGUI $a_parent_obj
	 * @param string $a_parent_cmd
	 * @param string $a_template_context
	 *
	 * @access public
	 *
	 */
	public function __construct($a_parent_obj, $a_parent_cmd = '', $a_template_context = '', $view_mode)
	{
		$this->setId('xavc_cnt_'.$a_parent_obj->object->getId().'_'.$view_mode);
		$this->setPrefix('xavc_cnt_'.$a_parent_obj->object->getId().'_'.$view_mode);
		$this->viewMode = $view_mode;
		$this->template_context = $a_template_context;

		parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);

		// Add general table configuration here
	}

	/**
	 *
	 * Set the view mode of this table, either <code>ilAdobeConnectRecordsTableGUI::MODE_VIEW</code>
	 * or <code>ilAdobeConnectRecordsTableGUI::MODE_EDIT</code>
	 *
	 * @param integer $mode
	 * @return ilAdobeConnectRecordsTableGUI
	 * @access public
	 *
	 */
	public function setViewMode($mode)
	{
		$this->viewMode = $mode;
		return $this;
	}

	/**
	 *
	 * Get the view mode of this table
	 *
	 * @return integer
	 *
	 */
	public function getViewMode()
	{
		return $this->viewMode;
	}

	/**
	 *
	 * Init the table with some configuration
	 *
	 * @return ilAdobeConnectRecordsTableGUI
	 * @access public
	 *
	 */
	public function init()
	{
		/**
		 * @var $this->parent_obj->ctrl $ilCtrl
		 */

		$this->setFormAction($this->parent_obj->ctrl->getFormAction($this->parent_obj, 'showContent'));

		$this->addColumn($this->parent_obj->pluginObj->txt('content_name'), 'title', '50%');
		$this->addColumn($this->parent_obj->pluginObj->txt('content_type'), 'type');
		$this->addColumn($this->parent_obj->pluginObj->txt('content_date_created'), 'date_created');
		if($this->viewMode == self::MODE_EDIT)
		{
			$this->addColumn($this->parent_obj->lng->txt('actions'), 'actions', '1%');	
		}

		$this->setRowTemplate('tpl.meeting_record_row.html', $this->parent_obj->pluginObj->getDirectory());

		$this->setDefaultOrderField('type');
		$this->setDefaultOrderDirection('desc');

		$this->setShowRowsSelector(true);
		return $this;
	}

	/**
	 * Define a final formatting for a cell value
	 * @param mixed  $column
	 * @param array  $row
	 * @return mixed
	 */
	protected function formatCellValue($column, array $row)
	{
		return $row[$column];
	}
	/**
	 *
	 * @see ilTable2GUI::fillRow()
	 *
	 */
	public function fillRow($a_set)
	{
		foreach($a_set as $key => $value)
		{
			$value = $this->formatCellValue($key, array($key => $value));
			$this->tpl->setVariable(strtoupper($key), $this->formatField($key, $value));
		}
	}

	/**
	 *
	 * Formats a field and returns it
	 *
	 * @param string $field
	 * @param string $content
	 * @return string
	 * @access public
	 *
	 */
	protected function formatField($field, $content)
	{
		switch($field)
		{
			case 'date_created':
				$content = ilDatePresentation::formatDate(new ilDateTime($content, IL_CAL_UNIX));
				break;
		}

		return $content;
	}

	/**
	 *
	 * @see ilTable2GUI::numericOrdering()
	 *
	 */
	public function numericOrdering($field)
	{
		switch($field)
		{
			case 'date_created':
				return true;
		}

		return false;
	}
}