<x-mail.layout :title="'Vos enregistrements expirent dans une semaine'" :action="['url' => $clientUrl, 'label' => 'Tout télécharger']">
    <p style="margin:0 0 14px;">Bonjour,</p>
    <p style="margin:0 0 14px;">
        Les messages de vos invités resteront en ligne jusqu'au {{ $expiresOn }}. Passé cette date, ils seront
        effacés de nos serveurs.
    </p>
    <p style="margin:0;">
        Pensez à les télécharger&nbsp;: le fichier groupé contient un MP3 par message.
    </p>
</x-mail.layout>
