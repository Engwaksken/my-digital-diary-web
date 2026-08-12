{{--
    Adds an "Itemize this expense" option to the expense create/edit
    modal — multiple description/quantity/unit-price rows (like an
    invoice) instead of one lump amount. See
    ExpenseController::store()/update() for how the submitted items[]
    array is turned into expense_items rows and summed into `amount`.

    Only offered on CREATE — editing an already-itemized expense's line
    items isn't supported through this UI yet (the modal doesn't know
    about an expense's existing items when it opens for editing); editing
    other fields of an itemized expense leaves its items untouched
    (see the controller's $request->has('items') check).
--}}
<script>
    (function () {
        var itemRowCount = 0;
        var pmCurrencySymbol = @json($siteSettings->default_currency_symbol ?? 'UGX');
        var pmCurrencyDecimals = {{ $siteSettings->default_currency_decimals ?? 0 }};

        function pmFormatMoney(amount) {
            return pmCurrencySymbol + ' ' + amount.toLocaleString(undefined, { minimumFractionDigits: pmCurrencyDecimals, maximumFractionDigits: pmCurrencyDecimals });
        }

        function pmAddExpenseItemRow(container) {
            var index = itemRowCount++;
            var row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 items-start pm-expense-item-row';
            row.innerHTML =
                '<div class="col-span-5">' +
                    '<input type="text" name="items[' + index + '][description]" placeholder="Description" class="pm-input text-sm" required>' +
                '</div>' +
                '<div class="col-span-2">' +
                    '<input type="number" name="items[' + index + '][quantity]" placeholder="Qty" value="1" min="0.01" step="0.01" class="pm-input text-sm pm-expense-item-qty" required>' +
                '</div>' +
                '<div class="col-span-2">' +
                    '<input type="number" name="items[' + index + '][unit_price]" placeholder="Unit price" min="0" step="0.01" class="pm-input text-sm pm-expense-item-price" required>' +
                '</div>' +
                '<div class="col-span-2 flex items-center h-full pt-2 text-sm text-slate-600 pm-expense-item-total">' + pmFormatMoney(0) + '</div>' +
                '<div class="col-span-1 flex items-center h-full pt-1">' +
                    '<button type="button" class="text-rose-500 hover:text-rose-700" aria-label="Remove line item" onclick="this.closest(\'.pm-expense-item-row\').remove(); pmRecalcExpenseTotal();">' +
                        '<i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>' +
                    '</button>' +
                '</div>';

            container.appendChild(row);

            row.querySelectorAll('.pm-expense-item-qty, .pm-expense-item-price').forEach(function (input) {
                input.addEventListener('input', function () {
                    pmRecalcExpenseRowTotal(row);
                    pmRecalcExpenseTotal();
                });
            });
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
            if (totalEl) { totalEl.textContent = pmFormatMoney(total); }
        };

        function pmToggleExpenseItemize(checked) {
            var amountField = document.getElementById('field-amount');
            if (!amountField) { return; }
            var amountWrapper = amountField.closest('div');
            var itemsWrapper = document.getElementById('pm-expense-items-wrapper');

            if (amountWrapper) { amountWrapper.style.display = checked ? 'none' : 'block'; }
            if (itemsWrapper) { itemsWrapper.style.display = checked ? 'block' : 'none'; }

            // Hiding the amount field doesn't stop it submitting a stale
            // value — clearing it here means an itemized expense's amount
            // is unambiguously computed server-side from items, never
            // from a leftover manual entry.
            if (checked) { amountField.value = ''; }
        }

        function pmInjectExpenseItemizeUi() {
            var amountField = document.getElementById('field-amount');
            if (!amountField || document.getElementById('pm-expense-itemize-toggle')) { return; }

            var amountWrapper = amountField.closest('div');
            if (!amountWrapper) { return; }

            var toggleWrapper = document.createElement('div');
            toggleWrapper.className = 'flex items-center gap-2 -mt-2';
            toggleWrapper.innerHTML =
                '<input type="checkbox" id="pm-expense-itemize-toggle" onchange="pmToggleExpenseItemizeHandler(this.checked)" ' +
                'class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">' +
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
        }

        window.pmToggleExpenseItemizeHandler = function (checked) { pmToggleExpenseItemize(checked); };
        window.pmAddExpenseItemRowHandler = function () {
            var container = document.getElementById('pm-expense-items-container');
            if (container) { pmAddExpenseItemRow(container); }
        };

        // Injected once on load, and re-checked every time the create
        // modal opens (the modal/form markup is shared and already exists
        // in the DOM, just hidden, so this only needs to run once — but
        // re-running harmlessly no-ops thanks to the
        // getElementById('pm-expense-itemize-toggle') guard above).
        document.addEventListener('DOMContentLoaded', pmInjectExpenseItemizeUi);
        document.addEventListener('click', function (event) {
            if (event.target.closest('[onclick*="openCrudCreateModal"]')) {
                setTimeout(pmInjectExpenseItemizeUi, 50);
            }
        });
    })();
</script>
