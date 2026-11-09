## Token Bearer

Ogni collegamento usa un token personale di integrazione, separato dall'accesso degli utenti. Invialo nell'header `Authorization: Bearer TOKEN`. Non inserirlo negli URL, nel codice pubblico, nel browser o nei log.

## Creazione e scadenza

Solo il superadmin può creare collegamenti. Il token viene mostrato una sola volta; il Centro conserva il suo hash. La scadenza è obbligatoria e non può superare un anno.

## Verifica

`GET /api/v1/me` restituisce l'identificativo del collegamento, il nome, gli scope e la scadenza. Non restituisce il token.

## Revoca

Il superadmin può revocare un collegamento dalle impostazioni. Un'integrazione può revocare se stessa con `POST /api/v1/connect/revoke`. La revoca elimina i token del collegamento e non cancella i dati del Centro.

Se il proprietario viene disabilitato o perde il ruolo superadmin, l'accesso viene negato. Per sostituire una credenziale crea un nuovo collegamento, configura il software e revoca il precedente.
