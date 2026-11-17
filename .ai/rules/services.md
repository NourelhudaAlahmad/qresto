---
paths:
  - 'app/Services/**'
---

# Services

## Role capabilities are per restaurant
Global spatie role permissions (RolesSeeder) are only the defaults. A restaurant's admin changes its own grid through PermissionManager / RestaurantCapabilities::set(), which writes restaurant_role_capabilities overrides; User::hasPermissionViaRole() applies them, so can() follows the user's restaurant. Never call givePermissionTo/revokePermissionTo on a SpatieRole from app code: that changes every restaurant. Read a restaurant's grid with RestaurantCapabilities::grid()/roleHas(), and scope permission_audits by restaurant_id.
