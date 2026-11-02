<?php
/**
 * Notifications — creates and reads NOTIFICATIONS table.
 * Fires notifications for: new order, new payment, low stock,
 * new customer, kitchen status change, employee added, supplier added.
 * db.php is already loaded by bootstrap.php — no require needed here.
 */

/*  Create notification(s) ─ */
function createNotification(int $userId, string $title, string $message, string $type = 'info', string $linkUrl = ''): void {
    try {
        $conn = getDBConnection();

        /* $userId = 0 means notify ALL active users */
        if ($userId === 0) {
            $sAll = oci_parse($conn, "SELECT USER_ID FROM APP_USER WHERE STATUS='Active'");
            oci_execute($sAll);
            $uids = [];
            while ($r = oci_fetch_assoc($sAll)) { $uids[] = (int)$r['USER_ID']; }
            oci_free_statement($sAll);
        } else {
            $uids = [$userId];
        }

        $sql = "INSERT INTO NOTIFICATIONS
                    (NOTIF_ID, USER_ID, TITLE, MESSAGE, TYPE, IS_READ, CREATED_AT, LINK_URL)
                VALUES
                    (NOTIF_SEQ.NEXTVAL, :uid, :ti, :ms, :ty, 0, SYSDATE, :lu)";

        foreach ($uids as $uid) {
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':uid', $uid);
            oci_bind_by_name($stmt, ':ti',  $title);
            oci_bind_by_name($stmt, ':ms',  $message);
            oci_bind_by_name($stmt, ':ty',  $type);
            oci_bind_by_name($stmt, ':lu',  $linkUrl);
            oci_execute($stmt);
            oci_free_statement($stmt);
        }
        oci_close($conn);
    } catch (Throwable $e) {
        // NOTIFICATIONS table may not exist yet — fail silently
    }
}

/*  Get unread notifications for a user ── */
function getUnreadNotifications(int $userId): array {
    try {
        $conn = getDBConnection();
        $sql  = "SELECT NOTIF_ID, TITLE, MESSAGE, TYPE, LINK_URL,
                        TO_CHAR(CREATED_AT,'DD-Mon HH24:MI') CREATED_AT
                 FROM   NOTIFICATIONS
                 WHERE  USER_ID = :uid AND IS_READ = 0
                 ORDER  BY CREATED_AT DESC
                 FETCH  FIRST 20 ROWS ONLY";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':uid', $userId);
        oci_execute($stmt);
        $rows = [];
        while ($r = oci_fetch_assoc($stmt)) { $rows[] = $r; }
        oci_free_statement($stmt);
        oci_close($conn);
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/*  Count unread ── */
function countUnreadNotifications(int $userId): int {
    try {
        $conn = getDBConnection();
        $sql  = "SELECT COUNT(*) C FROM NOTIFICATIONS WHERE USER_ID=:uid AND IS_READ=0";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':uid', $userId);
        oci_execute($stmt);
        $r = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        oci_close($conn);
        return (int)($r['C'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/*  Mark one as read ─ */
function markNotificationRead(int $notifId, int $userId): void {
    try {
        $conn = getDBConnection();
        $sql  = "UPDATE NOTIFICATIONS SET IS_READ=1 WHERE NOTIF_ID=:nid AND USER_ID=:uid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':nid', $notifId);
        oci_bind_by_name($stmt, ':uid', $userId);
        oci_execute($stmt);
        oci_free_statement($stmt);
        oci_close($conn);
    } catch (Throwable $e) {}
}

/*  Mark all read ─ */
function markAllNotificationsRead(int $userId): void {
    try {
        $conn = getDBConnection();
        $sql  = "UPDATE NOTIFICATIONS SET IS_READ=1 WHERE USER_ID=:uid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':uid', $userId);
        oci_execute($stmt);
        oci_free_statement($stmt);
        oci_close($conn);
    } catch (Throwable $e) {}
}

/*  Delete one notification  */
function deleteNotification(int $notifId, int $userId): void {
    try {
        $conn = getDBConnection();
        $sql  = "DELETE FROM NOTIFICATIONS WHERE NOTIF_ID=:nid AND USER_ID=:uid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':nid', $notifId);
        oci_bind_by_name($stmt, ':uid', $userId);
        oci_execute($stmt);
        oci_free_statement($stmt);
        oci_close($conn);
    } catch (Throwable $e) {}
}

/*  Clear all notifications for a user  */
function clearAllNotifications(int $userId): void {
    try {
        $conn = getDBConnection();
        $sql  = "DELETE FROM NOTIFICATIONS WHERE USER_ID=:uid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':uid', $userId);
        oci_execute($stmt);
        oci_free_statement($stmt);
        oci_close($conn);
    } catch (Throwable $e) {}
}

/* 
   AUTO-NOTIFICATION HELPERS
   Call these from CRUD pages when actions happen.
    */

/** Notify when a new order is created and sent to kitchen */
function notifyNewOrder(int $orderId, string $orderType, int $customerId): void {
    $conn  = getDBConnection();
    $sql   = "SELECT FIRST_NAME||' '||LAST_NAME N FROM CUSTOMER WHERE CUSTOMER_ID=:cid";
    $stmt  = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':cid', $customerId);
    oci_execute($stmt);
    $row   = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    $name  = $row ? $row['N'] : 'Customer #'.$customerId;
    createNotification(0,
        'New Order #'.$orderId.' → Kitchen',
        $orderType.' order from '.$name.' sent to kitchen.',
        'info',
        BASE_URL.'frontend/pages/orders.php?action=view&id='.$orderId
    );
}

/** Notify when kitchen status changes */
function notifyKitchenStatusChange(int $orderId, string $newStatus): void {
    createNotification(0,
        'Kitchen: Order #'.$orderId.' — '.$newStatus,
        'Order #'.$orderId.' kitchen status changed to '.$newStatus.'.',
        $newStatus === 'Ready' ? 'success' : 'info',
        BASE_URL.'frontend/pages/kitchen.php'
    );
}

/** Notify when a payment is recorded */
function notifyPayment(int $orderId, string $amount, string $status): void {
    createNotification(0,
        'Payment '.$status.' — Order #'.$orderId,
        'Amount '.$amount.' marked as '.$status.' for Order #'.$orderId.'.',
        $status === 'Paid' ? 'success' : 'warning',
        BASE_URL.'frontend/pages/payments.php'
    );
}

/** Notify when a new customer is added */
function notifyNewCustomer(string $name): void {
    createNotification(0,
        'New Customer Added',
        $name.' has been registered.',
        'info',
        BASE_URL.'frontend/pages/customers.php'
    );
}

/** Notify on low stock */
function notifyLowStock(string $itemName, float $qty, string $unit): void {
    createNotification(0,
        '⚠ Low Stock: '.$itemName,
        'Only '.$qty.' '.$unit.' remaining — below reorder level.',
        'warning',
        BASE_URL.'frontend/pages/inventory.php'
    );
}

/** Notify when a new employee is added */
function notifyNewEmployee(string $name, string $role): void {
    createNotification(0,
        'New Employee: '.$name,
        $name.' ('.$role.') added to the system.',
        'info',
        BASE_URL.'frontend/pages/employees.php'
    );
}

/** Notify when new supplier is added */
function notifyNewSupplier(string $name): void {
    createNotification(0,
        'New Supplier: '.$name,
        $name.' has been added as a supplier.',
        'info',
        BASE_URL.'frontend/pages/suppliers.php'
    );
}
