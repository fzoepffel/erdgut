<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('boxSetup', (initial) => ({
            marginRate: initial.margin_rate,
            // Eggs add-on price (config('box.eggs_dairy_add_on_eur')). Customers
            // with the eggs flag pay this on top of their size base TTC, so it
            // must inflate their per-customer max (and shift the target by the
            // planning margin) accordingly.
            eggsAddOnEur: Number(initial.eggs_add_on_eur ?? 0),
            stockById: Object.fromEntries(initial.stock.map(r => [r.product_id, { ...r, missing_stock: !!r.missing_stock }])),
            stockOutOfWeekLabel: @js(__('admin.box_setup.stock_out_of_week')),
            sizes: Object.entries(initial.sizes).map(([key, lines]) => ({
                key,
                meta: initial.size_meta[key],
                addProductId: '',
                lines: lines.map(l => ({ product_id: Number(l.product_id), quantity: Number(l.quantity) })),
            })),
            // Per-customer composition: each entry holds the resolved lines
            // (size template − allergen-conflicting items + auto-added eggs),
            // the precomputed flags, the admin's OK acknowledgement, and
                // any UI scratchpad state (expansion, line additions).
            // Eggs candidates panel: list of `is_egg_addon=true` products with
            // weekly stock. Each entry: { product_id, name, producer_name,
            // unit_price, allergens: [...] }.
            eggCandidates: Array.isArray(initial.egg_candidates)
                ? initial.egg_candidates.map(c => ({
                    product_id: Number(c.product_id),
                    name: String(c.name ?? ''),
                    producer_name: String(c.producer_name ?? ''),
                    unit_price: Number(c.unit_price ?? 0),
                    allergens: Array.isArray(c.allergens) ? c.allergens.map(String) : [],
                }))
                : [],
            customers: (initial.customers ?? []).map(c => ({
                // Form key — `s_<user_id>` for subscriptions, `t_<order_id>`
                // for trial orders. Used as the array key in customers[<key>]
                // and customer_ok[<key>] so the apply controller knows how to
                // route each row.
                form_key: String(c.form_key ?? ('s_' + (c.user_id ?? 0))),
                kind: String(c.kind ?? 'subscription'),
                user_id: Number(c.user_id),
                subscription_id: c.subscription_id != null ? Number(c.subscription_id) : null,
                order_id: c.order_id != null ? Number(c.order_id) : null,
                box_size: String(c.box_size),
                name: String(c.name ?? ''),
                email: String(c.email ?? ''),
                eggs: !!c.eggs,
                veg_only: !!c.veg_only,
                allergies: Array.isArray(c.allergies) ? c.allergies.map(String) : [],
                note: String(c.note ?? ''),
                lines: Array.isArray(c.regular_lines)
                    ? c.regular_lines.map(l => ({ product_id: Number(l.product_id), quantity: Number(l.quantity) }))
                    : (Array.isArray(c.lines) ? c.lines.map(l => ({ product_id: Number(l.product_id), quantity: Number(l.quantity) })) : []),
                eggsLines: Array.isArray(c.eggs_lines)
                    ? c.eggs_lines.map(l => ({ product_id: Number(l.product_id), quantity: Number(l.quantity) }))
                    : [],
                removed_allergen_items: Array.isArray(c.removed_allergen_items) ? c.removed_allergen_items : [],
                padded_from_leftover: Array.isArray(c.padded_from_leftover) ? c.padded_from_leftover : [],
                eggs_added: !!c.eggs_added,
                eggs_missing: !!c.eggs_missing,
                flags: Array.isArray(c.flags) ? c.flags.map(String) : [],
                ok: !!c.ok, // pre-loaded from server when box previously OK'd
                // 'existing' (saved already) | 'new_match' (no review needed)
                // | 'new_diff' (admin review required). Drives sectioning,
                // colors, and the OK toggle visibility.
                composition_status: String(c.composition_status ?? 'new_diff'),
                expanded: false,
                addProductId: '',
                addEggsProductId: '',
            })),
            sizeLabel(key) {
                const labels = @js([
                    'small' => __('admin.box_setup.size_small'),
                    'medium' => __('admin.box_setup.size_medium'),
                    'large' => __('admin.box_setup.size_large'),
                ]);
                return labels[key] ?? key;
            },
            productName(pid) {
                return this.stockById[pid]?.name ?? '—';
            },
            productUnit(pid) {
                return this.stockById[pid]?.unit ?? '';
            },
            productPrice(pid) {
                return this.stockById[pid]?.unit_price ?? 0;
            },
            retailSum(size) {
                return size.lines.reduce((acc, l) => acc + (this.productPrice(l.product_id) * Math.max(0, l.quantity || 0)), 0);
            },
            retailClass(size) {
                const r = this.retailSum(size);
                if (r > size.meta.max + 0.005) return 'text-rose-700';
                if (r > size.meta.target + 0.005) return 'text-amber-700';
                return 'text-emerald-700';
            },
            progressWidth(size) {
                if (size.meta.max <= 0) return 0;
                const pct = (this.retailSum(size) / size.meta.max) * 100;
                return Math.max(0, Math.min(100, pct));
            },
            progressClass(size) {
                const r = this.retailSum(size);
                if (r > size.meta.max + 0.005) return 'bg-rose-500';
                if (r > size.meta.target + 0.005) return 'bg-amber-400';
                return 'bg-emerald-500';
            },
            targetMarkerPct(size) {
                if (size.meta.max <= 0) return 0;
                const pct = (size.meta.target / size.meta.max) * 100;
                return Math.max(0, Math.min(100, pct));
            },
            get stockRows() {
                const used = {};
                for (const size of this.sizes) {
                    const n = size.meta.n;
                    for (const line of size.lines) {
                        const pid = Number(line.product_id);
                        const qty = Math.max(0, line.quantity || 0);
                        used[pid] = (used[pid] || 0) + qty * n;
                    }
                }
                return Object.values(this.stockById).map(row => {
                    const planned = Number(row.planned ?? 0);
                    const baseline = Number(row.baseline_used ?? 0);
                    const proposed = used[row.product_id] || 0;
                    const total = baseline + proposed;
                    return {
                        ...row,
                        planned,
                        used_total: Math.round(total * 100) / 100,
                        remaining: Math.round((planned - total) * 100) / 100,
                    };
                }).sort((a, b) => a.name.localeCompare(b.name));
            },
            get anyOver() {
                return this.stockRows.some(r => r.remaining < 0);
            },
            addableOptions(size) {
                const used = new Set(size.lines.map(l => Number(l.product_id)));
                return this.stockRows.filter(r => !used.has(Number(r.product_id)) && !r.missing_stock && r.remaining > 0);
            },
            addLine(sizeKey) {
                const size = this.sizes.find(s => s.key === sizeKey);
                if (!size || !size.addProductId) return;
                const pid = Number(size.addProductId);
                size.lines.push({ product_id: pid, quantity: 1 });
                size.addProductId = '';
            },
            removeLine(sizeKey, idx) {
                const size = this.sizes.find(s => s.key === sizeKey);
                if (!size) return;
                size.lines.splice(idx, 1);
            },
            incQty(sizeKey, idx) {
                const size = this.sizes.find(s => s.key === sizeKey);
                if (!size) return;
                size.lines[idx].quantity = Number(size.lines[idx].quantity || 0) + 1;
            },
            decQty(sizeKey, idx) {
                const size = this.sizes.find(s => s.key === sizeKey);
                if (!size) return;
                const next = Number(size.lines[idx].quantity || 0) - 1;
                size.lines[idx].quantity = next < 1 ? 1 : next;
            },
            formatNumber(v) {
                const n = Number(v) || 0;
                return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 2 }).format(n);
            },
            formatEur(v) {
                const n = Number(v) || 0;
                return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' €';
            },

            // ---- Per-customer helpers ----
            // Build a unified allergen lookup for any product id we might
            // surface — both regular stock products and eggs-candidate
            // products (since eggs are filtered out of the standard stock
            // list but still need allergen indicators).
            allergensFor(pid) {
                pid = Number(pid);
                const stockRow = this.stockById[pid];
                if (stockRow && Array.isArray(stockRow.allergens)) return stockRow.allergens.map(String);
                const egg = this.eggCandidates.find(e => Number(e.product_id) === pid);
                if (egg) return egg.allergens;
                return [];
            },
            productNameAny(pid) {
                pid = Number(pid);
                const stockRow = this.stockById[pid];
                if (stockRow && stockRow.name) return stockRow.name;
                const egg = this.eggCandidates.find(e => Number(e.product_id) === pid);
                if (egg) return egg.name;
                return '—';
            },
            productPriceAny(pid) {
                pid = Number(pid);
                const stockRow = this.stockById[pid];
                if (stockRow && stockRow.unit_price !== undefined) return Number(stockRow.unit_price);
                const egg = this.eggCandidates.find(e => Number(e.product_id) === pid);
                if (egg) return Number(egg.unit_price);
                return 0;
            },
            // Returns list of allergen-conflict strings between a customer's
            // allergies and a product's allergens.
            productConflicts(c, pid) {
                const productAllergens = this.allergensFor(pid);
                if (productAllergens.length === 0) return [];
                const have = new Set((c.allergies || []).map(String));
                return productAllergens.filter(a => have.has(String(a)));
            },
            productIsSafe(c, pid) {
                return this.productConflicts(c, pid).length === 0;
            },
            // Recompute live flags so the UI updates as the admin substitutes
            // products (e.g. removing the conflicting item or adding eggs).
            customerLiveFlags(c) {
                const flags = [];
                if (c.note && c.note.length > 0) flags.push('note');

                // Allergen conflict flag: any current line conflicts with the customer's allergies.
                const allLines = [...c.lines, ...c.eggsLines];
                const hasConflict = allLines.some(l => !this.productIsSafe(c, l.product_id));
                if (hasConflict) flags.push('allergy_conflict');

                if (c.eggs && c.eggsLines.length === 0) flags.push('eggs_missing');

                return flags;
            },
            customerFlagged(c) {
                return this.customerLiveFlags(c).length > 0;
            },
            // OK toggle visibility:
            //   - existing boxes don't need OK (already saved)
            //   - new_match boxes don't need OK (composition matches existing
            //     same-size boxes, no flags)
            //   - new_diff boxes need OK whenever they're flagged
            customerNeedsOk(c) {
                if (c.composition_status === 'existing') return false;
                if (c.composition_status === 'new_match' && !this.customerFlagged(c)) return false;
                return this.customerFlagged(c);
            },
            customerNeedsAttention(c) {
                return this.customerNeedsOk(c) && !c.ok;
            },
            anyCustomerBlocking() {
                return this.customers.some(c => this.customerNeedsAttention(c));
            },
            // ---- Sectioning helpers ----
            isExistingCustomer(c) {
                return c.composition_status === 'existing';
            },
            isNewCustomer(c) {
                return c.composition_status === 'new_match' || c.composition_status === 'new_diff';
            },
            newCustomers() {
                return this.customers.filter(c => this.isNewCustomer(c));
            },
            existingCustomers() {
                return this.customers.filter(c => this.isExistingCustomer(c));
            },
            // Sorted: new (review needed first) → new_match → existing.
            // The blade renders the existing-section header right before the
            // first 'existing' card.
            sortedCustomers() {
                const rank = (c) => {
                    if (c.composition_status === 'existing') return 2;
                    if (c.composition_status === 'new_match') return 1;
                    return 0; // new_diff
                };
                return [...this.customers].sort((a, b) => rank(a) - rank(b));
            },
            firstExistingIndex() {
                const sorted = this.sortedCustomers();
                return sorted.findIndex(c => this.isExistingCustomer(c));
            },
            customerSum(c) {
                const sumLines = (rows) => rows.reduce((acc, l) => acc + (this.productPriceAny(l.product_id) * Math.max(0, l.quantity || 0)), 0);
                return sumLines(c.lines) + sumLines(c.eggsLines);
            },
            // Per-customer max = size sales-price TTC + eggs add-on (if the
            // customer pays for eggs), since the customer was charged that
            // total and the box value should be measured against it.
            customerMax(c) {
                const meta = this.sizes.find(s => s.key === c.box_size)?.meta;
                let max = Number(meta?.max ?? 0);
                if (c.eggs) max += Number(this.eggsAddOnEur ?? 0);
                return max;
            },
            // Target shifts proportionally with the planning margin so the
            // green/amber/rose thresholds stay consistent: target = max · (1 − marginRate).
            customerTarget(c) {
                const max = this.customerMax(c);
                const rate = Number(this.marginRate ?? 0);
                return max * (1 - rate);
            },
            // Same green/yellow/red logic as retailClass(): green if at-or-below
            // target (under planning margin), amber if above target but at-or-
            // below max, rose if above sales-price max.
            customerValueClass(c) {
                const sum = this.customerSum(c);
                const max = this.customerMax(c);
                const target = this.customerTarget(c);
                if (max > 0 && sum > max + 0.005) return 'text-rose-700';
                if (target > 0 && sum > target + 0.005) return 'text-amber-700';
                return 'text-emerald-700';
            },
            customerValueDotClass(c) {
                const sum = this.customerSum(c);
                const max = this.customerMax(c);
                const target = this.customerTarget(c);
                if (max > 0 && sum > max + 0.005) return 'bg-rose-500';
                if (target > 0 && sum > target + 0.005) return 'bg-amber-400';
                return 'bg-emerald-500';
            },
            // ---- Aggregate totals across all customers ----
            customersTotal() {
                return this.customers.reduce((acc, c) => acc + this.customerSum(c), 0);
            },
            customersTotalTarget() {
                return this.customers.reduce((acc, c) => acc + this.customerTarget(c), 0);
            },
            customersTotalMax() {
                return this.customers.reduce((acc, c) => acc + this.customerMax(c), 0);
            },
            customersTotalClass() {
                const sum = this.customersTotal();
                const max = this.customersTotalMax();
                const target = this.customersTotalTarget();
                if (max > 0 && sum > max + 0.005) return 'text-rose-700';
                if (target > 0 && sum > target + 0.005) return 'text-amber-700';
                return 'text-emerald-700';
            },
            customersTotalProgressClass() {
                const sum = this.customersTotal();
                const max = this.customersTotalMax();
                const target = this.customersTotalTarget();
                if (max > 0 && sum > max + 0.005) return 'bg-rose-500';
                if (target > 0 && sum > target + 0.005) return 'bg-amber-400';
                return 'bg-emerald-500';
            },
            // ---- Eggs panel ----
            eggsAddableOptions(customer) {
                const used = new Set(customer.eggsLines.map(l => Number(l.product_id)));
                return this.eggCandidates.filter(e => !used.has(Number(e.product_id)));
            },
            customerAddEggs(c) {
                if (!c.addEggsProductId) return;
                const pid = Number(c.addEggsProductId);
                if (!pid) return;
                const idx = c.eggsLines.findIndex(l => Number(l.product_id) === pid);
                if (idx >= 0) {
                    c.eggsLines[idx].quantity = Number(c.eggsLines[idx].quantity || 0) + 1;
                } else {
                    c.eggsLines.push({ product_id: pid, quantity: 1 });
                }
                c.addEggsProductId = '';
            },
            customerRemoveEggsLine(c, idx) {
                c.eggsLines.splice(idx, 1);
            },
            customerIncEggsQty(c, idx) {
                c.eggsLines[idx].quantity = Number(c.eggsLines[idx].quantity || 0) + 1;
            },
            customerDecEggsQty(c, idx) {
                const next = Number(c.eggsLines[idx].quantity || 0) - 1;
                c.eggsLines[idx].quantity = next < 1 ? 1 : next;
            },
            customerCardClass(c) {
                // Existing (already saved) boxes get a quiet gray border —
                // they're informational and don't need attention. The
                // "Bereits eingerichtet" section header already conveys
                // what they are.
                if (c.composition_status === 'existing') return 'border-gray-200 bg-white';

                // Flagged + not yet OK'd → rose, regardless of kind.
                if (this.customerFlagged(c)) {
                    if (c.ok) return 'border-emerald-300 bg-emerald-50/40';
                    return 'border-rose-300 bg-rose-50/40';
                }

                // Trial customers get a violet tint to mirror the "Probebox"
                // badge. Subscription new boxes stay sky.
                if (c.kind === 'trial') return 'border-violet-300 bg-violet-50/40';
                return 'border-sky-300 bg-sky-50/40';
            },
            sizeLabelByKey(key) {
                return this.sizeLabel(key);
            },
            customerAddLine(c) {
                if (!c.addProductId) return;
                const pid = Number(c.addProductId);
                if (!pid) return;
                const existingIdx = c.lines.findIndex(l => Number(l.product_id) === pid);
                if (existingIdx >= 0) {
                    c.lines[existingIdx].quantity = Number(c.lines[existingIdx].quantity || 0) + 1;
                } else {
                    c.lines.push({ product_id: pid, quantity: 1 });
                }
                c.addProductId = '';
            },
            customerRemoveLine(c, idx) {
                c.lines.splice(idx, 1);
            },
            customerIncQty(c, idx) {
                c.lines[idx].quantity = Number(c.lines[idx].quantity || 0) + 1;
            },
            customerDecQty(c, idx) {
                const next = Number(c.lines[idx].quantity || 0) - 1;
                c.lines[idx].quantity = next < 1 ? 1 : next;
            },
            customerAddableOptions(c) {
                const used = new Set(c.lines.map(l => Number(l.product_id)));
                return Object.values(this.stockById)
                    .filter(r => !used.has(Number(r.product_id)))
                    .sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            },
            flagLabel(flag) {
                const labels = @js([
                    'note' => __('admin.box_setup.flag_note'),
                    'allergy_conflict' => __('admin.box_setup.flag_allergy_conflict'),
                    'eggs_missing' => __('admin.box_setup.flag_eggs_missing'),
                ]);
                return labels[flag] ?? flag;
            },
        }));
    });
</script>

