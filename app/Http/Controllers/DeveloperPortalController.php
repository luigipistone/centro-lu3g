<?php

namespace App\Http\Controllers;

use App\Services\CommonApiWebhooks;
use App\Services\CommonApiWrites;
use Illuminate\Support\Str;

class DeveloperPortalController extends Controller
{
    public const RESOURCES = ['clients' => 'Clienti', 'projects' => 'Progetti', 'tasks' => 'Task', 'comments' => 'Commenti', 'users' => 'Utenti', 'absences' => 'Assenze', 'services' => 'Servizi'];

    public const GUIDES = ['introduction' => 'Introduzione', 'authentication' => 'Autenticazione', 'permissions' => 'Permessi e ambiti', 'writes' => 'Creare e modificare', 'pagination' => 'Paginazione e filtri', 'errors' => 'Errori e limiti', 'availability' => 'Disponibilità e smart working', 'webhooks' => 'Webhook'];

    public function show(string $page = 'introduction')
    {
        abort_unless(isset(self::GUIDES[$page]) || isset(self::RESOURCES[$page]), 404);
        $resource = isset(self::RESOURCES[$page]);
        $content = $resource ? $this->resourceGuide($page) : file_get_contents(base_path('docs/developers/'.$page.'.md'));
        $example = $page === 'writes'
            ? ['curl --fail-with-body -X POST', '--header "Authorization: Bearer $CENTRO_TOKEN"', '--header "Content-Type: application/json"', '--header "Idempotency-Key: OPERAZIONE-UNIVOCA"', "--data '{\"name\":\"CLIENTE ESEMPIO\"}'", '"https://centro.lu3g.com/api/v1/clients"']
            : ['curl --fail-with-body', '--header "Authorization: Bearer $CENTRO_TOKEN"', '"https://centro.lu3g.com/api/v1/'.($resource ? $page.'?per_page=50&page=1' : 'me').'"'];

        return view('developers', ['page' => $page, 'title' => self::GUIDES[$page] ?? self::RESOURCES[$page], 'guides' => self::GUIDES,
            'resources' => self::RESOURCES, 'resource' => $resource,
            'html' => Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'example' => implode(' '.chr(92).PHP_EOL.'  ', $example)]);
    }

