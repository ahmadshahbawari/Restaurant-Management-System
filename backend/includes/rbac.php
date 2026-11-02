<?php

//  Role-Based Access Control (RBAC)
 

function getRolePermissions(): array {
    return [
        'Manager'  => ['*'],
        'Admin'    => ['*'],
        'Chef'     => ['dashboard','kitchen','menu_items','categories','orders_view','inventory_view'],
        'Waiter'   => ['dashboard','orders','orders_view','customers'],
        'Cashier'  => ['dashboard','payments','orders_view'],    
        'Delivery' => ['dashboard','orders_view'],
        'Supplier' => ['dashboard','suppliers_view','inventory_view'],
        'Staff'    => ['dashboard'],
    ];
}

function canAccess(string $pageKey): bool {
    $role    = trim($_SESSION['role'] ?? 'Staff');
    $perms   = getRolePermissions();
    $allowed = $perms[$role] ?? $perms['Staff'];
    if (in_array('*', $allowed)) return true;
    if (in_array($pageKey, $allowed)) return true;
    if (in_array($pageKey.'_view', $allowed)) return true;
    return false;
}

function canWrite(string $pageKey): bool {
    $role    = trim($_SESSION['role'] ?? 'Staff');
    $perms   = getRolePermissions();
    $allowed = $perms[$role] ?? $perms['Staff'];
    if (in_array('*', $allowed)) return true;
    if (in_array($pageKey.'_view', $allowed) && !in_array($pageKey, $allowed)) return false;
    return in_array($pageKey, $allowed);
}

function requireAccess(string $pageKey): void {
    if (!canAccess($pageKey)) {
        $_SESSION['flash_type'] = 'danger';
        $_SESSION['flash_msg']  = 'Access denied. You do not have permission to view this page.';
        while (ob_get_level()) { ob_end_clean(); }
        header('Location: ' . BASE_URL . 'frontend/pages/dashboard.php');
        exit;
    }
}

function getSidebarMenu(): array {
    $role    = trim($_SESSION['role'] ?? 'Staff');
    $perms   = getRolePermissions();
    $allowed = $perms[$role] ?? $perms['Staff'];
    $full    = in_array('*', $allowed);

    $has = function(string $key) use ($allowed, $full): bool {
        return $full || in_array($key, $allowed) || in_array($key.'_view', $allowed);
    };

    /* Use t() if lang.php loaded, else fallback English */
    $tr = function(string $key, string $fallback): string {
        if (function_exists('t') && isset($_SESSION['lang'])) {
            $result = t($key);
            return ($result !== $key) ? $result : $fallback;
        }
        return $fallback;
    };

    $menu = [];

    $menu[] = ['section' => $tr('sec_main','Main')];
    $menu[] = ['label'=> $tr('nav_dashboard','Dashboard'),'icon'=>'fa-gauge-high','page'=>'dashboard.php','key'=>'dashboard'];

    $restaurant = [];
    if ($has('orders'))   $restaurant[] = ['label'=> $tr('nav_orders','Orders'),  'icon'=>'fa-clipboard-list','page'=>'orders.php',  'key'=>'orders'];
    if ($has('kitchen'))  $restaurant[] = ['label'=> $tr('nav_kitchen','Kitchen'), 'icon'=>'fa-fire-burner',  'page'=>'kitchen.php', 'key'=>'kitchen'];
    if ($has('payments')) $restaurant[] = ['label'=> $tr('nav_payments','Payments'),'icon'=>'fa-credit-card', 'page'=>'payments.php','key'=>'payments'];
    if ($has('reports'))  $restaurant[] = ['label'=> $tr('nav_revenue','Revenue'), 'icon'=>'fa-chart-line',   'page'=>'reports.php', 'key'=>'reports'];
    if (!empty($restaurant)) {
        $menu[] = ['section' => $tr('sec_restaurant','Restaurant')];
        foreach ($restaurant as $r) $menu[] = $r;
    }

    $menuItems = [];
    if ($has('menu_items')) $menuItems[] = ['label'=> $tr('nav_menu_items','Menu Items'),'icon'=>'fa-utensils',  'page'=>'menu_items.php','key'=>'menu_items'];
    if ($has('categories')) $menuItems[] = ['label'=> $tr('nav_categories','Categories'),'icon'=>'fa-layer-group','page'=>'categories.php','key'=>'categories'];
    if (!empty($menuItems)) {
        $menu[] = ['section' => $tr('sec_menu','Menu')];
        foreach ($menuItems as $m) $menu[] = $m;
    }

    $people = [];
    if ($has('customers')) $people[] = ['label'=> $tr('nav_customers','Customers'),'icon'=>'fa-users',   'page'=>'customers.php','key'=>'customers'];
    if ($has('employees')) $people[] = ['label'=> $tr('nav_employees','Employees'),'icon'=>'fa-id-badge','page'=>'employees.php','key'=>'employees'];
    if (!empty($people)) {
        $menu[] = ['section' => $tr('sec_people','People')];
        foreach ($people as $p) $menu[] = $p;
    }

    $stock = [];
    if ($has('inventory')) $stock[] = ['label'=> $tr('nav_inventory','Inventory'),'icon'=>'fa-boxes-stacked','page'=>'inventory.php','key'=>'inventory'];
    if ($has('suppliers')) $stock[] = ['label'=> $tr('nav_suppliers','Suppliers'), 'icon'=>'fa-truck',       'page'=>'suppliers.php','key'=>'suppliers'];
    if (!empty($stock)) {
        $menu[] = ['section' => $tr('sec_stock','Stock')];
        foreach ($stock as $s) $menu[] = $s;
    }

    $admin = [];
    if ($full) {
        $admin[] = ['label'=> $tr('nav_app_users','App Users'),'icon'=>'fa-key',    'page'=>'app_users.php','key'=>'app_users'];
        $admin[] = ['label'=> $tr('nav_settings','Settings'),  'icon'=>'fa-sliders','page'=>'settings.php', 'key'=>'settings'];
    }
    /* My Profile is available to ALL roles */
    if (empty($admin)) { $menu[] = ['section' => $tr('sec_admin','Admin')]; }
    if (!empty($admin)) { $menu[] = ['section' => $tr('sec_admin','Admin')]; foreach ($admin as $a) $menu[] = $a; }
    $menu[] = ['label'=>'My Profile','icon'=>'fa-circle-user','page'=>'profile.php','key'=>'profile'];

    return $menu;
}
