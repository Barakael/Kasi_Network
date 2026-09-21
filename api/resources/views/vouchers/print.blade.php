<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $batch->reference }} — {{ $batch->plan->name }}</title>

    {{--
        Deliberately a self-contained document with inline CSS and no external
        requests. It is opened in a print dialog, often on a machine at a kiosk
        with an unreliable connection, and a stylesheet that fails to load would
        print a page of unformatted text that still costs paper and toner.

        Sizing is in millimetres throughout, because the output is a physical
        sheet that gets cut with a guillotine. Pixels would render differently
        per printer DPI and the cut lines would stop matching the cards.
    --}}
    <style>
        @page {
            size: A4;
            /* Most inkjets cannot print closer than 5mm to the edge, so the
               margin is set here rather than trusting the printer's default,
               which would otherwise clip the outer column of cards. */
            margin: 8mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #111827;
            background: #fff;
        }

        .toolbar {
            padding: 12px 16px;
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            font-size: 13px;
        }

        .toolbar button {
            padding: 8px 18px;
            font: inherit;
            font-weight: 600;
            color: #fff;
            background: #1d4ed8;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }

        /* The toolbar is for the screen only; it must never reach the paper. */
        @media print {
            .toolbar { display: none; }
        }

        .sheet {
            display: grid;
            grid-template-columns: repeat({{ $columns }}, 1fr);
            /* Guillotine cuts are made along shared edges, so cards sit flush
               against each other and the borders double as cut lines. A gap
               would mean twice as many cuts for the same yield. */
            gap: 0;

            /* Each card draws only its right and bottom edge, so an internal
               line is never drawn twice. The sheet closes the grid on the two
               sides no card covers. Drawing all four edges per card instead
               puts two dashed lines a hair apart everywhere they meet, which
               reads as a fuzzy double line and is harder to cut to. */
            border-top: 0.2mm dashed #9ca3af;
            border-left: 0.2mm dashed #9ca3af;
        }

        /* On paper the sheet is A4 by definition. On screen it would otherwise
           stretch to the window, so the cards preview at proportions no printer
           will produce -- and the preview is what an operator checks before
           committing a sheet. 194mm is A4 less the 8mm margins. */
        @media screen {
            .sheet {
                width: 194mm;
                margin: 8mm auto;
                box-shadow: 0 0 0 1px #e5e7eb;
            }
        }

        .card {
            position: relative;
            /* Sized so exactly {{ $columns }}x{{ $rows }} fill the printable
               area of one A4 sheet. */
            height: {{ $cardHeightMm }}mm;
            padding: 2.5mm;
            border-right: 0.2mm dashed #9ca3af;
            border-bottom: 0.2mm dashed #9ca3af;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            /* Nothing may bleed onto a neighbour: an overflowing plan name would
               print across the card next to it and both would be unreadable. */
            overflow: hidden;
            /* Keeps a card from being split across two pages. */
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 2mm;
        }

        .card__brand {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1d4ed8;
            line-height: 1.2;
            /* Long operator names are truncated rather than allowed to push the
               code off the card. */
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .card__plan {
            font-size: 7pt;
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
            color: #374151;
        }

        .card__body {
            display: flex;
            align-items: center;
            gap: 2.5mm;
        }

        .card__qr {
            flex: 0 0 auto;
            width: 15mm;
            height: 15mm;
        }

        .card__qr svg { width: 100%; height: 100%; display: block; }

        .card__code {
            /* Monospace so a 5 cannot be mistaken for an S at a glance, and
               because equal character widths make a long code easier to read
               back accurately when a customer dictates it over the phone. */
            font-family: "SFMono-Regular", Menlo, Consolas, "Liberation Mono", monospace;
            /* Sized to fit a full code on one line in the width left beside the
               QR code. A code that wraps is legible but gets misread when a
               customer reads it back over the phone, which is the situation the
               grouping and the check character exist to survive. */
            font-size: 10pt;
            font-weight: 700;
            white-space: nowrap;
            line-height: 1.3;
        }

        .card__terms {
            font-size: 7pt;
            color: #374151;
            line-height: 1.35;
        }

        /* Both lines were a point smaller until a print test: at 5.5pt the expiry
           was legible on screen and not on paper, which is the only place it
           matters. Nothing on a card a customer has to read is below 6.5pt. */
        .card__instructions {
            font-size: 6.5pt;
            color: #374151;
            line-height: 1.3;
        }

        .card__footer {
            font-size: 6.5pt;
            font-weight: 600;
            color: #4b5563;
            line-height: 1.3;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ $batch->reference }}</strong> —
            {{ $batch->plan->name }} —
            {{ $cards->count() }} {{ Str::plural('voucher', $cards->count()) }}
            @if ($batch->assignedAgent)
                — for {{ $batch->assignedAgent->name }}
            @endif
        </div>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        @foreach ($cards as $card)
            <div class="card">
                <div class="card__header">
                    <div class="card__brand">{{ $card->ssid }}</div>
                    <div class="card__plan">{{ $card->planName }}</div>
                </div>

                <div class="card__body">
                    <div class="card__qr">{!! $card->qrSvg !!}</div>
                    <div>
                        <div class="card__code">{{ $card->displayCode }}</div>
                        <div class="card__terms">{{ $card->terms }}</div>
                    </div>
                </div>

                <div>
                    <div class="card__instructions">
                        Scan the QR on the hotspot portal to connect. Code is the fallback.
                    </div>
                    @if ($card->expiryNote)
                        <div class="card__footer">{{ $card->expiryNote }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
