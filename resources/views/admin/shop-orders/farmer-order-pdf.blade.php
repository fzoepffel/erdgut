<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ __('shop.admin.farmer_export_pdf_title', ['producer' => $producer->name]) }}</title>
    <style>
        @page { margin: 22mm 18mm 22mm 18mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #1f1f1d;
            font-size: 10pt;
            line-height: 1.45;
        }
        .header { border-bottom: 1pt solid #1f1f1d; padding-bottom: 8pt; margin-bottom: 18pt; }
        .header .brand { font-size: 14pt; font-weight: bold; letter-spacing: 0.06em; }
        .header .meta { color: #555; font-size: 8.5pt; margin-top: 2pt; }
        .sender {
            float: left;
            width: 48%;
            font-size: 9pt;
            color: #1f1f1d;
        }
        .recipient {
            float: right;
            width: 48%;
            text-align: right;
            font-size: 9pt;
        }
        .recipient strong { font-size: 11pt; }
        .clearer { clear: both; }

        .order-meta {
            margin-top: 16pt;
            font-size: 9pt;
            color: #1f1f1d;
            border: 0.5pt solid #b9b9b0;
            padding: 8pt 10pt;
            background: #faf9f3;
        }
        .order-meta dt { color: #6b6b65; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.06em; }
        .order-meta dd { margin: 0 0 4pt 0; font-size: 10pt; font-weight: bold; }

        h2 {
            font-size: 11pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #2c5b2c;
            margin: 22pt 0 6pt 0;
            border-bottom: 0.5pt solid #b9b9b0;
            padding-bottom: 3pt;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 5pt 6pt;
            font-size: 9.5pt;
            border-bottom: 0.5pt solid #d8d6cf;
            vertical-align: top;
        }
        th {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b6b65;
            text-align: left;
            border-bottom: 0.75pt solid #6b6b65;
        }
        th.right, td.right { text-align: right; }
        td.unit { color: #6b6b65; font-size: 9pt; }

        .day-header td {
            background: #f0eee5;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 9pt;
            color: #2c5b2c;
            border-top: 0.5pt solid #b9b9b0;
        }

        .empty {
            font-style: italic;
            color: #6b6b65;
            padding: 14pt 0;
        }
        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #6b6b65;
        }
    </style>
</head>
<body>
    @php
        use Illuminate\Support\Carbon;
        $fromLabel = $filter['delivery_from'] !== ''
            ? Carbon::parse($filter['delivery_from'])->locale('de')->isoFormat('D. MMMM YYYY')
            : '—';
        $toLabel = $filter['delivery_to'] !== ''
            ? Carbon::parse($filter['delivery_to'])->locale('de')->isoFormat('D. MMMM YYYY')
            : '—';
    @endphp

    <div class="header">
        <div class="brand">ERDGUT</div>
        <div class="meta">{{ __('shop.admin.farmer_export_pdf_subtitle') }} — {{ $generatedAt->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }}</div>
        <div class="meta">{{ __('shop.admin.farmer_export_snapshot_hint') }}</div>
    </div>

    <div class="sender">
        <strong>{{ __('shop.admin.farmer_export_sender_label') }}</strong><br>
        Erdgut UG (haftungsbeschränkt)<br>
        Gotenstr. 19<br>
        10829 Berlin<br>
        Deutschland<br>
        kontakt@erd-gut.de
    </div>

    <div class="recipient">
        <strong>{{ __('shop.admin.farmer_export_recipient_label') }}</strong><br>
        <strong>{{ $producer->name }}</strong>
    </div>

    <div class="clearer"></div>

    <table class="order-meta">
        <tr>
            <td style="width: 50%;">
                <dt>{{ __('shop.admin.farmer_export_window_label') }}</dt>
                <dd>{{ $fromLabel }} – {{ $toLabel }}</dd>
            </td>
            <td style="width: 50%;">
                <dt>{{ __('shop.admin.farmer_export_position_count_label') }}</dt>
                <dd>{{ count($rows) }}</dd>
            </td>
        </tr>
    </table>

    <h2>{{ __('shop.admin.farmer_export_section_positions') }}</h2>

    @if (count($rows) === 0)
        <p class="empty">{{ __('shop.admin.farmer_export_no_rows') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('shop.admin.farmer_export_col_product') }}</th>
                    <th class="right">{{ __('shop.admin.farmer_export_col_price') }}</th>
                    <th>{{ __('shop.admin.farmer_export_col_unit') }}</th>
                    <th class="right">{{ __('shop.admin.farmer_export_col_quantity') }}</th>
                </tr>
            </thead>
            <tbody>
                @php($lastDate = null)
                @foreach ($rows as $row)
                    @php($currentDate = $row['date']->toDateString())
                    @if ($currentDate !== $lastDate)
                        <tr class="day-header">
                            <td colspan="4">{{ $row['date']->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</td>
                        </tr>
                        @php($lastDate = $currentDate)
                    @endif
                    <tr>
                        <td>{{ $row['product_name'] }}</td>
                        <td class="right">{{ $row['price'] }}</td>
                        <td class="unit">{{ $row['unit'] }}</td>
                        <td class="right">{{ $row['amount'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Erdgut UG · Gotenstr. 19 · 10829 Berlin · kontakt@erd-gut.de
    </div>
</body>
</html>
