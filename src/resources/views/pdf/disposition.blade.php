@php
\Carbon\Carbon::setLocale('id');
@endphp
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Disposisi {{ $disp->id ?? '' }}</title>
    <style>
        @page {
            margin: 16mm 16mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        .header {
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: middle;
        }

        .logo {
            width: 58px;
        }

        .title {
            text-align: center;
        }

        .title h1 {
            margin: 0;
            font-size: 16px;
        }

        .title p {
            margin: 2px 0 0;
            font-size: 12px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            padding: 4px 0;
            vertical-align: top;
        }

        .label {
            width: 132px;
            font-weight: 700;
        }

        .sep {
            width: 10px;
            text-align: center;
        }

        .qr-box {
            margin-top: 6px;
        }

        .qr-box svg {
            width: 90px;
            height: 90px;
            display: block;
        }

        .qr-link {
            margin-top: 3px;
            font-size: 9px;
            color: #444;
            word-break: break-all;
        }

        .section-title {
            margin: 12px 0 6px;
            font-weight: 700;
        }

        .instruction {
            border: 1px solid #333;
            padding: 8px;
            line-height: 1.35;
            min-height: 74px;
        }

        .note {
            margin-top: 12px;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <table>
            <tr>
                <td class="logo">
                    @if(!empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="Logo" style="width: 56px;">
                    @endif
                </td>
                <td class="title">
                    <H1>RUMAH SAKIT <br> PKU MUHAMMADIYAH KARANGANYAR</H1>
                    <!-- <h1>{{ $appName }}</h1> -->
                    <p>Jl. Papahan, Tasikmadu, Kabupaten Karanganyar 57722 | www.rspkumuhkra.com</p>
                </td>
                <td class="logo"></td>
            </tr>
        </table>
    </div>

    <div>
        <table width="100%">
            <tr>
                <td class="title">
                    <h4>Lembar Disposisi Surat Masuk</h4>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Disposisi ID</td>
            <td class="sep">:</td>
            <td>
                {{ $disp->id ?? '-' }}
                <div class="qr-link">
                    <a href="{{ $downloadUrl }}" target="_blank">
                        {{ $downloadUrl }}
                    </a>
                </div>
            </td>
        </tr>
        <tr>
            <td class="label">No Surat</td>
            <td class="sep">:</td>
            <td>{{ $disp->mail?->mail_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Surat</td>
            <td class="sep">:</td>
            <td>
                {{ $disp->due_date 
            ? \Carbon\Carbon::parse($mailDate)->translatedFormat('d F Y') 
            : '-' }}
            </td>
        </tr>
        <tr>
            <td class="label">Pengirim</td>
            <td class="sep">:</td>
            <td>{{ $disp->mail?->sender ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Perihal</td>
            <td class="sep">:</td>
            <td>{{ $disp->mail?->subject ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tujuan Unit</td>
            <td class="sep">:</td>
            <td>{{ $disp->unit?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Dibuat Oleh</td>
            <td class="sep">:</td>
            <td>
                @php
                $creatorUnit = $disp->fromUser?->unit?->name;
                $creatorName = $disp->fromUser?->name;
                $creatorText = trim(implode(' - ', array_filter([$creatorUnit, $creatorName], fn ($v) => filled($v))));
                @endphp
                {{ $creatorText !== '' ? $creatorText : '-' }}
            </td>
        </tr>
        <tr>
            <td class="label">Tanggal Dibuat</td>
            <td class="sep">:</td>
            <td>
                {{ $disp->created_at 
            ? \Carbon\Carbon::parse($disp->created_at)->translatedFormat('d F Y') 
            : '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Jatuh Tempo</td>
            <td class="sep">:</td>
            <td>
                {{ $disp->due_date 
            ? \Carbon\Carbon::parse($disp->due_date)->translatedFormat('d F Y') 
            : '-' }}
            </td>
        </tr>
    </table>

    <div class="section-title">Instruksi Disposisi</div>
    <div class="instruction">{{ $disp->instruction ?? '-' }}</div>

    <div class="note">
        * Lampiran surat digabung di halaman setelah lembar disposisi ini. <br>
        * Dokumen ini adalah dokumen digital, valid jika bisa diverifikasi pada portal sekre.rspkumuhkra.com .
        Klik pada link di bawah nomer dokumen untuk melakukan verifikasi, pastikan alamat portal adalah sekre.rspkumuhkra.com .
    </div>
</body>

</html>