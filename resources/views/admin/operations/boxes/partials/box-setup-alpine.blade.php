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
        }));
    });
</script>

