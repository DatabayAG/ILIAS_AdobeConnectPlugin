<#1>
<?php
	if(!$ilDB->tableExists('rep_robj_xavc_data'))
	{
		$fields = array(
			'id' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => true
			),
			'sco_id' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false
			),
			'start_date' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			),
			'end_date' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			),
			'started' => array(
				'type' => 'integer',
				'length' => 1,
				'notnull' => false,
				'default'=> 0
			)
		);
		
		$ilDB->createTable("rep_robj_xavc_data", $fields);
		$ilDB->addPrimaryKey("rep_robj_xavc_data", array("id"));
	}
	
	
?>
<#2>
<?php
	if(!$ilDB->tableExists('rep_robj_xavc_settings'))
	{
		$fields = array(
			'keyword' => array(
				'type' => 'text',
				'length' => 50,
				'notnull' => true,
			),
			'value' => array(
				'type' => 'text',
				'length' => 4000,
				"notnull" => false,
				"default" => null
			));

		$ilDB->createTable("rep_robj_xavc_settings", $fields);
		$ilDB->addPrimaryKey('rep_robj_xavc_settings', array('keyword'));
	}
?>
<#3>
<?php
	if(!$ilDB->tableExists('rep_robj_xavc_users'))
	{
		$fields = array(
			'user_id' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			),
			'xavc_login' => array(
				'type' => 'text',
				'length' => 80,
				'notnull' => false
			)
		);
	
		$ilDB->createTable("rep_robj_xavc_users", $fields);
		$ilDB->addPrimaryKey("rep_robj_xavc_users", array("user_id"));
	}
?>
<#4>
<?php
	if(!$ilDB->tableExists('rep_robj_xavc_members'))
	{
		$fields = array(
			'user_id' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			),
			'ref_id' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			),
			'sco_id' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			),
			'xavc_status' => array(
				'type' => 'text',
				'length' => 80,
				'notnull' => false
			)
		);
	
		$ilDB->createTable("rep_robj_xavc_members", $fields);
		$ilDB->addPrimaryKey("rep_robj_xavc_members", array("user_id","ref_id"));
	}
?>
<#5>
<#6>
<#7>
<#8>
<#9>
<#10>
<#11>
<#12>
<#13>
<#14>
<#15>
<?php
	$next_id = $ilDB->nextId('adm_settings_template');
	$ilDB->insert('adm_settings_template',
	array('id' => array('integer', $next_id),
		'type' => array('text', 'xavc'),
		'title' => array('text', 'cb_simple')
	));

	$ilDB->insert('adm_set_templ_value',
	array('template_id' => array('integer', $next_id),
		'setting' => array('text', 'start_date'),
		'value' => array('text', 'now'),
		'hide' => array('integer', 1)
	));


	$ilDB->insert('adm_set_templ_value',
		array('template_id' => array('integer', $next_id),
			  'setting' => array('text', 'duration'),
			  'value' => array('text', '2'),
			  'hide' => array('integer', 1)
		));

	$ilDB->insert('adm_set_templ_value',
		array('template_id' => array('integer', $next_id),
			  'setting' => array('text', 'reuse_existing_rooms'),
			  'value' => array('text', '0'),
			  'hide' => array('integer', 1)
		));
?>
<#16>
<?php
	$next_id = $ilDB->nextId('adm_settings_template');
	$ilDB->insert('adm_settings_template',
		array('id' => array('integer', $next_id),
			  'type' => array('text', 'xavc'),
			  'title' => array('text', 'cb_extended')
		));

	$ilDB->insert('adm_set_templ_value',
		array('template_id' => array('integer', $next_id),
			  'setting' => array('text', 'start_date'),
			  'value' => array('text', 'now'),
			  'hide' => array('integer', 0)
		));

	$ilDB->insert('adm_set_templ_value',
		array('template_id' => array('integer', $next_id),
			  'setting' => array('text', 'duration'),
			  'value' => array('text', '2'),
			  'hide' => array('integer', 0)
		));
	
	$ilDB->insert('adm_set_templ_value',
		array('template_id' => array('integer', $next_id),
			  'setting' => array('text', 'reuse_existing_rooms'),
			  'value' => array('text', '0'),
			  'hide' => array('integer', 0)
		));
?>
<#17>
<?php

	if(!$ilDB->tableColumnExists('rep_robj_xavc_data', 'instructions'))
	{
		$ilDB->addTableColumn('rep_robj_xavc_data', 'instructions',
			array('type' => 'text',
				  'length' => 4000,
				  'notnull' => false,
				  'default' => null));
	}
