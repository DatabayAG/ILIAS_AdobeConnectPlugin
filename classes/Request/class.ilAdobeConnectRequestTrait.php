<?php

/******************************************************************************
 * This file is part of ILIAS, a powerful learning management system.
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 *      https://www.ilias.de
 *      https://github.com/ILIAS-eLearning
 *****************************************************************************/

declare(strict_types=1);

use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;

/**
 * ilAdobeConnectRequestTrait
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
trait ilAdobeConnectRequestTrait
{
    public static string $REQUEST_GET = 'get';
    public static string $REQUEST_POST = 'post';
    public static string $TYPE_INT = 'int';
    public static string $TYPE_STRING = 'string';
    public static string $TYPE_LIST_INT = 'list_int';
    public static string $TYPE_LIST_STRING = 'list_string';

    private function getWrapperByRequestType($request_type): ArrayBasedRequestWrapper
    {
        global $DIC;

        $wrapper = $DIC->http()->wrapper();

        if ($request_type == self::$REQUEST_POST) {
            return $wrapper->post();
        }
        return $wrapper->query();
    }

    private function retrieveIntFrom(string $request_type, string $param): int
    {
        global $DIC;

        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        return $wrapper->retrieve(
            $param,
            $refinery->byTrying([
                $refinery->kindlyTo()->int(),
                $refinery->always(0)
            ])
        );
    }

    private function retrieveStringFrom(string $request_type, string $param): string
    {
        global $DIC;

        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        return $wrapper->retrieve(
            $param,
            $refinery->byTrying([
                $refinery->kindlyTo()->string(),
                $refinery->always('')
            ])
        );
    }

    private function retrieveListOfStringFrom(string $request_type, string $param): array
    {
        global $DIC;

        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        return $wrapper->retrieve(
            $param,
            $refinery->byTrying([
                $refinery->kindlyTo()->dictOf($refinery->kindlyTo()->string())
            ])
        );
    }

    private function retrieveListOfIntFrom(string $request_type, string $param): array
    {
        global $DIC;

        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        return $wrapper->retrieve(
            $param,
            $refinery->byTrying([
                $refinery->kindlyTo()->dictOf($refinery->kindlyTo()->int())
            ])
        );
    }

    private function retrieveFromRequest(string $param, string $value_type)
    {
        global $DIC;

        $base_wrapper = $DIC->http()->wrapper();

        $request_type = self::$REQUEST_GET;
        if ($base_wrapper->query()->has($param)) {
            $request_type = self::$REQUEST_GET;
        } elseif ($base_wrapper->post()->has($param)) {
            $request_type = self::$REQUEST_POST;
        }

        if ($value_type == self::$TYPE_INT) {
            return $this->retrieveIntFrom($request_type, $param);
        }
        if ($value_type == self::$TYPE_STRING) {
            return $this->retrieveStringFrom($request_type, $param);
        }
        if ($value_type == self::$TYPE_LIST_INT) {
            return $this->retrieveListOfIntFrom($request_type, $param);
        }
        if ($value_type == self::$TYPE_LIST_STRING) {
            return $this->retrieveListOfStringFrom($request_type, $param);
        }
    }
}