    private function resourceGuide(string $resource): string
    {
        $notes = [
            'comments' => 'Commenti delle task nei progetti autorizzati. Filtra con `task_id`. Il campo `content` contiene HTML: sanificalo prima di renderizzarlo. I commenti possono essere aggiunti, non modificati o eliminati via API.',
            'clients' => 'Include clienti attivi e archiviati. Usa `active=1` oppure `active=0` per separarli. Note interne e dati bancari non vengono esposti.',
            'projects' => 'Include i progetti autorizzati in tutti gli stati. Filtra con `client_id` o `status` (`active`, `completed`, `on_hold`, `archived`). Gli ID collegati non autorizzano a leggere automaticamente le relative schede.',
            'tasks' => 'Include task, continuative, meeting e sottoattività dei progetti autorizzati, non solo quelli creati tramite API. `parent_task_id` distingue le sottoattività. Stati: `todo`, `in_progress`, `in_review`, `done`. Filtri: `project_id`, `client_id`, `status`. Per le attività prive di progetto serve l’ambito tutti i progetti.',
            'users' => 'Solo account attivi autorizzati. Email di lavoro e nome sono inclusi; password, profilo privato, documenti e dati anagrafici personali sono esclusi.',
            'absences' => 'Solo richieste approvate delle persone autorizzate. `date=2026-11-09` restituisce quelle che comprendono il giorno. Non include smart working o trasferte. Motivazioni, tipologia medica, note e allegati non vengono esposti. Gli orari null indicano una richiesta senza intervallo orario specificato, non una certificazione di presenza.',
            'services' => 'Catalogo dei servizi del Centro. Lo scope autorizza il catalogo completo, senza esporre le associazioni private dei clienti. Filtra con `active=1` o `active=0`.',
        ];
        $text = "## Elenco\n\n`GET /api/v1/$resource`\n\n".$notes[$resource]."\n\nScope richiesto: `$resource:read`.\n\n## Parametri\n\n| Parametro | Descrizione |\n| --- | --- |\n| page | Pagina da 1; predefinita 1 |\n| per_page | Da 1 a 100; predefinito 50 |\n| updated_since | Data e ora ISO 8601 incluse nel confronto |\n";
        if (! in_array($resource, ['absences', 'comments'])) {
            $text .= "| search | Ricerca per nome o titolo su tutto l’elenco autorizzato |\n";
        }
        $text .= "\n## Risposta\n\nGli elenchi restituiscono `data` e `meta` con `page`, `per_page`, `total`, `last_page`, `has_more`. L’ordine è stabile per ID.\n\n## Dettaglio\n\n`GET /api/v1/$resource/{id}`\n\nRestituisce `{\"data\": {...}}`. Un ID inesistente o fuori ambito restituisce 404.\n\n## Campi restituiti\n\n";
        $text .= "| Campo | Tipo |\n| --- | --- |\n";
        foreach (CommonApiController::FIELDS[$resource] as $field) {
            $text .= '| `'.$field.'` | '.$this->fieldType($field)." |\n";
        }

        $text .= "\nI campi non valorizzati possono essere null. Le date e ore archiviate sono nel fuso configurato del Centro (Europe/Rome); i campi data non rappresentano istanti UTC. Il dettaglio restituisce l’header `ETag`.\n";
        if (isset(CommonApiWrites::FIELDS[$resource])) {
            $text .= "\n## Creazione e modifica\n\n`POST /api/v1/$resource` crea; `PATCH /api/v1/$resource/{id}` modifica soltanto i campi inviati. Richiedono entrambi gli scope `$resource:read` e `$resource:write`. Vedi [Creare e modificare](/developers/writes) per idempotenza, versioni e controlli.\n\nCampi accettati: `".implode('`, `', CommonApiWrites::FIELDS[$resource])."`. Altri campi vengono rifiutati.\n";
        } elseif ($resource === 'comments') {
            $text .= "\n## Aggiungere un commento\n\n`POST /api/v1/tasks/{id}/comments` con JSON `{\"body\":\"Testo del commento\"}`. Massimo 20.000 caratteri, testo semplice. Richiede `comments:write`, `comments:read`, `tasks:read` e `Idempotency-Key`. Restituisce il commento in `data`, stato 201; un ritentativo identico restituisce 200. L’autore è il proprietario del collegamento.\n";
        } else {
            $text .= "\n## Disponibilità\n\nRisorsa in sola lettura. Non usare le operazioni Page Notes come alternativa.\n";
        }
        if ($resource === 'tasks') {
            $text .= "\nIl dettaglio include anche `assignee_ids` e `follower_ids`. Per filtrare le sottoattività usa `parent_task_id`. `description` è HTML in lettura e testo semplice in scrittura.\n";
        }
        if ($resource === 'projects') {
            $text .= "\nIl dettaglio include anche `user_ids` (partecipanti). `description` è HTML in lettura e testo semplice in scrittura.\n";
        }

        if ($resource === 'users') {
            $text .= "\n## Avatar\n\n`avatar_url` contiene l’URL assoluto di `GET /api/v1/users/{id}/avatar`, oppure null se non è impostata una foto. Scarica l’immagine con `Authorization: Bearer TOKEN`: valgono lo scope `users:read` e gli stessi ambiti utente. Restituisce il file immagine, non JSON; 404 se la foto manca, l’utente non è attivo o è fuori ambito. Non inserire il token nell’URL. Per una web app usa il backend come proxy; un semplice tag img non invia l’header Bearer.\n";
        }

        return $text;
    }

