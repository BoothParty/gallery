<x-mail.layout :title="$partyTitle . ' : annonce à valider'" :action="['url' => $studioUrl, 'label' => 'Écouter l\'annonce']">
    <p style="margin:0 0 14px;">
        {{ $partyTitle }} vient d'envoyer son annonce{{ $duration ? ' de '.$duration : '' }} pour validation.
    </p>
    @if ($clientNote)
        <p style="margin:0 0 14px;padding:12px 14px;background:#f6f5f2;border-radius:3px;">
            <strong style="color:#171512;">Note du client&nbsp;:</strong><br>{{ $clientNote }}
        </p>
    @endif
    <p style="margin:0;">Le client attend une réponse sous 48&nbsp;h.</p>
</x-mail.layout>