?>
<#18>
<?php
	if(!$ilDB->tableColumnExists('rep_robj_xavc_data', 'permanent_room'))
	{
		$ilDB->addTableColumn('rep_robj_xavc_data', 'permanent_room',
			array(
				'type'    => 'integer',
				'length'  => 1,
				'notnull' => false,
				'default' => 0
			));
	}
?>
<#19>
<?php
	if(!$ilDB->tableExists('rep_robj_xavc_gloperm'))
	{
		$fields = array(
			'id' => array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			),
			'permission' => array(
				'type' => 'text',
				'length' => 80,
				'notnull' => false
			),
			'role' => array(
				'type' => 'text',
				'length' => 20,
				'notnull' => false
			),
			'has_access' => array(
				'type' => 'integer',
				'length' => 1,
				'notnull' => false,
				'default'=> 0
			)
		);

		$ilDB->createTable("rep_robj_xavc_gloperm", $fields);
		$ilDB->addPrimaryKey("rep_robj_xavc_gloperm", array("id"));
		$ilDB->createSequence("rep_robj_xavc_gloperm");
	}
?>
<#20>
<?php
//insert permission: upload content
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			'permission' => array('text', 'perm_upload_content'),
		'role' => array('text', 'host'),
		'has_access' =>array('integer', 1)
	));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_upload_content'),
				  'role' => array('text', 'mini-host'),
				  'has_access' =>array('integer', 1)));
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_upload_content'),
				  'role' => array('text', 'view'),
				  'has_access' =>array('integer', 0)));
	
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_upload_content'),
				  'role' => array('text', 'denied'),
				  'has_access' =>array('integer', 0)));
	
?>
<#21>
<?php
	//insert permission: upload content
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_change_role'),
				  'role' => array('text', 'host'),
				  'has_access' =>array('integer', 1)
		));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_change_role'),
				  'role' => array('text', 'mini-host'),
				  'has_access' =>array('integer', 1)));
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');

	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_change_role'),
				  'role' => array('text', 'view'),
				  'has_access' =>array('integer', 0)));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_change_role'),
				  'role' => array('text', 'denied'),
				  'has_access' =>array('integer', 0)));
?>
<#22>
<?php
	
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_edit_participants'),
				  'role' => array('text', 'host'),
				  'has_access' =>array('integer', 1)
		));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_edit_participants'),
				  'role' => array('text', 'mini-host'),
				  'has_access' =>array('integer', 1)));
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');

	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_edit_participants'),
				  'role' => array('text', 'view'),
				  'has_access' =>array('integer', 0)));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_edit_participants'),
				  'role' => array('text', 'denied'),
				  'has_access' =>array('integer', 0)));
?>
<#23>
<?php

$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_add_participants'),
			  'role' => array('text', 'host'),
			  'has_access' =>array('integer', 1)
	));

$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_add_participants'),
			  'role' => array('text', 'mini-host'),
			  'has_access' =>array('integer', 1)));
$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');

$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_add_participants'),
			  'role' => array('text', 'view'),
			  'has_access' =>array('integer', 0)));

$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_add_participants'),
			  'role' => array('text', 'denied'),
			  'has_access' =>array('integer', 0)));
?>
<#24>
	<?php

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_records'),
				  'role' => array('text', 'host'),
				  'has_access' =>array('integer', 1)
		));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_records'),
				  'role' => array('text', 'mini-host'),
				  'has_access' =>array('integer', 1)));
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');

	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_records'),
				  'role' => array('text', 'view'),
				  'has_access' =>array('integer', 1)));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_records'),
				  'role' => array('text', 'denied'),
				  'has_access' =>array('integer', 0)));
?>
<#25>	
<?php
	$res = $ilDB->queryF('SELECT id FROM adm_settings_template WHERE type = %s  AND title = %s',
		array('text', 'text'), array('xavc', 'cb_simple'));
	
	$row = $ilDB->fetchAssoc($res);
	$id = $row['id'];
	
	$ilDB->insert('adm_set_templ_value',
		array(
			'template_id' => array('integer', $id),
			'setting'     => array('text', 'access_level'),
			'value'       => array('text', ''),
			'hide'        => array('integer', 1)
		));

?>
<#26>
<?php
	$res = $ilDB->queryF('SELECT id FROM adm_settings_template WHERE type = %s  AND title = %s',
		array('text', 'text'), array('xavc', 'cb_extended'));
	
	$row = $ilDB->fetchAssoc($res);
	$id = $row['id'];
	
	$ilDB->insert('adm_set_templ_value',
		array(
			'template_id' => array('integer', $id),
			'setting'     => array('text', 'access_level'),
			'value'       => array('text', ''),
			'hide'        => array('integer', 0)
		));
