<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('boxSetup', (initial) => ({
            marginRate: initial.margin_rate,
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
                user_id: Number(c.user_id),
                subscription_id: Number(c.subscription_id),
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
                eggs_added: !!c.eggs_added,
                eggs_missing: !!c.eggs_missing,
                flags: Array.isArray(c.flags) ? c.flags.map(String) : [],
                ok: !!c.ok, // pre-loaded from server when box previously OK'd
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
            customerNeedsAttention(c) {
                return this.customerFlagged(c) && !c.ok;
            },
            anyCustomerBlocking() {
                return this.customers.some(c => this.customerNeedsAttention(c));
            },
            customerSum(c) {
                const sumLines = (rows) => rows.reduce((acc, l) => acc + (this.productPriceAny(l.product_id) * Math.max(0, l.quantity || 0)), 0);
                return sumLines(c.lines) + sumLines(c.eggsLines);
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
                if (!this.customerFlagged(c)) return 'border-gray-200';
                if (c.ok) return 'border-emerald-300 bg-emerald-50/40';
                return 'border-rose-300 bg-rose-50/40';
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