    public function openapi()
    {
        $paths = [];
        $schemas = [];
        foreach (self::RESOURCES as $resource => $label) {
            $properties = [];
            foreach (CommonApiController::FIELDS[$resource] as $field) {
                $properties[$field] = ['type' => $this->fieldType($field), 'nullable' => $field !== 'id'];
            }
            $schemas[$resource] = ['type' => 'object', 'properties' => $properties, 'required' => ['id']];
            $responses = ['401' => ['description' => 'Token assente, scaduto o revocato'], '403' => ['description' => 'Scope mancante o account proprietario disabilitato'], '404' => ['description' => 'Risorsa inesistente o fuori ambito'], '422' => ['description' => 'Parametri non validi'], '429' => ['description' => 'Limite richieste superato']];
            $params = [
                ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50]],
                ['name' => 'updated_since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
            ];
            if (! in_array($resource, ['absences', 'comments'])) {
                $params[] = ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 200]];
            }
            foreach (array_intersect(CommonApiController::FIELDS[$resource], ['active', 'client_id', 'project_id', 'user_id', 'task_id', 'parent_task_id', 'status']) as $field) {
                $params[] = ['name' => $field, 'in' => 'query', 'schema' => $field === 'active' ? ['type' => 'integer', 'enum' => [0, 1]] : ['type' => 'string']];
            }
            if ($resource === 'absences') {
                $params[] = ['name' => 'date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']];
            }
            $ref = ['$ref' => '#/components/schemas/'.$resource];
            $paths['/'.$resource]['get'] = ['operationId' => 'list'.ucfirst($resource), 'tags' => [$label], 'summary' => 'Elenca '.$label, 'description' => 'Scope: '.$resource.':read. Solo connessioni general.', 'parameters' => $params,
                'responses' => ['200' => ['description' => 'Elenco paginato', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $ref], 'meta' => ['$ref' => '#/components/schemas/Pagination']]]]]]] + $responses];
            $paths['/'.$resource.'/{id}']['get'] = ['operationId' => 'get'.ucfirst($resource), 'tags' => [$label], 'summary' => 'Dettaglio '.$label, 'description' => 'Scope: '.$resource.':read.', 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'responses' => ['200' => ['description' => 'Risorsa', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $ref]]]]]] + $responses];
        }
        $schemas['Pagination'] = ['type' => 'object', 'properties' => array_fill_keys(['page', 'per_page', 'total', 'last_page'], ['type' => 'integer']) + ['has_more' => ['type' => 'boolean']]];
        $schemas['Availability'] = ['type' => 'object', 'properties' => [
            'user_id' => ['type' => 'string', 'format' => 'uuid'], 'date' => ['type' => 'string', 'format' => 'date'],
            'planned_minutes' => ['type' => 'integer'], 'status' => ['type' => 'string', 'enum' => ['not_scheduled', 'absent', 'partially_absent', 'scheduled']],
            'work_mode' => ['type' => 'string', 'nullable' => true, 'enum' => ['remote', 'office', 'travel', null]],
            'absence_intervals' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['start' => ['type' => 'string'], 'end' => ['type' => 'string']]]],
        ]];
        $paths['/availability']['get'] = ['operationId' => 'getAvailability', 'summary' => 'Pianificazione giornaliera', 'description' => 'Scope availability:read; ambito persone. Non certifica presenza fisica.',
            'parameters' => [['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
                ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]], ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]]],
            'responses' => ['200' => ['description' => 'Disponibilità paginata', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Availability']], 'meta' => ['$ref' => '#/components/schemas/Pagination']]]]]]] + $responses];
        $schemas['Webhook'] = ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'url' => ['type' => 'string', 'format' => 'uri'], 'events' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => CommonApiWebhooks::events()]], 'active' => ['type' => 'boolean'], 'created_at' => ['type' => 'string']]];
        $paths['/webhooks']['get'] = ['operationId' => 'listWebhooks', 'summary' => 'Elenca sottoscrizioni', 'description' => 'Scope webhooks:write. Non restituisce segreti.', 'responses' => ['200' => ['description' => 'Sottoscrizioni', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Webhook']]]]]]]] + $responses];
        $paths['/webhooks']['post'] = ['operationId' => 'createWebhook', 'summary' => 'Registra callback', 'description' => 'Scope webhooks:write e lettura delle risorse richieste. Segreto visibile solo alla creazione.',
            'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['url', 'events'], 'properties' => ['url' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 1000], 'events' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'enum' => CommonApiWebhooks::events()]]]]]]],
            'responses' => ['201' => ['description' => 'Creato', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'secret' => ['type' => 'string']]]]]]]], '409' => ['description' => 'Callback già registrato']] + $responses];
        $paths['/webhooks/{id}']['delete'] = ['operationId' => 'deleteWebhook', 'summary' => 'Elimina sottoscrizione', 'description' => 'Scope webhooks:write.', 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]], 'responses' => ['204' => ['description' => 'Eliminato']] + $responses];
        $paths['/me']['get'] = ['operationId' => 'getConnection', 'summary' => 'Verifica collegamento', 'responses' => ['200' => ['description' => 'Identità, scope e scadenza', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['connection_id' => ['type' => 'string'], 'token_name' => ['type' => 'string'], 'scopes' => ['type' => 'array', 'items' => ['type' => 'string']], 'client_id' => ['type' => 'string', 'nullable' => true], 'project_id' => ['type' => 'string', 'nullable' => true], 'expires_at' => ['type' => 'string', 'format' => 'date-time']]]]]]] + $responses];
        $paths['/connect/revoke']['post'] = ['operationId' => 'revokeConnection', 'summary' => 'Revoca il proprio collegamento', 'responses' => ['200' => ['description' => 'Revocato', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['revoked' => ['type' => 'boolean']]]]]]] + $responses];

        $paths['/me']['get']['responses']['200']['content']['application/json']['schema']['properties'] += ['kind' => ['type' => 'string', 'enum' => ['general', 'page_notes']], 'access_policy' => ['type' => 'object', 'nullable' => true]];

        $schemas['users']['properties']['avatar_url'] += ['format' => 'uri', 'description' => 'URL assoluto protetto da Bearer users:read; null senza avatar.'];
        $paths['/users/{id}/avatar']['get'] = ['operationId' => 'getUserAvatar', 'tags' => ['Utenti'], 'summary' => 'Scarica avatar utente', 'description' => 'Richiede users:read e utente attivo nell’ambito del collegamento. Token nell’header, mai nell’URL. 404 anche se la foto non esiste.', 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]], 'responses' => ['200' => ['description' => 'Immagine avatar', 'content' => ['image/*' => ['schema' => ['type' => 'string', 'format' => 'binary']]]]] + $responses];
        $this->writeSpecification($paths, $schemas, $responses);
        $schemas['Error'] = ['type' => 'object', 'required' => ['error'], 'properties' => ['error' => ['type' => 'object', 'required' => ['code', 'message', 'fields'], 'properties' => ['code' => ['type' => 'string'], 'message' => ['type' => 'string'], 'fields' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]]]]]];
        foreach ($paths as &$operations) {
            foreach ($operations as &$operation) {
                foreach ($operation['responses'] as $status => &$response) {
                    if ((int) $status >= 400) {
                        $response['content'] = ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]];
                    }
                }
                unset($response);
            }
            unset($operation);
        }
        unset($operations);

        return response()->json(['openapi' => '3.0.3', 'info' => ['title' => 'Il Centro API comuni', 'version' => '1.1.0', 'description' => 'Letture, scritture per clienti/progetti/task/commenti e webhook. Non include le operazioni specifiche Page Notes.'],
            'servers' => [['url' => 'https://centro.lu3g.com/api/v1']], 'security' => [['bearerAuth' => []]], 'paths' => $paths,
            'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']], 'schemas' => $schemas]])->header('Content-Disposition', 'attachment; filename="centro-common-api-v1.json"');
    }

    private function fieldType(string $field): string
    {
        return match ($field) {
            'active', 'recurring_enabled' => 'boolean',
            'recurring_interval_value', 'recurring_weekday', 'recurring_month_day' => 'integer',
            default => 'string',
        };
    }

    private function writeSpecification(array &$paths, array &$schemas, array $errors): void
    {
        $errors += ['409' => ['description' => 'Chiave riutilizzata o relazione incompatibile'], '412' => ['description' => 'ETag non più valido'], '428' => ['description' => 'If-Match obbligatorio']];
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']];
        $key = ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128]];
        foreach (self::RESOURCES as $resource => $label) {
            $detail = ['$ref' => '#/components/schemas/'.$resource];
            $extra = match ($resource) {
                'tasks' => ['assignee_ids', 'follower_ids'], 'projects' => ['user_ids'], default => []
            };
            if ($extra) {
                $schemas[$resource.'Detail'] = ['allOf' => [$detail, ['type' => 'object', 'properties' => array_fill_keys($extra, ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']])]]];
                $detail = ['$ref' => '#/components/schemas/'.$resource.'Detail'];
            }
            $response = ['description' => 'Risorsa aggiornata; data contiene il dettaglio corrente.', 'headers' => ['ETag' => ['schema' => ['type' => 'string']], 'Idempotency-Replayed' => ['description' => 'true solo per una creazione già eseguita', 'schema' => ['type' => 'string']]], 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $detail]]]]];
            $paths['/'.$resource.'/{id}']['get']['responses']['200'] = $response;
            if (! isset(CommonApiWrites::FIELDS[$resource])) {
                continue;
            }
            $properties = [];
            foreach (CommonApiWrites::FIELDS[$resource] as $field) {
                $type = ['type' => 'string', 'nullable' => true];
                if (in_array($field, ['name', 'title'])) {
                    $type = ['type' => 'string', 'minLength' => 1, 'maxLength' => 255];
                }
                if (in_array($field, ['legal_name', 'email', 'phone', 'website', 'vat_number', 'tax_code', 'country', 'street', 'street_number', 'postal_code', 'city', 'province', 'location'])) {
                    $type['maxLength'] = 255;
                }
                if ($field === 'email') {
                    $type['format'] = 'email';
                }
                if ($field === 'color') {
                    $type = ['type' => 'string', 'maxLength' => 20];
                }
                if (str_ends_with($field, '_id')) {
                    $type['format'] = 'uuid';
                }
                if (str_ends_with($field, '_ids')) {
                    $type = ['type' => 'array', 'uniqueItems' => true, 'items' => ['type' => 'string', 'format' => 'uuid']];
                }
                if (in_array($field, ['active', 'recurring_enabled'])) {
                    $type = ['type' => 'boolean'];
                }
                if (in_array($field, ['recurring_interval_value', 'recurring_weekday', 'recurring_month_day'])) {
                    $type = ['type' => 'integer', 'nullable' => true, 'minimum' => 1, 'maximum' => match ($field) {
                        'recurring_interval_value' => 365, 'recurring_weekday' => 7, default => 31
                    }];
                }
                if (in_array($field, ['start_date', 'due_date'])) {
                    $type['format'] = 'date';
                }
                if ($field === 'due_time') {
                    $type['pattern'] = '^([01][0-9]|2[0-3]):[0-5][0-9]$';
                }
                if ($field === 'description') {
                    $type += ['maxLength' => 50000, 'description' => 'Testo semplice; convertito in HTML sicuro dal Centro.'];
                }
                $enum = match ($field) {
                    'status' => $resource === 'projects' ? ['active', 'completed', 'on_hold', 'archived'] : ['todo', 'in_progress', 'in_review', 'done'], 'task_type' => ['task', 'project', 'ongoing', 'meeting'], 'priority' => ['low', 'medium', 'high', 'urgent'], 'recurring_interval_unit' => ['week', 'month'], 'recurring_mode' => ['fixed', 'relative'], default => null
                };
                if ($enum) {
                    $type = ['type' => 'string', 'enum' => $enum];
                }
                $properties[$field] = $type;
            }
            $schema = ['type' => 'object', 'additionalProperties' => false, 'minProperties' => 1, 'properties' => $properties];
            $schemas[$resource.'Patch'] = $schema;
            $schemas[$resource.'Create'] = $schema + ['required' => [$resource === 'tasks' ? 'title' : 'name']];
            foreach (['post' => 'Create', 'patch' => 'Patch'] as $method => $suffix) {
                $path = '/'.$resource.($method === 'patch' ? '/{id}' : '');
                $paths[$path][$method] = ['operationId' => strtolower($suffix).ucfirst($resource), 'tags' => [self::RESOURCES[$resource]], 'summary' => ($method === 'post' ? 'Crea ' : 'Modifica ').self::RESOURCES[$resource], 'description' => "Scope $resource:read e $resource:write. Regole complete: /developers/writes. Le task standard richiedono project_id; sottoattività ereditano il progetto dal genitore.", 'parameters' => $method === 'post' ? [$key] : [$id, ['name' => 'If-Match', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/'.$resource.$suffix]]]], 'responses' => ['200' => $response] + ($method === 'post' ? ['201' => $response] : []) + $errors];
            }
        }
        $paths['/tasks/{id}/comments']['post'] = ['operationId' => 'createComment', 'tags' => ['Commenti'], 'summary' => 'Aggiunge un commento', 'description' => 'Scope comments:write, comments:read, tasks:read. Autore: proprietario del collegamento.', 'parameters' => [$id, $key], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['body'], 'properties' => ['body' => ['type' => 'string', 'maxLength' => 20000, 'minLength' => 1]]]]]], 'responses' => ['201' => $paths['/comments/{id}']['get']['responses']['200'], '200' => $paths['/comments/{id}']['get']['responses']['200']] + $errors];
    }
}
