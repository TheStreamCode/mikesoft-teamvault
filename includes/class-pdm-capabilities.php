<?php

defined('ABSPATH') || exit;

class PDM_Capabilities
{
    public const CAP_MANAGE = 'manage_private_documents';

    public static function register(): void
    {
        $roles = wp_roles();
        
        $administrator = get_role('administrator');
        if ($administrator && !$administrator->has_cap(self::CAP_MANAGE)) {
            $administrator->add_cap(self::CAP_MANAGE);
        }
    }

    public static function can_manage(): bool
    {
        return current_user_can(self::CAP_MANAGE);
    }

    public static function get_roles_with_capability(): array
    {
        global $wp_roles;
        $roles = [];

        foreach ($wp_roles->roles as $role_name => $role_data) {
            if (isset($role_data['capabilities'][self::CAP_MANAGE]) 
                && $role_data['capabilities'][self::CAP_MANAGE]) {
                $roles[$role_name] = $role_data['name'];
            }
        }

        return $roles;
    }

    public static function add_capability_to_role(string $roleName): bool
    {
        $role = get_role($roleName);
        if (!$role) {
            return false;
        }
        
        $role->add_cap(self::CAP_MANAGE);
        return true;
    }

    public static function remove_capability_from_role(string $roleName): bool
    {
        $role = get_role($roleName);
        if (!$role) {
            return false;
        }
        
        $role->remove_cap(self::CAP_MANAGE);
        return true;
    }
}
