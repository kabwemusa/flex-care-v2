import { NavItem } from '../layout/side-nav/side-nav';
import { AuthService } from 'core-auth';

/**
 * Filter navigation items based on user permissions and module access
 */
export function filterNavigation(items: NavItem[], authService: AuthService): NavItem[] {
  return items
    .map((item) => {
      // Check if user has required permissions for this item
      if (!canAccessNavItem(item, authService)) {
        return null;
      }

      // If item has children, filter them recursively
      if (item.children && item.children.length > 0) {
        const filteredChildren = filterNavigation(item.children, authService);

        // If all children are filtered out, hide the parent too
        if (filteredChildren.length === 0) {
          return null;
        }

        return {
          ...item,
          children: filteredChildren,
        };
      }

      return item;
    })
    .filter((item): item is NavItem => item !== null);
}

/**
 * Check if user can access a specific nav item
 */
function canAccessNavItem(item: NavItem, authService: AuthService): boolean {
  // Check system admin requirement
  if (item.requireSystemAdmin && !authService.isSystemAdmin()) {
    return false;
  }

  // System admins can access everything
  if (authService.isSystemAdmin()) {
    return true;
  }

  // Check module access requirement
  if (item.requiredModule && !authService.hasModuleAccess(item.requiredModule as any)) {
    return false;
  }

  // Check permission requirements
  if (item.requiredPermissions && item.requiredPermissions.length > 0) {
    const guard = (item.guard as any) || 'web';
    const hasPermission = authService.hasAnyPermission(item.requiredPermissions, guard);
    if (!hasPermission) {
      return false;
    }
  }

  return true;
}
