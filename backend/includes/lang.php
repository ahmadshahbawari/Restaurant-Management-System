<?php




if (!function_exists('t')) {

    /*  All translations  */
    function _getLangData(): array {
        return [
            /*  Navigation  */
            'nav_dashboard'   => ['en'=>'Dashboard',         'ps'=>'ډیشبورډ',             'fa'=>'داشبورد'],
            'nav_orders'      => ['en'=>'Orders',            'ps'=>'آرډرونه',             'fa'=>'سفارشات'],
            'nav_kitchen'     => ['en'=>'Kitchen',           'ps'=>'اشپزخان',              'fa'=>'آشپزخانه'],
            'nav_payments'    => ['en'=>'Payments',          'ps'=>'تادیات',              'fa'=>'پرداخت‌ها'],
            'nav_revenue'     => ['en'=>'Revenue',           'ps'=>'عواید',               'fa'=>'درآمد'],
            'nav_menu_items'  => ['en'=>'Menu Items',        'ps'=>'د مینو توکي',          'fa'=>'آیتم‌های منو'],
            'nav_categories'  => ['en'=>'Categories',        'ps'=>'کټګورۍ',              'fa'=>'دسته‌بندی‌ها'],
            'nav_customers'   => ['en'=>'Customers',         'ps'=>'پیرودونکي',           'fa'=>'مشتریان'],
            'nav_employees'   => ['en'=>'Employees',         'ps'=>'کارمندان',            'fa'=>'کارمندان'],
            'nav_inventory'   => ['en'=>'Inventory',         'ps'=>'ګدام',               'fa'=>'موجودی'],
            'nav_suppliers'   => ['en'=>'Suppliers',         'ps'=>'عرضه کونکي',          'fa'=>'تامین‌کنندگان'],
            'nav_app_users'   => ['en'=>'App Users',         'ps'=>'کاروونکي',            'fa'=>'کاربران'],
            'nav_settings'    => ['en'=>'Settings',          'ps'=>'تنظیمات',             'fa'=>'تنظیمات'],

            /*  Section headings  */
            'sec_main'        => ['en'=>'Main',              'ps'=>'اصلي',                'fa'=>'اصلی'],
            'sec_restaurant'  => ['en'=>'Restaurant',        'ps'=>'رسټوران',             'fa'=>'رستوران'],
            'sec_menu'        => ['en'=>'Menu',              'ps'=>'مینو',                'fa'=>'منو'],
            'sec_people'      => ['en'=>'People',            'ps'=>'خلګ',                 'fa'=>'افراد'],
            'sec_stock'       => ['en'=>'Stock',             'ps'=>'ذخیره',               'fa'=>'انبار'],
            'sec_admin'       => ['en'=>'Admin',             'ps'=>'اداره',               'fa'=>'مدیریت'],

            /*  Topbar / header  */
            'topbar_date'     => ['en'=>'Date',              'ps'=>'نیټه',                'fa'=>'تاریخ'],
            'lbl_dark_mode'   => ['en'=>'Dark Mode',         'ps'=>'تور موډ',             'fa'=>'حالت تاریک'],
            'lbl_light_mode'  => ['en'=>'Light Mode',        'ps'=>'رڼا موډ',             'fa'=>'حالت روشن'],
            'notif_title'     => ['en'=>'Notifications',     'ps'=>'خبرتیاوې',            'fa'=>'اعلان‌ها'],
            'notif_clear_all' => ['en'=>'Clear All',         'ps'=>'ټول پاک کړه',          'fa'=>'پاک کردن همه'],
            'notif_empty'     => ['en'=>'No new notifications','ps'=>'نوې خبرتیاوې نسته','fa'=>'اعلان جدیدی وجود ندارد'],
            'btn_profile'     => ['en'=>'My Profile',        'ps'=>'زما پروفایل',          'fa'=>'پروفایل من'],
            'btn_settings'    => ['en'=>'Settings',          'ps'=>'تنظیمات',             'fa'=>'تنظیمات'],
            'btn_logout'      => ['en'=>'Logout',            'ps'=>'وتل',                 'fa'=>'خروج'],

            /*  Login  */
            'login_title'     => ['en'=>'Sign In',           'ps'=>'ننوتل',               'fa'=>'ورود'],
            'login_subtitle'  => ['en'=>'Restaurant Management System','ps'=>'د رسټوران مدیریت سیسټم','fa'=>'سیستم مدیریت رستوران'],
            'login_username'  => ['en'=>'Username',          'ps'=>'کارونکی نوم',          'fa'=>'نام کاربری'],
            'login_password'  => ['en'=>'Password',          'ps'=>'پاسورډ',              'fa'=>'رمز عبور'],
            'login_btn'       => ['en'=>'Sign In',           'ps'=>'ننوتل',               'fa'=>'ورود'],
            'login_error'     => ['en'=>'Invalid username or password.','ps'=>'غلط نوم یا پاسورډ.','fa'=>'نام کاربری یا رمز اشتباه است.'],

            /*  Dashboard  */
            'page_dashboard'         => ['en'=>'Dashboard',          'ps'=>'ډیشبورډ',            'fa'=>'داشبورد'],
            'dash_total_customers'   => ['en'=>'Total Customers',    'ps'=>'ټول پیرودونکي',      'fa'=>'مجموع مشتریان'],
            'dash_orders_today'      => ['en'=>'Orders Today',       'ps'=>'نن ورځ آرډرونه',     'fa'=>'سفارشات امروز'],
            'dash_revenue_today'     => ['en'=>'Revenue Today',      'ps'=>'نن ورځ عواید',       'fa'=>'درآمد امروز'],
            'dash_revenue_month'     => ['en'=>'Revenue This Month', 'ps'=>'د میاشتې عواید',     'fa'=>'درآمد این ماه'],
            'dash_kitchen_active'    => ['en'=>'Kitchen Active',     'ps'=>'فعال پخلنځی',        'fa'=>'آشپزخانه فعال'],
            'dash_low_stock'         => ['en'=>'Low Stock Items',    'ps'=>'کم ذخیره توکي',      'fa'=>'اقلام کم موجودی'],
            'dash_available_items'   => ['en'=>'Available Items',    'ps'=>'شته توکي',            'fa'=>'اقلام موجود'],
            'dash_total_orders'      => ['en'=>'Total Orders',       'ps'=>'ټول آرډرونه',        'fa'=>'مجموع سفارشات'],
            'dash_recent_orders'     => ['en'=>'Recent Orders',      'ps'=>'وروستي آرډرونه',     'fa'=>'سفارشات اخیر'],
            'dash_quick_actions'     => ['en'=>'Quick Actions',      'ps'=>'ژره کړنې',           'fa'=>'اقدامات سریع'],
            'dash_revenue_7days'     => ['en'=>'Revenue – Last 7 Days','ps'=>'د ۷ ورځو عواید','fa'=>'درآمد ۷ روز اخیر'],
            'dash_orders_by_type'    => ['en'=>'Orders by Type',     'ps'=>'د ډول آرډرونه',      'fa'=>'سفارشات بر اساس نوع'],
            'dash_order_status'      => ['en'=>'Order Status (30 Days)','ps'=>'د آرډر حالت','fa'=>'وضعیت سفارشات'],
            'dash_top_items'         => ['en'=>'Top 5 Menu Items',   'ps'=>'غوره ۵ توکي',        'fa'=>'۵ آیتم برتر منو'],
            'dash_monthly_revenue'   => ['en'=>'Monthly Revenue',    'ps'=>'میاشتنۍ عواید',      'fa'=>'درآمد ماهانه'],
            'dash_kitchen_dist'      => ['en'=>'Kitchen Distribution','ps'=>'د پخلنځي وضع',     'fa'=>'توزیع آشپزخانه'],

            /*  Common buttons / labels  */
            'btn_add'         => ['en'=>'Add',               'ps'=>'اضافه',               'fa'=>'افزودن'],
            'btn_new'         => ['en'=>'New',               'ps'=>'نوی',                 'fa'=>'جدید'],
            'btn_edit'        => ['en'=>'Edit',              'ps'=>'سم کول',              'fa'=>'ویرایش'],
            'btn_delete'      => ['en'=>'Delete',            'ps'=>'ړنګول',               'fa'=>'حذف'],
            'btn_save'        => ['en'=>'Save',              'ps'=>'خوندي کول',           'fa'=>'ذخیره'],
            'btn_cancel'      => ['en'=>'Cancel',            'ps'=>'لغوه',                'fa'=>'لغو'],
            'btn_back'        => ['en'=>'Back',              'ps'=>'شاته',                'fa'=>'بازگشت'],
            'btn_search'      => ['en'=>'Search',            'ps'=>'لټون',                'fa'=>'جستجو'],
            'btn_clear'       => ['en'=>'Clear',             'ps'=>'پاکول',               'fa'=>'پاک کردن'],
            'btn_view_all'    => ['en'=>'View All',          'ps'=>'ټول وګوره',           'fa'=>'مشاهده همه'],
            'btn_new_order'   => ['en'=>'New Order',         'ps'=>'نوی آرډر',            'fa'=>'سفارش جدید'],
            'btn_confirm_del' => ['en'=>'Yes, Delete',       'ps'=>'هو، ړنګوه',           'fa'=>'بله، حذف کن'],
            'btn_upload'      => ['en'=>'Upload',            'ps'=>'پورته کول',           'fa'=>'آپلود'],
            'btn_download'    => ['en'=>'Download',          'ps'=>'ډاونلوډ',             'fa'=>'دانلود'],
            'btn_restore'     => ['en'=>'Restore Now',       'ps'=>'اوس بیرته راوله',     'fa'=>'اکنون بازیابی کن'],

            /*  Table columns  */
            'col_id'          => ['en'=>'#',                 'ps'=>'#',                   'fa'=>'#'],
            'col_name'        => ['en'=>'Name',              'ps'=>'نوم',                 'fa'=>'نام'],
            'col_first_name'  => ['en'=>'First Name',        'ps'=>'لومړی نوم',           'fa'=>'نام'],
            'col_last_name'   => ['en'=>'Last Name',         'ps'=>'وروستی نوم',          'fa'=>'نام خانوادگی'],
            'col_phone'       => ['en'=>'Phone',             'ps'=>'تلیفون',              'fa'=>'تلفن'],
            'col_email'       => ['en'=>'Email',             'ps'=>'بریښنالیک',           'fa'=>'ایمیل'],
            'col_address'     => ['en'=>'Address',           'ps'=>'پته',                 'fa'=>'آدرس'],
            'col_status'      => ['en'=>'Status',            'ps'=>'حالت',                'fa'=>'وضعیت'],
            'col_actions'     => ['en'=>'Actions',           'ps'=>'کړنې',                'fa'=>'عملیات'],
            'col_price'       => ['en'=>'Price',             'ps'=>'قیمت',                'fa'=>'قیمت'],
            'col_total'       => ['en'=>'Total',             'ps'=>'ټول',                 'fa'=>'مجموع'],
            'col_date'        => ['en'=>'Date',              'ps'=>'نیټه',                'fa'=>'تاریخ'],
            'col_role'        => ['en'=>'Role',              'ps'=>'رول',                 'fa'=>'نقش'],
            'col_category'    => ['en'=>'Category',          'ps'=>'کټګورۍ',              'fa'=>'دسته‌بندی'],
            'col_quantity'    => ['en'=>'Quantity',          'ps'=>'مقدار',               'fa'=>'مقدار'],
            'col_amount'      => ['en'=>'Amount',            'ps'=>'مبلغ',                'fa'=>'مبلغ'],
            'col_salary'      => ['en'=>'Salary',            'ps'=>'معاش',                'fa'=>'حقوق'],
            'col_hire_date'   => ['en'=>'Hire Date',         'ps'=>'د کار نیټه',          'fa'=>'تاریخ استخدام'],
            'col_type'        => ['en'=>'Type',              'ps'=>'ډول',                 'fa'=>'نوع'],
            'col_customer'    => ['en'=>'Customer',          'ps'=>'پیرودونکی',           'fa'=>'مشتری'],
            'col_order'       => ['en'=>'Order',             'ps'=>'آرډر',                'fa'=>'سفارش'],
            'col_unit'        => ['en'=>'Unit',              'ps'=>'واحد',                'fa'=>'واحد'],
            'col_in_stock'    => ['en'=>'In Stock',          'ps'=>'ذخیره',               'fa'=>'موجودی'],
            'col_reorder'     => ['en'=>'Reorder Level',     'ps'=>'بیا آرډر کچه',       'fa'=>'سطح سفارش مجدد'],
            'col_unit_cost'   => ['en'=>'Unit Cost',         'ps'=>'واحد لګښت',           'fa'=>'هزینه واحد'],
            'col_availability'=> ['en'=>'Available',         'ps'=>'شتمنتیا',             'fa'=>'موجود'],
            'col_username'    => ['en'=>'Username',          'ps'=>'کارونکی نوم',          'fa'=>'نام کاربری'],

            /*  Pages  */
            'page_customers'  => ['en'=>'Customers',         'ps'=>'پیرودونکي',           'fa'=>'مشتریان'],
            'page_orders'     => ['en'=>'Orders',            'ps'=>'آرډرونه',             'fa'=>'سفارشات'],
            'page_kitchen'    => ['en'=>'Kitchen Board',     'ps'=>'د پخلنځي بورډ',       'fa'=>'تابلوی آشپزخانه'],
            'page_payments'   => ['en'=>'Payments',          'ps'=>'تادیات',              'fa'=>'پرداخت‌ها'],
            'page_menu_items' => ['en'=>'Menu Items',        'ps'=>'د مینو توکي',          'fa'=>'آیتم‌های منو'],
            'page_categories' => ['en'=>'Categories',        'ps'=>'کټګورۍ',              'fa'=>'دسته‌بندی‌ها'],
            'page_employees'  => ['en'=>'Employees',         'ps'=>'کارمندان',            'fa'=>'کارمندان'],
            'page_inventory'  => ['en'=>'Inventory',         'ps'=>'انبار',               'fa'=>'موجودی'],
            'page_suppliers'  => ['en'=>'Suppliers',         'ps'=>'عرضه کونکي',          'fa'=>'تامین‌کنندگان'],
            'page_app_users'  => ['en'=>'App Users',         'ps'=>'کاروونکي',            'fa'=>'کاربران'],
            'page_settings'   => ['en'=>'Settings',          'ps'=>'تنظیمات',             'fa'=>'تنظیمات'],
            'page_reports'    => ['en'=>'Revenue Reports',   'ps'=>'د عوایدو راپورونه',   'fa'=>'گزارش درآمد'],

            /*  Filter / search  */
            'lbl_filter'      => ['en'=>'Filter:',           'ps'=>'پالنه:',              'fa'=>'فیلتر:'],
            'lbl_search'      => ['en'=>'Search…',           'ps'=>'لټون…',               'fa'=>'جستجو…'],
            'lbl_all_statuses'=> ['en'=>'All Statuses',      'ps'=>'ټول حالتونه',         'fa'=>'همه وضعیت‌ها'],
            'lbl_all_types'   => ['en'=>'All Types',         'ps'=>'ټول ډولونه',          'fa'=>'همه انواع'],
            'lbl_showing'     => ['en'=>'Showing',           'ps'=>'ښودل کیږي',           'fa'=>'نمایش'],
            'lbl_of'          => ['en'=>'of',                'ps'=>'د',                   'fa'=>'از'],
            'lbl_records'     => ['en'=>'records',           'ps'=>'ریکارډونه',           'fa'=>'رکورد'],
            'lbl_pages'       => ['en'=>'Pages',             'ps'=>'مخونه',               'fa'=>'صفحات'],

            /*  Status values  */
            'status_active'   => ['en'=>'Active',            'ps'=>'فعال',                'fa'=>'فعال'],
            'status_inactive' => ['en'=>'Inactive',          'ps'=>'غیر فعال',            'fa'=>'غیرفعال'],
            'status_pending'  => ['en'=>'Pending',           'ps'=>'انتظار',              'fa'=>'در انتظار'],
            'status_completed'=> ['en'=>'Completed',         'ps'=>'بشپړ',                'fa'=>'تکمیل شده'],
            'status_cancelled'=> ['en'=>'Cancelled',         'ps'=>'لغو',                 'fa'=>'لغو شده'],
            'status_paid'     => ['en'=>'Paid',              'ps'=>'ورکړل شوی',           'fa'=>'پرداخت شده'],
            'status_unpaid'   => ['en'=>'Unpaid',            'ps'=>'نه ورکړل شوی',        'fa'=>'پرداخت نشده'],

            /*  Confirm delete  */
            'confirm_delete'  => ['en'=>'Confirm Delete',    'ps'=>'ړنګونه تایید کړه',    'fa'=>'تأیید حذف'],
            'confirm_msg'     => ['en'=>'This cannot be undone.','ps'=>'دا نشي بیرته راستنیدلی.','fa'=>'این عمل قابل بازگشت نیست.'],

            /*  Empty states  */
            'empty_no_data'   => ['en'=>'No data found.',    'ps'=>'معلومات ونه موندل شول.','fa'=>'داده‌ای یافت نشد.'],

            /*  Settings page  */
            'set_general'     => ['en'=>'General',           'ps'=>'عمومي',               'fa'=>'عمومی'],
            'set_appearance'  => ['en'=>'Appearance',        'ps'=>'بڼه',                 'fa'=>'ظاهر'],
            'set_logo'        => ['en'=>'Logo',              'ps'=>'لوګو',                'fa'=>'آرم'],
            'set_language'    => ['en'=>'Language',          'ps'=>'ژبه',                 'fa'=>'زبان'],
            'set_backup'      => ['en'=>'Backup',            'ps'=>'بیک اپ',              'fa'=>'پشتیبان‌گیری'],
            'set_restore'     => ['en'=>'Restore',           'ps'=>'بیرته راوستل',        'fa'=>'بازیابی'],
            'set_rest_name'   => ['en'=>'Restaurant Name',   'ps'=>'د رستوران نوم',       'fa'=>'نام رستوران'],
            'set_address'     => ['en'=>'Address',           'ps'=>'پته',                 'fa'=>'آدرس'],
            'set_date_fmt'    => ['en'=>'Date Format',       'ps'=>'د نیټې بڼه',          'fa'=>'قالب تاریخ'],
            'set_currency'    => ['en'=>'Currency',          'ps'=>'اسعار',               'fa'=>'واحد پول'],
            'set_per_page'    => ['en'=>'Records Per Page',  'ps'=>'پر مخ ریکارډونه',    'fa'=>'رکورد در صفحه'],
            'set_save'        => ['en'=>'Save Settings',     'ps'=>'تنظیمات خوندي کول',  'fa'=>'ذخیره تنظیمات'],
            'set_en'          => ['en'=>'English',           'ps'=>'انګلیسي',             'fa'=>'انگلیسی'],
            'set_ps'          => ['en'=>'Pashto',            'ps'=>'پښتو',                'fa'=>'پشتو'],
            'set_fa'          => ['en'=>'Dari / Farsi',      'ps'=>'دري / فارسي',         'fa'=>'دری / فارسی'],

            /*  Orders  */
            'ord_new'         => ['en'=>'New Order',         'ps'=>'نوی آرډر',            'fa'=>'سفارش جدید'],
            'ord_type_dine'   => ['en'=>'Dine-In',           'ps'=>'دلته خوړل',           'fa'=>'درجا'],
            'ord_type_take'   => ['en'=>'Takeaway',          'ps'=>'وړل',                 'fa'=>'بیرون بر'],
            'ord_type_del'    => ['en'=>'Delivery',          'ps'=>'لیږل',                'fa'=>'تحویل'],
            'ord_place'       => ['en'=>'Place Order & Send to Kitchen','ps'=>'آرډر کول او پخلنځي ته لیږل','fa'=>'ثبت سفارش و ارسال به آشپزخانه'],
            'ord_items'       => ['en'=>'Order Items',       'ps'=>'د آرډر توکي',         'fa'=>'اقلام سفارش'],
            'ord_view'        => ['en'=>'View Order',        'ps'=>'آرډر وګوره',          'fa'=>'مشاهده سفارش'],
            'ord_served_by'   => ['en'=>'Served By',         'ps'=>'چا خدمت وکاو',       'fa'=>'توسط'],
            'ord_record_pay'  => ['en'=>'Record Payment',    'ps'=>'تادیه ثبت کړه',       'fa'=>'ثبت پرداخت'],
            'ord_update_status'=>['en'=>'Update Status',     'ps'=>'حالت تازه کول',      'fa'=>'به‌روزرسانی وضعیت'],

            /*  Kitchen  */
            'kit_pending'     => ['en'=>'Pending',           'ps'=>'انتظار',              'fa'=>'در انتظار'],
            'kit_preparing'   => ['en'=>'Preparing',         'ps'=>'چمتو کیږي',           'fa'=>'در حال آماده‌سازی'],
            'kit_ready'       => ['en'=>'Ready',             'ps'=>'چمتو',                'fa'=>'آماده'],
            'kit_cancelled'   => ['en'=>'Cancelled',         'ps'=>'لغو',                 'fa'=>'لغو'],
            'kit_started'     => ['en'=>'Started',           'ps'=>'پیل شو',              'fa'=>'شروع شد'],

            /*  Inventory  */
            'inv_low_stock'   => ['en'=>'Low Stock',         'ps'=>'کم ذخیره',            'fa'=>'موجودی کم'],
            'inv_total_val'   => ['en'=>'Total Value',       'ps'=>'ټوله ارزښت',          'fa'=>'ارزش کل'],

            /*  Backup  */
            'bak_full'        => ['en'=>'Download Full Backup','ps'=>'بشپړ بیک اپ ډاونلوډ','fa'=>'دانلود پشتیبان کامل'],
            'bak_restore'     => ['en'=>'Restore from SQL File','ps'=>'د SQL فایل له لارې بیرته راوستل','fa'=>'بازیابی از فایل SQL'],

            /*  Extra missing keys  */
            'set_light_mode'  => ['en'=>'Light Mode',        'ps'=>'رڼا موډ',             'fa'=>'حالت روشن'],
            'set_dark_mode'   => ['en'=>'Dark Mode',         'ps'=>'تور موډ',             'fa'=>'حالت تاریک'],

            /* Keys used by pages but previously missing */
            'btn_confirm'     => ['en'=>'Confirm',           'ps'=>'تایید',               'fa'=>'تأیید'],
            'lbl_all_types'   => ['en'=>'All Types',         'ps'=>'ټول ډولونه',          'fa'=>'همه انواع'],
            'col_unit'        => ['en'=>'Unit',              'ps'=>'واحد',                'fa'=>'واحد'],
            'col_category'    => ['en'=>'Category',          'ps'=>'کټګورۍ',              'fa'=>'دسته‌بندی'],
            'col_availability'=> ['en'=>'Available',         'ps'=>'شتمنتیا',             'fa'=>'موجود'],
            'cust_title'      => ['en'=>'Customers',         'ps'=>'پیرودونکي',           'fa'=>'مشتریان'],
            'cust_add'        => ['en'=>'Add Customer',      'ps'=>'پیرودونکی اضافه',     'fa'=>'افزودن مشتری'],
        ];
    }

    /**
     * Translate key → current language. Falls back to English.
     */
    function t(string $key): string {
        static $data  = null;
        static $cache = [];
        if ($data === null) { $data = _getLangData(); }
        $lang = $_SESSION['lang'] ?? 'en';
        if (isset($cache[$lang][$key])) { return $cache[$lang][$key]; }
        $result = $data[$key][$lang] ?? $data[$key]['en'] ?? $key;
        $cache[$lang][$key] = $result;
        return $result;
    }

    function isRtl(): bool {
        return in_array($_SESSION['lang'] ?? 'en', ['ps','fa','ar']);
    }

    function currentLang(): string {
        return $_SESSION['lang'] ?? 'en';
    }
}

