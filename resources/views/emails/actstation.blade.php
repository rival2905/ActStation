<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $subject ?? 'Pemberitahuan ActStation' }}</title>
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; min-width: 100%; background-color: #edf2f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .mobile-stack { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #edf2f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #344050;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #edf2f9; table-layout: fixed;">
        <tr>
            <td align="center" style="padding: 30px 15px 40px 15px;">
                <!-- Main Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(11, 23, 39, 0.08); border: 1px solid #d8e2ef;">
                    
                    <!-- Top Accent Bar -->
                    <tr>
                        <td height="4" style="background-color: #2c7be5; font-size: 0; line-height: 0;">&nbsp;</td>
                    </tr>

                    <!-- Small Brand Hero Header -->
                    <tr>
                        <td class="mobile-padding" style="padding: 24px 32px 20px 32px; background-color: #0b1727; border-bottom: 1px solid #121621;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td valign="middle" align="left">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="{{ url('assets/img/logo_fix.png') }}" alt="ActStation" width="36" height="36" style="display: block; border-radius: 6px; border: 0;" />
                                                </td>
                                                <td valign="middle" style="padding-left: 12px;">
                                                    <span style="font-size: 18px; font-weight: 700; color: #ffffff; letter-spacing: 0.3px; display: block; line-height: 1.2;">ActStation</span>
                                                    <span style="font-size: 11px; color: #9da9bb; display: block; line-height: 1.2; margin-top: 2px;">Daily Lifestyle & Open Sports Ecosystem &middot; actstation.net</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td valign="middle" align="right">
                                        @php
                                            $typeValue = strtolower($type ?? 'information');
                                            $chipBg = '#2c7be5';
                                            $chipText = '#ffffff';
                                            $chipLabel = 'Informasi Resmi';

                                            if ($typeValue === 'verification') {
                                                $chipBg = '#00d27a';
                                                $chipText = '#ffffff';
                                                $chipLabel = 'Verifikasi Akun';
                                            } elseif ($typeValue === 'readiness') {
                                                $chipBg = '#f5803e';
                                                $chipText = '#ffffff';
                                                $chipLabel = 'Kesiapan Pertandingan';
                                            } elseif ($typeValue === 'assignment') {
                                                $chipBg = '#27bcfd';
                                                $chipText = '#0b1727';
                                                $chipLabel = 'Penugasan Resmi';
                                            }
                                        @endphp
                                        <span style="display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 4px; background-color: {{ $chipBg }}; color: {{ $chipText }};">
                                            {{ $chipLabel }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Email Content Area -->
                    <tr>
                        <td class="mobile-padding" style="padding: 32px 32px 24px 32px;">
                            <!-- Subject / Heading -->
                            <h1 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 700; color: #0b1727; line-height: 1.35;">
                                {{ $subject ?? 'Pemberitahuan Resmi' }}
                            </h1>

                            <!-- Recipient Greeting -->
                            <p style="margin: 0 0 18px 0; font-size: 14px; line-height: 1.6; color: #5e6e82;">
                                Halo <strong style="color: #0b1727;">{{ $recipient_name ?? 'Pengguna ActStation' }}</strong>,
                            </p>

                            <!-- Escaped Body Text with Safe Linebreaks -->
                            <div style="font-size: 14px; line-height: 1.65; color: #344050; margin: 0 0 24px 0; word-break: break-word;">
                                {!! nl2br(e($body ?? $content ?? '')) !!}
                            </div>

                            <!-- Optional CTA Button (Validated HTTP/HTTPS only) -->
                            @if(!empty($action_url) && preg_match('/^https?:\/\//i', $action_url))
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 26px 0;">
                                <tr>
                                    <td align="center" style="border-radius: 6px; background-color: #2c7be5;">
                                        <a href="{{ $action_url }}" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 12px 26px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #2c7be5; border: 1px solid #2c7be5;">
                                            {{ $action_label ?? 'Buka Tautan' }} &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 20px 0; font-size: 11px; line-height: 1.5; color: #748194; word-break: break-all;">
                                Jika tombol di atas tidak berfungsi, salin dan buka tautan berikut di peramban Anda:<br />
                                <a href="{{ $action_url }}" target="_blank" rel="noopener noreferrer" style="color: #2c7be5; text-decoration: underline;">{{ $action_url }}</a>
                            </p>
                            @endif

                            <!-- Attachment Note (if any) -->
                            @if(!empty($has_attachments) || (!empty($attachments) && is_array($attachments) && count($attachments) > 0))
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0; background-color: #f9fafd; border: 1px solid #d8e2ef; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 14px 18px;">
                                        <span style="display: block; font-size: 13px; font-weight: 600; color: #0b1727; margin-bottom: 4px;">
                                            &#128206; Berkas Lampiran Resmi Terlampir
                                        </span>
                                        <span style="display: block; font-size: 12px; color: #5e6e82; line-height: 1.5;">
                                            Pesan ini memuat dokumen atau berkas resmi yang telah disertakan sebagai lampiran email. Silakan periksa bagian berkas terlampir pada aplikasi email Anda untuk mengunduhnya.
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <!-- Message ID Reference -->
                            @if(!empty($message_id))
                            <p style="margin: 24px 0 0 0; font-size: 11px; color: #9da9bb; border-top: 1px dashed #edf2f9; padding-top: 12px;">
                                ID Pesan: <code>{{ $message_id }}</code>
                            </p>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer Area -->
                    <tr>
                        <td class="mobile-padding" style="padding: 24px 32px 30px 32px; background-color: #f9fafd; border-top: 1px solid #edf2f9;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="font-size: 12px; line-height: 1.55; color: #748194;">
                                        <p style="margin: 0 0 6px 0; font-weight: 600; color: #5e6e82;">
                                            Pemberitahuan Otomatis ActStation (actstation.net)
                                        </p>
                                        <p style="margin: 0 0 8px 0;">
                                            Email ini dikirimkan secara otomatis dari sistem platform ActStation (actstation.net). Mohon untuk tidak membalas langsung ke alamat email ini (no-reply@actstation.net).
                                        </p>
                                        <p style="margin: 0; font-size: 11px; color: #9da9bb;">
                                            &copy; {{ date('Y') }} <a href="https://actstation.net" target="_blank" rel="noopener noreferrer" style="color: #9da9bb; text-decoration: underline;">actstation.net</a>. Hak cipta dilindungi undang-undang.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                <!-- End Main Container -->
            </td>
        </tr>
    </table>
</body>
</html>
