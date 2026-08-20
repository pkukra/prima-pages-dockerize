<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>{{ $subjectText }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  </head>
  <body style="margin:0;padding:0;background-color:#f5f6f8;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f6f8;padding:24px 12px;">
      <tr>
        <td align="center">
          <table role="presentation" cellpadding="0" cellspacing="0" width="560" style="max-width:560px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e6e8ec;">
            <tr>
              <td style="padding:18px 20px;background:#0f2a43;color:#ffffff;font-family:Arial, Helvetica, sans-serif;font-size:16px;font-weight:bold;">
                {{ $subjectText }}
              </td>
            </tr>
            <tr>
              <td style="padding:20px 20px 8px 20px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#2c2f36;">
                <br>
                {!! nl2br(e($messageText)) !!}
              </td>
            </tr>
            <tr>
              <td style="padding:4px 20px 16px 20px;">
                <p>Silahkan membuka aplikasi {{ config('app.name') }} untuk melihat surat.</p>
                <a href="{{ $actionUrl ?? '#' }}" style="display:inline-block;padding:10px 16px;background:#1b5eaa;color:#ffffff;text-decoration:none;border-radius:6px;font-family:Arial, Helvetica, sans-serif;font-size:14px;">
                  Lihat Surat
                </a>
              </td>
            </tr>
            <tr>
              <td style="padding:0 20px 18px 20px;font-family:Arial, Helvetica, sans-serif;font-size:12px;line-height:1.6;color:#7a7f87;">
              </td>
            </tr>
            <tr>
              <td style="padding:12px 20px;background:#fafbfc;border-top:1px solid #eef1f4;font-family:Arial, Helvetica, sans-serif;font-size:12px;line-height:1.6;color:#8a9099;text-align:center;">
                {{ config('app.name') }} • RS PKU Muhammadiyah Karanganyar
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
