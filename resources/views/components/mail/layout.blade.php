<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
{{--
    Styles en ligne et tableaux : les clients mail ignorent les feuilles
    externes et la moitié d'entre eux ne connaissent pas flexbox. Les couleurs
    reprennent celles du livre d'or (surface, encre, accent).
--}}
<body style="margin:0;padding:0;background:#f6f5f2;color:#171512;font-family:Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f5f2;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fdfcfa;border:1px solid rgba(23,21,18,.1);border-radius:4px;">
                <tr>
                    <td style="padding:32px 32px 0;">
                        <p style="margin:0;font-size:10px;letter-spacing:.22em;text-transform:uppercase;color:#a8a29a;">
                            Livre d'or audio
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px 0;">
                        <h1 style="margin:0;font-family:Georgia,serif;font-weight:400;font-size:28px;line-height:1.15;color:#171512;">
                            {{ $title }}
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px 0;font-size:15px;line-height:1.65;color:#4a463f;">
                        {{ $slot }}
                    </td>
                </tr>
                @isset($action)
                    <tr>
                        <td style="padding:28px 32px 0;">
                            <a href="{{ $action['url'] }}" style="display:inline-block;padding:14px 26px;border-radius:999px;background:#171512;color:#fdfcfa;font-size:15px;text-decoration:none;">
                                {{ $action['label'] }}
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 32px 0;font-size:12px;line-height:1.6;color:#a09a91;word-break:break-all;">
                            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>{{ $action['url'] }}
                        </td>
                    </tr>
                @endisset
                <tr>
                    <td style="padding:32px;">
                        <hr style="border:none;border-top:1px solid rgba(23,21,18,.1);margin:0 0 16px;">
                        <p style="margin:0;font-size:12px;line-height:1.6;color:#8d877e;">
                            {{ config('app.name') }} — une question&nbsp;? Répondez simplement à ce message.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
