<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm 16mm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #111; line-height: 1.45; }
        .head { border-bottom: 1pt solid #111; padding-bottom: 6pt; margin-bottom: 10pt; }
        .head td { vertical-align: middle; }
        h1 { font-size: 17pt; margin: 4pt 0 8pt; }
        h2 { font-size: 12.5pt; margin: 14pt 0 5pt; padding-bottom: 2pt; border-bottom: 0.5pt solid #bbb; }
        h3 { font-size: 11pt; margin: 10pt 0 4pt; }
        p { margin: 0 0 6pt; }
        ul, ol { margin: 0 0 6pt 14pt; padding: 0; }
        li { margin-bottom: 2pt; }
        blockquote { margin: 6pt 0; padding: 5pt 8pt; background: #f1f5f9; border-left: 2pt solid #64748b; }
        code { font-family: 'DejaVu Sans Mono', monospace; font-size: 9pt; background: #f1f5f9; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                @if ($company['logo']) <img src="{{ $company['logo'] }}" alt="" style="max-height:34pt;max-width:130pt"> @endif
            </td>
            <td style="text-align:right;font-size:9pt;color:#555">{{ $company['name'] }}<br>Manual de uso · {{ now()->format('d/m/Y') }}</td>
        </tr>
    </table>
    {!! $html !!}
</body>
</html>
