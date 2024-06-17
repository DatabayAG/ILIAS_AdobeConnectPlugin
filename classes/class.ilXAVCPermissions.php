<?php

class ilXAVCPermissions
{
    public function __construct()
    {
    }

    public static function getPermissionsArray(): array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $res = $ilDB->query('SELECT * FROM rep_robj_xavc_gloperm ORDER BY id, permission');

        $permissions = [];

        while ($row = $ilDB->fetchAssoc($res)) {
            $permissions[$row['permission']][$row['role']] = $row['has_access'];
        }

        return $permissions;
    }

    public function setPermissions(array $permissions = []): void
    {
        global $DIC;
        $ilDB = $DIC->database();

        // reset all permissions
        $ilDB->update(
            'rep_robj_xavc_gloperm',
            ['has_access' => ['integer', 0]],
            ['has_access' => ['integer', 1]]
        );

        //update new permissions
        foreach ($permissions as $permission => $roles) {
            foreach ($roles as $role) {
                $ilDB->update(
                    'rep_robj_xavc_gloperm',
                    ['has_access' => ['integer', 1]],
                    [
                        'permission' => ['text', $permission],
                        'role' => ['text', $role]
                    ]
                );
            }
        }
    }

    public static function hasAccess(int $user_id, int $ref_id, string $permission): bool
    {
        global $DIC;
        $ilDB = $DIC->database();

        //lookupRole
        $res = $ilDB->queryF(
            '
			SELECT has_access FROM rep_robj_xavc_members mem
			LEFT JOIN rep_robj_xavc_gloperm perm ON mem.xavc_status = perm.role
			WHERE mem.user_id = %s AND mem.ref_id = %s
			AND perm.permission = %s',
            ['integer', 'integer', 'text'], [$user_id, $ref_id, $permission]
        );

        $access = false;

        while ($row = $ilDB->fetchAssoc($res)) {
            $access = (bool) $row['has_access'];
        }

        return (bool) $access;
    }

    /**
     * @param string $role host | mini-host | view | denied
     */
    public static function lookupPermission(string $permission, string $role): int
    {
        global $DIC;
        $ilDB = $DIC->database();

        $res = $ilDB->queryF(
            '
			SELECT has_access FROM rep_robj_xavc_gloperm 
			WHERE permission = %s AND role = %s',
            ['text', 'text'], [$permission, $role]
        );

        $row = $ilDB->fetchAssoc($res);

        return (int) $row['has_access'];
    }
}