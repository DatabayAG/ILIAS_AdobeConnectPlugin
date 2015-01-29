<?php

interface ilAdobeConnectTableDataProvider
{
	/**
	 * @param array $params
	 * @param array $filter
	 * @return array
	 */
	public function getList(array $params, array $filter);
}