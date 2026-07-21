<!DOCTYPE html>
<html lang="en-NG">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 13px; margin: 32px; }
        .header { border-bottom: 3px solid #059669; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { font-size: 20px; font-weight: bold; color: #059669; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th { text-align: left; background: #f1f5f9; padding: 8px; font-size: 11px; text-transform: uppercase; color: #475569; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .code-box { background: #059669; color: #fff; padding: 14px; text-align: center; border-radius: 6px; margin-top: 20px; }
        .code { font-size: 22px; font-weight: bold; letter-spacing: 4px; }
        .footer { margin-top: 28px; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">NewCo Health</div>
        <div class="muted">Electronic prescription — telemedicine consult</div>
    </div>

    <table>
        <tr>
            <td style="border: none; padding-left: 0;">
                <strong>Prescription</strong><br>
                <span class="muted">Ref: {{ $prescription->id }}</span><br>
                <span class="muted">Issued: {{ $prescription->created_at->timezone('Africa/Lagos')->format('j M Y, H:i') }} (Lagos)</span>
            </td>
            <td style="border: none; text-align: right;">
                <strong>Dr {{ $prescription->doctor->user->name }}</strong><br>
                <span class="muted">MDCN: {{ $prescription->doctor->mdcn_licence_no }}</span><br>
                <span class="muted">Status: {{ ucfirst($prescription->status) }}</span>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Medicine</th>
                <th>Dosage</th>
                <th>Duration</th>
                <th>Instructions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prescription->items as $item)
                <tr>
                    <td><strong>{{ $item->formularyItem->label() }}</strong></td>
                    <td>{{ $item->dosage }}</td>
                    <td>{{ $item->duration_days }} days</td>
                    <td>{{ $item->instructions ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="code-box">
        <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Pharmacy pickup code</div>
        <div class="code">{{ $prescription->pickup_code }}</div>
    </div>

    <div class="footer">
        Valid at any NewCo Health partner pharmacy. The pharmacist verifies this code in their portal before dispensing.
        This prescription was issued via telemedicine in accordance with MDCN guidelines.
    </div>
</body>
</html>
