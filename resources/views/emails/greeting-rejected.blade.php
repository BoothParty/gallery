<x-mail.layout :title="'Votre annonce est à refaire'" :action="['url' => $clientUrl, 'label' => 'Réenregistrer mon message']">
    <p style="margin:0 0 14px;">Bonjour,</p>
    <p style="margin:0 0 14px;">
        Nous n'avons pas pu retenir votre enregistrement. Voici ce qui nous a arrêtés&nbsp;:
    </p>
    <p style="margin:0 0 14px;padding:12px 14px;background:#f6f5f2;border-left:2px solid #a8452c;border-radius:3px;color:#171512;">
        {{ $reason }}
    </p>
    <p style="margin:0;">
        Réenregistrez-le quand vous voulez depuis votre espace, nous le réécouterons sous 48&nbsp;h.
    </p>
</x-mail.layout>