/*  These functions may be defined here or by header.php  */
if (!function_exists('saveUserPreference')) {
    function saveUserPreference(int $userId, string $theme, string $lang): void {
        try {
            $conn = getDBConnection();
            $sql  = "MERGE INTO USER_PREFERENCES up USING DUAL ON (up.USER_ID=:uid)
                     WHEN MATCHED    THEN UPDATE SET THEME=:th, LANGUAGE=:la, UPDATED_AT=SYSDATE
                     WHEN NOT MATCHED THEN INSERT (USER_ID,THEME,LANGUAGE,UPDATED_AT)
                     VALUES (:uid2,:th2,:la2,SYSDATE)";
            $stmt = oci_parse($conn,$sql);
            oci_bind_by_name($stmt,':uid', $userId);
            oci_bind_by_name($stmt,':th',  $theme);
            oci_bind_by_name($stmt,':la',  $lang);
            oci_bind_by_name($stmt,':uid2',$userId);
            oci_bind_by_name($stmt,':th2', $theme);
            oci_bind_by_name($stmt,':la2', $lang);
            oci_execute($stmt);
            oci_free_statement($stmt);
            oci_close($conn);
        } catch (Throwable $e) { /* USER_PREFERENCES table may not exist yet */ }
    }
}

if (!function_exists('loadUserPreferences')) {
    function loadUserPreferences(int $userId): void {
        try {
            $conn = getDBConnection();
            $stmt = oci_parse($conn,"SELECT THEME,LANGUAGE FROM USER_PREFERENCES WHERE USER_ID=:uid");
            oci_bind_by_name($stmt,':uid',$userId);
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            oci_close($conn);
            if ($row) {
                $_SESSION['theme'] = $row['THEME']    ?? 'light';
                $_SESSION['lang']  = $row['LANGUAGE'] ?? 'en';
            }
        } catch (Throwable $e) {}
    }
}
