<style>
.pm-expense-item-label{display:block;font-size:.7rem;font-weight:700;color:#64748b;margin:0 0 .3rem .1rem}.pm-expense-item-row{padding:.75rem 0;border-bottom:1px solid #eef2f7}.pm-expense-item-row:last-child{border-bottom:0}@media(max-width:640px){.pm-expense-item-row{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:.75rem!important;padding:1rem 0}.pm-expense-desc{grid-column:1/-1!important}.pm-expense-qty,.pm-expense-price{grid-column:span 1!important}.pm-expense-total-wrap{grid-column:1/2!important}.pm-expense-remove{grid-column:2/3!important;justify-content:flex-end}.pm-expense-item-row .pm-input{width:100%!important;min-width:0!important}.pm-expense-item-total{min-height:42px}}
@media(max-width:380px){.pm-expense-item-row{grid-template-columns:1fr!important}.pm-expense-desc,.pm-expense-qty,.pm-expense-price,.pm-expense-total-wrap,.pm-expense-remove{grid-column:1/-1!important}.pm-expense-remove{justify-content:flex-start}}
/* These are programmatic file pickers. Global professional form CSS must never make them visible. */
#pm-receipt-file,#pm-receipt-camera{display:none!important;visibility:hidden!important;position:absolute!important;width:1px!important;height:1px!important;opacity:0!important;pointer-events:none!important;}
</style>
{{-- Expense itemization + receipt/PDF extraction UI. --}}
<script>
    (function () {
        var itemRowCount = 0;
        var pmCurrencySymbol = @json($siteSettings->default_currency_symbol ?? 'UGX');
        var pmCurrencyDecimals = {{ $siteSettings->default_currency_decimals ?? 0 }};
        var receiptEndpoint = @json(route('expenses.store'));

        function pmFormatMoney(amount) {
            return pmCurrencySymbol + ' ' + Number(amount || 0).toLocaleString(undefined, {
                minimumFractionDigits: pmCurrencyDecimals,
                maximumFractionDigits: pmCurrencyDecimals
            });
        }

        function pmAddExpenseItemRow(container, item) {
            item = item || {};
            var index = itemRowCount++;
            var row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 items-start pm-expense-item-row';
            row.innerHTML =
                '<div class="col-span-5 pm-expense-desc"><label class="pm-expense-item-label">Item</label>' +
                    '<input type="text" name="items[' + index + '][description]" placeholder="e.g. Sugar" class="pm-input text-sm" required>' +
                '</div>' +
                '<div class="col-span-2 pm-expense-qty"><label class="pm-expense-item-label">Quantity</label>' +
                    '<input type="number" name="items[' + index + '][quantity]" placeholder="Qty" value="1" min="0.01" step="0.01" inputmode="decimal" class="pm-input text-sm pm-expense-item-qty" required>' +
                '</div>' +
                '<div class="col-span-2 pm-expense-price"><label class="pm-expense-item-label">Unit Price</label>' +
                    '<input type="number" name="items[' + index + '][unit_price]" placeholder="Unit price" step="0.01" inputmode="decimal" class="pm-input text-sm pm-expense-item-price" required>' +
                '</div>' +
                '<div class="col-span-2 pm-expense-total-wrap"><label class="pm-expense-item-label">Line Total</label><div class="flex items-center min-h-[44px] text-sm font-semibold text-slate-700 pm-expense-item-total">' + pmFormatMoney(0) + '</div></div>' +
                '<div class="col-span-1 flex items-end h-full pb-1 pm-expense-remove">' +
                    '<button type="button" class="text-rose-500 hover:text-rose-700" aria-label="Remove line item" onclick="this.closest(\'.pm-expense-item-row\').remove(); pmRecalcExpenseTotal();">' +
                        '<i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>' +
                    '</button>' +
                '</div>';

            container.appendChild(row);
            row.querySelector('[name$="[description]"]').value = item.description || '';
            row.querySelector('.pm-expense-item-qty').value = item.quantity != null ? item.quantity : 1;
            row.querySelector('.pm-expense-item-price').value = item.unit_price != null ? item.unit_price : '';

            row.querySelectorAll('.pm-expense-item-qty, .pm-expense-item-price').forEach(function (input) {
                input.addEventListener('input', function () {
                    pmRecalcExpenseRowTotal(row);
                    pmRecalcExpenseTotal();
                });
            });
            pmRecalcExpenseRowTotal(row);
        }

        function pmRecalcExpenseRowTotal(row) {
            var qty = parseFloat(row.querySelector('.pm-expense-item-qty').value) || 0;
            var price = parseFloat(row.querySelector('.pm-expense-item-price').value) || 0;
            row.querySelector('.pm-expense-item-total').textContent = pmFormatMoney(qty * price);
        }

        window.pmRecalcExpenseTotal = function () {
            var total = 0;
            document.querySelectorAll('.pm-expense-item-row').forEach(function (row) {
                var qty = parseFloat(row.querySelector('.pm-expense-item-qty').value) || 0;
                var price = parseFloat(row.querySelector('.pm-expense-item-price').value) || 0;
                total += qty * price;
            });
            var totalEl = document.getElementById('pm-expense-items-grand-total');
            if (totalEl) totalEl.textContent = pmFormatMoney(total);

            // Keep the real form amount synchronized even while its visual
            // field is hidden. This prevents generic form/browser validation
            // from treating an extracted itemized receipt as an empty amount.
            var toggle = document.getElementById('pm-expense-itemize-toggle');
            var amountField = document.getElementById('field-amount');
            if (toggle && toggle.checked && amountField) {
                amountField.value = total.toFixed(Math.max(0, pmCurrencyDecimals));
                amountField.required = false;
            }
        };

        function pmToggleExpenseItemize(checked) {
            var amountField = document.getElementById('field-amount');
            if (!amountField) return;
            var amountWrapper = amountField.closest('div');
            var itemsWrapper = document.getElementById('pm-expense-items-wrapper');
            if (amountWrapper) amountWrapper.style.display = checked ? 'none' : 'block';
            if (itemsWrapper) itemsWrapper.style.display = checked ? 'block' : 'none';

            // The generic CRUD form renders Amount as HTML `required`.
            // In itemized mode the field is hidden, so leaving `required`
            // on it makes the browser silently block Save before Laravel
            // ever receives the request. Disable native required validation
            // while itemizing; the server still validates/recomputes amount.
            amountField.required = !checked;
            amountField.setAttribute('aria-required', checked ? 'false' : 'true');

            if (checked) {
                pmRecalcExpenseTotal();
            }
        }

        function pmReceiptStatus(message, error) {
            var el = document.getElementById('pm-receipt-status');
            if (!el) return;
            el.textContent = message || '';
            el.className = 'text-xs mt-2 ' + (error ? 'text-rose-600' : 'text-slate-600');
        }

        function pmSetField(id, value) {
            if (value == null || value === '') return;
            var el = document.getElementById(id);
            if (el) {
                el.value = value;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function pmApplyReceipt(data) {
            pmSetField('field-category', data.category || 'Other');
            pmSetField('field-spent_at', data.spent_at);
            pmSetField('field-payment_method', data.payment_method);

            var notes = [];
            if (data.merchant) notes.push('Merchant: ' + data.merchant);
            if (data.receipt_number) notes.push('Receipt #: ' + data.receipt_number);
            if (data.notes) notes.push(data.notes);
            var notesField = document.getElementById('field-notes');
            if (notesField && !notesField.value.trim() && notes.length) notesField.value = notes.join(' • ');

            var items = Array.isArray(data.items) ? data.items : [];
            if (items.length) {
                var toggle = document.getElementById('pm-expense-itemize-toggle');
                if (toggle) toggle.checked = true;
                pmToggleExpenseItemize(true);
                var container = document.getElementById('pm-expense-items-container');
                if (container) {
                    container.innerHTML = '';
                    itemRowCount = 0;
                    items.forEach(function (item) { pmAddExpenseItemRow(container, item); });
                    pmRecalcExpenseTotal();
                }
            } else if (data.total != null) {
                pmSetField('field-amount', data.total);
            }
        }

        async function pmExtractReceipt(file) {
            if (!file) return;
            var btn = document.getElementById('pm-receipt-upload-btn');
            var oldHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Reading receipt...';
            }
            pmReceiptStatus('Reading the receipt and extracting expense details...', false);

            try {
                var formData = new FormData();
                formData.append('receipt_extract', '1');
                formData.append('receipt_file', file);
                var csrf = document.querySelector('meta[name="csrf-token"]');
                var response = await fetch(receiptEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf ? csrf.content : ''
                    },
                    body: formData
                });
                var payload = await response.json().catch(function () { return {}; });
                if (!response.ok) throw new Error(payload.message || 'Receipt extraction failed.');
                pmApplyReceipt(payload.data || {});
                pmReceiptStatus('Receipt extracted. Review the filled details and line items, then click Save.', false);
            } catch (error) {
                pmReceiptStatus(error.message || 'Receipt extraction failed.', true);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = oldHtml;
                }
                var input = document.getElementById('pm-receipt-file');
                if (input) input.value = '';
            }
        }

        function pmInjectExpenseItemizeUi() {
            var amountField = document.getElementById('field-amount');
            if (!amountField || document.getElementById('pm-expense-itemize-toggle')) return;
            var amountWrapper = amountField.closest('div');
            if (!amountWrapper) return;

            var receipt = document.createElement('div');
            receipt.id = 'pm-receipt-extractor';
            receipt.className = 'rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 mb-2';
            receipt.innerHTML =
                '<div class="flex items-start gap-3">' +
                    '<div class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-[var(--brand-1)]"><i class="fa-solid fa-file-invoice"></i></div>' +
                    '<div class="flex-1 min-w-0">' +
                        '<div class="font-semibold text-sm text-slate-800">Scan / Upload Receipt or Document</div>' +
                        '<div class="text-xs text-slate-500 mt-1">Scan a receipt/document with your device camera, or upload a PDF/image. My Digital Diary will extract the expense and line items automatically for review before saving.</div>' +
                        '<input id="pm-receipt-file" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" hidden aria-hidden="true" tabindex="-1" style="display:none!important">' +
                        '<input id="pm-receipt-camera" type="file" accept="image/*" capture="environment" hidden aria-hidden="true" tabindex="-1" style="display:none!important">' +
                        '<div class="mt-3 flex flex-wrap gap-2">' +
                            '<button type="button" id="pm-receipt-scan-btn" class="px-3 py-2 rounded-lg bg-[var(--brand-1)] text-white text-sm font-medium hover:opacity-90">' +
                                '<i class="fa-solid fa-camera mr-1"></i> Scan Document' +
                            '</button>' +
                            '<button type="button" id="pm-receipt-upload-btn" class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">' +
                                '<i class="fa-solid fa-file-arrow-up mr-1"></i> Choose PDF / Image' +
                            '</button>' +
                        '</div>' +
                        '<div id="pm-receipt-status" class="text-xs mt-2 text-slate-600"></div>' +
                    '</div>' +
                '</div>';
            amountWrapper.parentNode.insertBefore(receipt, amountWrapper);

            receipt.querySelector('#pm-receipt-scan-btn').addEventListener('click', function () {
                receipt.querySelector('#pm-receipt-camera').click();
            });
            receipt.querySelector('#pm-receipt-upload-btn').addEventListener('click', function () {
                receipt.querySelector('#pm-receipt-file').click();
            });
            receipt.querySelector('#pm-receipt-camera').addEventListener('change', function () {
                if (this.files && this.files[0]) pmExtractReceipt(this.files[0]);
            });
            receipt.querySelector('#pm-receipt-file').addEventListener('change', function () {
                if (this.files && this.files[0]) pmExtractReceipt(this.files[0]);
            });

            var toggleWrapper = document.createElement('div');
            toggleWrapper.className = 'flex items-center gap-2 -mt-2';
            toggleWrapper.innerHTML =
                '<input type="checkbox" id="pm-expense-itemize-toggle" onchange="pmToggleExpenseItemizeHandler(this.checked)" class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">' +
                '<label for="pm-expense-itemize-toggle" class="text-sm text-slate-700">Itemize this expense (multiple line items) instead of a single amount</label>';
            amountWrapper.parentNode.insertBefore(toggleWrapper, amountWrapper.nextSibling);

            var itemsWrapper = document.createElement('div');
            itemsWrapper.id = 'pm-expense-items-wrapper';
            itemsWrapper.style.display = 'none';
            itemsWrapper.className = 'space-y-2 border border-slate-200 rounded-lg p-3';
            itemsWrapper.innerHTML =
                '<div class="grid grid-cols-12 gap-2 text-xs font-medium text-slate-500 uppercase tracking-wide">' +
                    '<div class="col-span-5">Description</div><div class="col-span-2">Qty</div>' +
                    '<div class="col-span-2">Unit Price</div><div class="col-span-2">Total</div><div class="col-span-1"></div>' +
                '</div>' +
                '<div id="pm-expense-items-container"></div>' +
                '<button type="button" class="text-sm text-[var(--brand-1)] hover:underline" onclick="pmAddExpenseItemRowHandler()">' +
                    '<i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Add line item' +
                '</button>' +
                '<div class="flex justify-between text-sm font-semibold border-t border-slate-100 pt-2">' +
                    '<span>Grand Total</span><span id="pm-expense-items-grand-total">' + pmFormatMoney(0) + '</span>' +
                '</div>';
            toggleWrapper.parentNode.insertBefore(itemsWrapper, toggleWrapper.nextSibling);

            var form = amountField.closest('form');
            if (form && !form.dataset.expenseItemSaveBound) {
                form.dataset.expenseItemSaveBound = '1';
                form.addEventListener('submit', function (event) {
                    var itemizeToggle = document.getElementById('pm-expense-itemize-toggle');
                    var amount = document.getElementById('field-amount');

                    if (itemizeToggle && itemizeToggle.checked) {
                        pmRecalcExpenseTotal();
                        if (amount) amount.required = false;

                        var rows = Array.from(document.querySelectorAll('.pm-expense-item-row'));
                        if (!rows.length) {
                            event.preventDefault();
                            pmReceiptStatus('Add at least one line item, or turn off Itemize and enter a single amount.', true);
                            return;
                        }

                        var invalidRow = rows.find(function (row) {
                            var description = row.querySelector('[name$="[description]"]');
                            var qty = row.querySelector('.pm-expense-item-qty');
                            var price = row.querySelector('.pm-expense-item-price');
                            return !description || !description.value.trim() ||
                                !qty || !(parseFloat(qty.value) > 0) ||
                                !price || Number.isNaN(parseFloat(price.value));
                        });

                        if (invalidRow) {
                            event.preventDefault();
                            pmReceiptStatus('Please review the extracted line items. Every item needs a description, quantity greater than 0, and unit price.', true);
                            invalidRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            return;
                        }
                    } else if (amount) {
                        amount.required = true;
                    }
                });
            }
        }

        window.pmToggleExpenseItemizeHandler = function (checked) { pmToggleExpenseItemize(checked); };
        window.pmAddExpenseItemRowHandler = function () {
            var container = document.getElementById('pm-expense-items-container');
            if (container) pmAddExpenseItemRow(container, {});
        };

        document.addEventListener('DOMContentLoaded', pmInjectExpenseItemizeUi);
        document.addEventListener('click', function (event) {
            if (event.target.closest('[onclick*="openCrudCreateModal"]')) {
                setTimeout(pmInjectExpenseItemizeUi, 50);
            }
        });
    })();
</script>