?>
<#27>
<?php
if(!$ilDB->tableColumnExists('rep_robj_xavc_data', 'contact_info'))
{
	$ilDB->addTableColumn('rep_robj_xavc_data', 'contact_info',
		array('type' => 'text',
			  'length' => 4000,
			  'notnull' => false,
			  'default' => null));
}
?>
<#28>
<?php
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_contents'),
				  'role' => array('text', 'host'),
				  'has_access' =>array('integer', 1)
		));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_contents'),
				  'role' => array('text', 'mini-host'),
				  'has_access' =>array('integer', 1)));
	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');

	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_contents'),
				  'role' => array('text', 'view'),
				  'has_access' =>array('integer', 1)));

	$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
	$ilDB->insert('rep_robj_xavc_gloperm',
		array(	'id' => array('integer', $next_id),
				  'permission' => array('text', 'perm_read_contents'),
				  'role' => array('text', 'denied'),
				  'has_access' =>array('integer', 0)));
?>	
<#29>
<?php
if(!$ilDB->tableColumnExists('rep_robj_xavc_data', 'perm_read_contents'))
{
	$ilDB->addTableColumn('rep_robj_xavc_data', 'perm_read_contents',
	array(
			'type' => 'integer',
			'length' => 1,
			'notnull' => false,
			'default'=> 0
	));
}
?>
<#30>
<?php
if(!$ilDB->tableColumnExists('rep_robj_xavc_data', 'perm_read_records'))
{
	$ilDB->addTableColumn('rep_robj_xavc_data', 'perm_read_records',
		array(
			'type' => 'integer',
			'length' => 1,
			'notnull' => false,
			'default'=> 0
		));
}
?>
<#31>
<?php
	// migration-step
	$res = $ilDB->queryF('
		SELECT permission, has_access 
		FROM rep_robj_xavc_gloperm 
		WHERE (permission = %s
		OR permission = %s)
		AND role = %s',
	array('text', 'text', 'text'), 
	array('perm_read_records', 'perm_read_contents', 'view'));
	
	while($row = $ilDB->fetchAssoc($res))
	{
		$permissions[$row['permission']] = $row['has_access']; 
	}

	$ilDB->manipulateF('
	UPDATE rep_robj_xavc_data 
	SET perm_read_contents = %s,
		perm_read_records = %s',
	array('integer', 'integer'), 
	array((int)$permissions['perm_read_contents'],(int)$permissions['perm_read_records']));
?>	
<#32>
<?php
	if(!$ilDB->tableColumnExists('rep_robj_xavc_data', 'folder_id'))
	{
		$ilDB->addTableColumn('rep_robj_xavc_data', 'folder_id',
			array(
				'type' => 'integer',
				'length' => 8,
				'notnull' => false,
				'default'=> 0
			));
	}
?>	
<#33>	
<?php
	// migration step
	$res = $ilDB->query('SELECT * FROM rep_robj_xavc_settings');
	$settings = array();
	while($row = $ilDB->fetchAssoc($res))
	{
		$settings[$row['keyword']] = $row['value'];
	}

	if($settings['login'] && $settings['password'])
	{
		//check connection
		$xmlAPI = ilXMLApiFactory::getApiByAuthMode();
		$session = $xmlAPI->getBreezeSession();
		
		if($session && $xmlAPI->login($settings['login'], $settings['password'], $session))
		{
			$folder_id = $xmlAPI->getShortcuts("my-meetings", $session);
	
			$ilDB->update('rep_robj_xavc_data',
				array('folder_id' => array('integer', (int)$folder_id)),
				array('folder_id' => array('integer', 0)));
		}
	}
?>
<#34>
<?php
if(!$ilDB->tableColumnExists('rep_robj_xavc_data', 'url_path'))
{
	$ilDB->addTableColumn('rep_robj_xavc_data', 'url_path',
		array('type' => 'text',
			'length' => 80,
			'notnull' => false,
			'default' => null));
}
?>
<#35>
<?php
// insert permission edit/delete records
$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_edit_records'),
			  'role' => array('text', 'host'),
			  'has_access' =>array('integer', 1)
	));


$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_edit_records'),
			  'role' => array('text', 'mini-host'),
			  'has_access' =>array('integer', 1)));

$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_edit_records'),
			  'role' => array('text', 'view'),
			  'has_access' =>array('integer', 0)));

$next_id = $ilDB->nextId('rep_robj_xavc_gloperm');
$ilDB->insert('rep_robj_xavc_gloperm',
	array(	'id' => array('integer', $next_id),
			  'permission' => array('text', 'perm_edit_records'),
			  'role' => array('text', 'denied'),
			  'has_access' =>array('integer', 0)));
?>