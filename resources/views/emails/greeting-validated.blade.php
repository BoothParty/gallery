<x-mail.layout :title="'Votre annonce est validée'" :action="['url' => $clientUrl, 'label' => 'Réécouter mon message']">
    <p style="margin:0 0 14px;">Bonjour,</p>
    <p style="margin:0 0 14px;">
        Nous avons écouté votre message&nbsp;: il est parfait. Il sera en messagerie sur le combiné le jour
        de votre événement.
    </p>
    <p style="margin:0;">
        Vous pouvez le réécouter à tout moment depuis votre espace. Toute modification demandera une nouvelle
        validation.
    </p>
</x-mail.layout>
