<?php
/**
 * General helper functions.
 */

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string {
    $sym = $_SESSION['currency_symbol'] ?? '$';
    return $sym . number_format($amount, 2);
}

function statusBadge(string $status): string {
    $map = [
        'Pending'    => 'warning',
        'Active'     => 'success',
        'Inactive'   => 'danger',
        'Completed'  => 'success',
        'Cancelled'  => 'danger',
        'Preparing'  => 'info',
        'Ready'      => 'success',
        'Paid'       => 'success',
        'Unpaid'     => 'danger',
        'Refunded'   => 'secondary',
        'Processing' => 'info',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . ' text-white px-2 py-1" style="font-size:11px;">'
         . h($status) . '</span>';
}

function redirect(string $path): void {
    // Clear all output buffers so Location header is never blocked
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

/**
 * Generate an Export / PDF button for use inside page card-headers.
 * Opens a print-preview panel in a Bootstrap modal on the same page.
 * Place next to the Add button: echo exportBtn('customers.php');
 */
function exportBtn(string $pageName, string $label = ''): string {
    if (!defined('BASE_URL')) { return ''; }
    $url = BASE_URL . 'frontend/pages/page_export.php?page=' . rawurlencode($pageName);
    $lbl = $label
        ? h($label)
        : '<i class="fa-solid fa-file-pdf me-1"></i>PDF';
    /* data-export-url is picked up by footer.js to open modal */
    return '<button type="button" class="btn btn-sm no-print pdf-export-btn"'
         . ' data-export-url="' . $url . '"'
         . ' style="color:#e74c3c;border:1.5px solid #e74c3c;background:transparent;"'
         . ' title="Preview &amp; print / save as PDF">'
         . $lbl . '</button>';
}

function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_msg']  = $msg;
    /* Also queue as toast popup (req #8) */
    $_SESSION['toast_type'] = $type;
    $_SESSION['toast_msg']  = $msg;
}

function getFlash(): array {
    if (isset($_SESSION['flash_msg'])) {
        $flash = ['type' => $_SESSION['flash_type'], 'msg' => $_SESSION['flash_msg']];
        unset($_SESSION['flash_type'], $_SESSION['flash_msg']);
        return $flash;
    }
    return [];
}

/**
 * Pagination helper.
 * Returns ['offset'=>int, 'limit'=>int, 'page'=>int, 'total_pages'=>int, 'html'=>string]
 */
function paginate(int $totalRecords, int $perPage = 10, string $baseUrl = ''): array {
    $page  = max(1, (int)get('page', '1'));
    $total = (int)ceil($totalRecords / max(1, $perPage));
    $page  = min($page, max(1, $total));

    // Build base URL preserving existing GET params except 'page'
    if ($baseUrl === '') {
        $params = $_GET;
        unset($params['page']);
        $baseUrl = '?' . http_build_query($params);
        $sep     = strpos($baseUrl, '?') !== false ? '&' : '?';
        // already has ? from http_build_query
        $sep = (strlen($baseUrl) > 1) ? '&' : '?';
    } else {
        $sep = '?';
    }

    $html = '';
    if ($total > 1) {
        $html .= '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-end mb-0">';

        // Prev
        $prevDisabled = $page <= 1 ? ' disabled' : '';
        $prevHref     = $page <= 1 ? '#' : $baseUrl . 'page=' . ($page - 1);
        $html .= '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . $prevHref . '">&laquo;</a></li>';

        // Page numbers (show max 7)
        $start = max(1, $page - 3);
        $end   = min($total, $start + 6);
        $start = max(1, $end - 6);
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $page) ? ' active' : '';
            $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a></li>';
        }

        // Next
        $nextDisabled = $page >= $total ? ' disabled' : '';
        $nextHref     = $page >= $total ? '#' : $baseUrl . 'page=' . ($page + 1);
        $html .= '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . $nextHref . '">&raquo;</a></li>';

        $html .= '</ul></nav>';
    }

    return [
        'page'        => $page,
        'limit'       => $perPage,
        'offset'      => ($page - 1) * $perPage,
        'total_pages' => $total,
        'html'        => $html,
    ];
}
