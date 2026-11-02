    </div><!-- /.page-content -->
</main><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- ═══ PDF PREVIEW MODAL (opens on same page) ════════ -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:90vw;">
        <div class="modal-content" style="background:var(--surface);border:1px solid var(--border);">
            <div class="modal-header" style="border-color:var(--border);padding:12px 18px;">
                <h5 class="modal-title" id="pdfModalLabel" style="font-size:14px;font-weight:700;">
                    <i class="fa-solid fa-file-pdf me-2" style="color:#e74c3c;"></i>
                    Report Preview
                </h5>
                <div class="d-flex gap-2 align-items-center">
                    <a id="pdfModalCsvBtn" href="#" class="btn btn-sm btn-success" target="_blank">
                        <i class="fa-solid fa-file-csv me-1"></i>Download CSV
                    </a>
                    <button class="btn btn-sm btn-primary" onclick="document.getElementById('pdfFrame').contentWindow.print()">
                        <i class="fa-solid fa-print me-1"></i>Print / Save PDF
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="filter:<?php echo isset($_SESSION['theme']) && $_SESSION['theme']==='dark'?'invert(1)':'none'; ?>;"></button>
                </div>
            </div>
            <div class="modal-body" style="padding:0;height:75vh;">
                <iframe id="pdfFrame" src="about:blank"
                        style="width:100%;height:100%;border:none;"
                        title="Report Preview">
                </iframe>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="toastContainer"
     style="position:fixed;bottom:24px;right:24px;z-index:9999;
            display:flex;flex-direction:column;gap:10px;min-width:280px;pointer-events:none;">
</div>

<!-- Bootstrap 5 JS (offline) -->
<script src="<?= BASE_URL ?>frontend/assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>
<!-- Chart.js (offline) -->
<script src="<?= BASE_URL ?>frontend/assets/vendors/chartjs/chart.umd.min.js"></script>

<script>
/* ═══════════════════════════════════════════════════
   1. TOAST NOTIFICATIONS (auto-dismiss popups)
═══════════════════════════════════════════════════ */
function showToast(message, type, duration) {
    type     = type     || 'success';
    duration = duration || 2500;
    var icons  = {success:'fa-circle-check',danger:'fa-circle-xmark',warning:'fa-triangle-exclamation',info:'fa-circle-info'};
    var colors = {success:'#27ae60',danger:'#e74c3c',warning:'#f39c12',info:'#2980b9'};
    var icon   = icons[type]  || icons.info;
    var color  = colors[type] || colors.info;

    var t = document.createElement('div');
    t.style.cssText = [
        'background:var(--surface,#fff)',
        'border:1px solid var(--border,#e3e8ef)',
        'border-left:4px solid '+color,
        'border-radius:8px',
        'padding:12px 16px',
        'box-shadow:0 4px 20px rgba(0,0,0,.18)',
        'display:flex','align-items:center','gap:10px',
        'font-size:13px','color:var(--text,#1e2a35)',
        'opacity:0','transform:translateX(40px)',
        'transition:all .28s ease','cursor:pointer','pointer-events:auto'
    ].join(';');
    t.innerHTML = '<i class="fa-solid '+icon+'" style="color:'+color+';font-size:16px;flex-shrink:0;"></i>'
                + '<span style="flex:1;">'+message+'</span>'
                + '<i class="fa-solid fa-xmark" style="color:#aaa;font-size:12px;"></i>';
    document.getElementById('toastContainer').appendChild(t);
    requestAnimationFrame(function(){requestAnimationFrame(function(){t.style.opacity='1';t.style.transform='translateX(0)';});});
    function dismiss(){t.style.opacity='0';t.style.transform='translateX(40px)';setTimeout(function(){if(t.parentNode)t.parentNode.removeChild(t);},300);}
    t.addEventListener('click',dismiss);
    setTimeout(dismiss, duration);
}

/* Show PHP-set toast on page load */
<?php
if (!empty($_SESSION['toast_type']) && !empty($_SESSION['toast_msg'])):
    $tm = $_SESSION['toast_msg']; $tt = $_SESSION['toast_type'];
    unset($_SESSION['toast_type'],$_SESSION['toast_msg']);
?>
document.addEventListener('DOMContentLoaded',function(){
    showToast(<?= json_encode(h($tm)) ?>,<?= json_encode($tt) ?>);
});
<?php endif; ?>

/* ═══════════════════════════════════════════════════
   2. CHART.JS DARK MODE DEFAULTS
═══════════════════════════════════════════════════ */
(function(){
    var dark = document.documentElement.getAttribute('data-theme')==='dark';
    var g = dark?'rgba(255,255,255,.06)':'rgba(0,0,0,.06)';
    var l = dark?'#8b94a3':'#6c757d';
    if(window.Chart){Chart.defaults.color=l;Chart.defaults.borderColor=g;}
})();

