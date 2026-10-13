<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:24px 12px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb">
        <tr>
            <td style="padding:24px 28px 8px;text-align:center">
                @if ($logoPng = \App\Support\Branding::logoPng())
                    <img src="{{ $message->embedData($logoPng, 'logo.png', 'image/png') }}" alt="{{ $company['name'] }}" style="max-height:56px;max-width:200px">
                @else
                    <strong style="font-size:18px">{{ $company['name'] }}</strong>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 8px;font-size:15px;line-height:1.55">
                @foreach ($lines as $line)
                    @if (trim($line) === '')
                        <div style="height:10px"></div>
                    @else
                        <div>{{ $line }}</div>
                    @endif
                @endforeach
            </td>
        </tr>
        @if ($url)
            <tr>
                <td style="padding:16px 28px 8px;text-align:center">
                    <a href="{{ $url }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:10px">{{ $action }}</a>
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:18px 28px 24px;font-size:12px;color:#6b7280;text-align:center;border-top:1px solid #f3f4f6">
                {{ $company['name'] }}{{ $company['address'] ? ' · ' . $company['address'] : '' }}{{ $company['phone'] ? ' · Tel. ' . $company['phone'] : '' }}
            </td>
        </tr>
    </table>
</body>
</html>
