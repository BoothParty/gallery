<x-mail.layout :title="$count > 1 ? 'Vos '.$count.' messages sont en ligne' : 'Votre message est en ligne'" :action="['url' => $clientUrl, 'label' => 'Écouter les messages']">
    <p style="margin:0 0 14px;">Bonjour,</p>
    <p style="margin:0 0 14px;">
        Vos invités ont laissé {{ $count > 1 ? $count.' messages' : 'un message' }} sur le combiné,
        {{ $total }} d'enregistrements au total. Ils vous attendent dans votre espace.
    </p>
    <p style="margin:0;">
        Vous pouvez les écouter, nommer ceux dont vous reconnaissez la voix, et tout télécharger d'un coup.
    </p>
</x-mail.layout>
