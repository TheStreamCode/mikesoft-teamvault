/* global mstvNoticeData, ajaxurl */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.mstv-storage-security-notice').forEach(function (notice) {
            notice.addEventListener('click', function (e) {
                if (!e.target.classList.contains('notice-dismiss')) return;
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mstv_dismiss_storage_notice&_ajax_nonce=' + encodeURIComponent(mstvNoticeData.dismissNonce),
                });
            });
        });
    });
}());
