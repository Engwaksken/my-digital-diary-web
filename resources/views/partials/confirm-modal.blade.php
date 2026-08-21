<dialog id="pm-global-confirm-modal" class="pm-dialog pm-dialog-sm pm-modal-shell" aria-labelledby="pm-global-confirm-title">
    <div class="pm-modal-content">
        <div class="pm-modal-header">
            <div class="pm-modal-heading">
                <div class="pm-modal-icon danger"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                <div>
                    <h2 id="pm-global-confirm-title" class="pm-modal-title">Confirm action</h2>
                    <p id="pm-global-confirm-message" class="pm-modal-description">Are you sure you want to continue?</p>
                </div>
            </div>
            <button type="button" class="pm-modal-close" data-confirm-cancel aria-label="Close confirmation"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="pm-modal-body">
            <div class="rounded-xl border border-rose-100 bg-rose-50/70 p-3 text-sm text-rose-700" id="pm-global-confirm-warning">
                Please review this action before continuing.
            </div>
        </div>
        <div class="pm-modal-footer">
            <button type="button" class="pm-btn-cancel" data-confirm-cancel>Cancel</button>
            <button type="button" class="pm-btn-danger" id="pm-global-confirm-submit"><i class="fa-solid fa-trash-can"></i><span>Delete</span></button>
        </div>
    </div>
</dialog>
<script>
(function () {
    'use strict';

    let pendingAction = null;

    function getModal() {
        return document.getElementById('pm-global-confirm-modal');
    }

    function closeConfirm() {
        const modal = getModal();
        pendingAction = null;
        if (!modal) return;
        if (modal.open && typeof modal.close === 'function') {
            modal.close();
        } else {
            modal.removeAttribute('open');
        }
    }

    window.pmCloseConfirmModal = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        closeConfirm();
        return false;
    };

    window.pmConfirmAction = function (options) {
        options = options || {};
        const modal = getModal();
        if (!modal) return false;

        const title = document.getElementById('pm-global-confirm-title');
        const message = document.getElementById('pm-global-confirm-message');
        const submit = document.getElementById('pm-global-confirm-submit');
        const label = submit ? submit.querySelector('span') : null;

        if (title) title.textContent = options.title || 'Confirm action';
        if (message) message.textContent = options.message || 'Are you sure you want to continue?';
        if (label) label.textContent = options.confirmText || 'Delete';

        pendingAction = typeof options.onConfirm === 'function' ? options.onConfirm : null;

        if (!modal.open) {
            if (typeof modal.showModal === 'function') modal.showModal();
            else modal.setAttribute('open', '');
        }
        return true;
    };

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (!target || typeof target.closest !== 'function') return;

        if (target.closest('[data-confirm-cancel]')) {
            window.pmCloseConfirmModal(event);
            return;
        }

        const button = target.closest('[data-confirm-click]');
        if (!button) return;

        event.preventDefault();
        event.stopPropagation();

        window.pmConfirmAction({
            title: button.dataset.confirmTitle || 'Confirm action',
            message: button.dataset.confirmClick || 'Are you sure you want to continue?',
            confirmText: button.dataset.confirmText || 'Continue',
            onConfirm: function () {
                const formId = button.getAttribute('form');
                const form = formId ? document.getElementById(formId) : button.closest('form');
                if (!form) return;
                form.dataset.confirmBypass = '1';
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }
        });
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('form[data-confirm]')) return;

        if (form.dataset.confirmBypass === '1') {
            delete form.dataset.confirmBypass;
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        window.pmConfirmAction({
            title: form.dataset.confirmTitle || 'Confirm action',
            message: form.dataset.confirm || 'Are you sure you want to continue?',
            confirmText: form.dataset.confirmText || 'Continue',
            onConfirm: function () {
                form.dataset.confirmBypass = '1';
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }
        });
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const modal = getModal();
        if (modal && modal.open) closeConfirm();
    }, true);

    const submit = document.getElementById('pm-global-confirm-submit');
    if (submit) {
        submit.addEventListener('click', function (event) {
            event.preventDefault();
            const action = pendingAction;
            pendingAction = null;
            closeConfirm();
            if (typeof action === 'function') window.setTimeout(action, 0);
        });
    }

    const modal = getModal();
    if (modal) {
        // Clicking the native dialog backdrop (outside its box) closes it.
        modal.addEventListener('click', function (event) {
            if (event.target !== modal) return;
            const rect = modal.getBoundingClientRect();
            const inside = event.clientX >= rect.left && event.clientX <= rect.right &&
                           event.clientY >= rect.top && event.clientY <= rect.bottom;
            if (!inside) closeConfirm();
        });

        modal.addEventListener('cancel', function (event) {
            event.preventDefault();
            closeConfirm();
        });
    }

    // Never restore a confirmation dialog as open from browser bfcache.
    window.addEventListener('pageshow', closeConfirm);
})();
</script>
