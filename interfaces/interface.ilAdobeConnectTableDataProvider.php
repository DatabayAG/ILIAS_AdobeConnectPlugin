<?php

interface ilAdobeConnectTableDataProvider
{
    public function getList(array $params, array $filter): array;
}