/* ═══════════════════════════════════════════════════
   3. SIDEBAR TOGGLE — state saved in localStorage
      Persists across all page navigations.
═══════════════════════════════════════════════════ */
(function(){
    var KEY = 'rdb_sidebar_collapsed';

    function updateIcon(collapsed){
        var icon = document.getElementById('sidebarToggleIcon');
        if (!icon) return;
        icon.className = collapsed ? 'fa-solid fa-bars-staggered' : 'fa-solid fa-bars';
    }

    /* Set icon on load based on current state */
    document.addEventListener('DOMContentLoaded', function(){
        var collapsed = document.body.classList.contains('sidebar-collapsed');
        updateIcon(collapsed);

        var btn = document.getElementById('sidebarToggle');
        if (!btn) return;

        btn.addEventListener('click', function(e){
            e.preventDefault();
            var isNowCollapsed = !document.body.classList.contains('sidebar-collapsed');
            if (isNowCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
            localStorage.setItem(KEY, isNowCollapsed ? '1' : '0');
            updateIcon(isNowCollapsed);
        });
    });
})();

/* ═══════════════════════════════════════════════════
   4. TABLE COLUMN SORT (click any <th>)
═══════════════════════════════════════════════════ */
(function(){
    document.querySelectorAll('table').forEach(function(tbl){
        tbl.querySelectorAll('thead th').forEach(function(th, colIdx){
            th.style.cursor='pointer';
            th.title='Click to sort';
            var asc=true;
            th.addEventListener('click',function(){
                var tbody=tbl.querySelector('tbody');
                if(!tbody)return;
                var rows=Array.from(tbody.querySelectorAll('tr'));
                var type=th.getAttribute('data-sort')||'text';
                rows.sort(function(a,b){
                    var av=(a.querySelectorAll('td')[colIdx]||{}).innerText||'';
                    var bv=(b.querySelectorAll('td')[colIdx]||{}).innerText||'';
                    var res = type==='num'
                        ? (parseFloat(av.replace(/[^0-9.\-]/g,''))||0)-(parseFloat(bv.replace(/[^0-9.\-]/g,''))||0)
                        : av.localeCompare(bv);
                    return asc?res:-res;
                });
                asc=!asc;
                tbl.querySelectorAll('thead th').forEach(function(h){h.innerHTML=h.innerHTML.replace(/ [▲▼]$/,'');});
                th.innerHTML+=asc?' ▼':' ▲';
                rows.forEach(function(r){tbody.appendChild(r);});
            });
        });
    });
})();

/* ═══════════════════════════════════════════════════
   5. RTL DIRECTION (set from data-lang on <html>)
═══════════════════════════════════════════════════ */
(function(){
    var lang=document.documentElement.getAttribute('data-lang')||'en';
    if(['ps','fa','ar'].indexOf(lang)!==-1){
        document.documentElement.setAttribute('dir','rtl');
    }
})();

/* ═══════════════════════════════════════════════════
   6. NOTIFICATION AUTO-REFRESH (poll every 30s)
═══════════════════════════════════════════════════ */
(function(){
    function refreshNotifCount(){
        var badge = document.querySelector('.notif-badge-count');
        if(!badge) return;
        var xhr = new XMLHttpRequest();
        xhr.open('GET','<?= BASE_URL ?>frontend/pages/notif_count.php',true);
        xhr.onload = function(){
            if(xhr.status===200){
                var count = parseInt(xhr.responseText,10)||0;
                if(count>0){badge.textContent=count;badge.style.display='inline';}
                else{badge.style.display='none';}
            }
        };
        xhr.send();
    }
    setInterval(refreshNotifCount, 30000);
})();

/* ═══════════════════════════════════════════════════
   7. PDF MODAL — open report in Bootstrap modal
      Triggered by buttons with class .pdf-export-btn
      and data-export-url attribute.
═══════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function(){
    /* Wire up all PDF export buttons */
    document.querySelectorAll('.pdf-export-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var url  = btn.getAttribute('data-export-url');
            if (!url) return;
            /* Set iframe src to load the report */
            var frame = document.getElementById('pdfFrame');
            var csv   = document.getElementById('pdfModalCsvBtn');
            if (frame) { frame.src = url; }
            if (csv)   { csv.href  = url + '&fmt=csv'; }
            /* Open modal */
            var modal = new bootstrap.Modal(document.getElementById('pdfModal'));
            modal.show();
        });
    });
});
</script>

<?php if (isset($chartScripts)): ?>
<script>
<?= $chartScripts ?>
</script>
<?php endif; ?>

</body>
</html>
