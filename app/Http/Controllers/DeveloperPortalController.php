<?php

namespace App\Http\Controllers;

use App\Services\CommonApiWebhooks;
use Illuminate\Support\Str;

class DeveloperPortalController extends Controller
{
    public const RESOURCES = ['clients' => 'Clienti', 'projects' => 'Progetti', 'tasks' => 'Task', 'users' => 'Utenti', 'absences' => 'Assenze', 'services' => 'Servizi'];

    public const GUIDES = ['introduction' => 'Introduzione', 'authentication' => 'Autenticazione', 'permissions' => 'Permessi e ambiti', 'pagination' => 'Paginazione e filtri', 'errors' => 'Errori e limiti', 'availability' => 'Disponibilità e smart working', 'webhooks' => 'Webhook'];

    public function show(string $page = 'introduction')
    {
        abort_unless(isset(self::GUIDES[$page]) || isset(self::RESOURCES[$page]), 404);
        $resource = isset(self::RESOURCES[$page]);
        $content = $resource ? $this->resourceGuide($page) : file_get_contents(base_path('docs/developers/'.$page.'.md'));

        return view('developers', ['page' => $page, 'title' => self::GUIDES[$page] ?? self::RESOURCES[$page], 'guides' => self::GUIDES,
            'resources' => self::RESOURCES, 'resource' => $resource,
            'html' => Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'example' => implode(' '.chr(92).PHP_EOL.'  ', ['curl --fail-with-body', '--header "Authorization: Bearer $CENTRO_TOKEN"', '"https://centro.lu3g.com/api/v1/'.($resource ? $page.'?per_page=50&page=1' : 'me').'"'])]);
    }

    private function resourceGuide(string $resource): string
    {
        $notes = [
            'clients' => 'Include clienti attivi e archiviati. Usa `active=1` oppure `active=0` per separarli. Note interne e dati bancari non vengono esposti.',
            'projects' => 'Include i progetti autorizzati in tutti gli stati. Filtra con `client_id` o `status` (`active`, `completed`, `on_hold`, `archived`). Gli ID collegati non autorizzano a leggere automaticamente le relative schede.',
            'tasks' => 'Include task, continuative, meeting e sottoattività dei progetti autorizzati, non solo quelli creati tramite API. `parent_task_id` distingue le sottoattività. Stati: `todo`, `in_progress`, `in_review`, `done`. Filtri: `project_id`, `client_id`, `status`. Per le attività prive di progetto serve l’ambito tutti i progetti.',
            'users' => 'Solo account attivi autorizzati. Email di lavoro e nome sono inclusi; password, profilo privato, documenti e dati anagrafici personali sono esclusi.',
            'absences' => 'Solo richieste approvate delle persone autorizzate. `date=2026-11-09` restituisce quelle che comprendono il giorno. Non include smart working o trasferte. Motivazioni, tipologia medica, note e allegati non vengono esposti. Gli orari null indicano una richiesta senza intervallo orario specificato, non una certificazione di presenza.',
            'services' => 'Catalogo dei servizi del Centro. Lo scope autorizza il catalogo completo, senza esporre le associazioni private dei clienti. Filtra con `active=1` o `active=0`.',
        ];
        $text = "## Elenco\n\n`GET /api/v1/$resource`\n\n".$notes[$resource]."\n\nScope richiesto: `$resource:read`.\n\n## Parametri\n\n| Parametro | Descrizione |\n| --- | --- |\n| page | Pagina da 1; predefinita 1 |\n| per_page | Da 1 a 100; predefinito 50 |\n| updated_since | Data e ora ISO 8601 incluse nel confronto |\n";
        if ($resource !== 'absences') {
            $text .= "| search | Ricerca per nome o titolo su tutto l’elenco autorizzato |\n";
        }
        $text .= "\n## Risposta\n\nGli elenchi restituiscono `data` e `meta` con `page`, `per_page`, `total`, `last_page`, `has_more`. L’ordine è stabile per ID.\n\n## Dettaglio\n\n`GET /api/v1/$resource/{id}`\n\nRestituisce `{\"data\": {...}}`. Un ID inesistente o fuori ambito restituisce 404.\n\n## Campi restituiti\n\n";
        $text .= "| Campo | Tipo |\n| --- | --- |\n";
        foreach (CommonApiController::FIELDS[$resource] as $field) {
            $text .= '| `'.$field.'` | '.($field === 'active' ? 'boolean' : 'string')." |\n";
        }

        return $text."\nI campi non valorizzati possono essere null. Le date e ore archiviate sono nel fuso configurato del Centro (Europe/Rome); i campi data non rappresentano istanti UTC.\n\n## Disponibilità\n\nQuesta versione comune espone la lettura e i webhook. Le operazioni di scrittura generali non sono ancora pubblicate. Non usare le operazioni Page Notes come alternativa.\n";
    }

    public function openapi()
    {
        $paths = [];
        $schemas = [];
        foreach (self::RESOURCES as $resource => $label) {
            $properties = [];
            foreach (CommonApiController::FIELDS[$resource] as $field) {
                $properties[$field] = ['type' => $field === 'active' ? 'boolean' : 'string', 'nullable' => $field !== 'id'];
            }
            $schemas[$resource] = ['type' => 'object', 'properties' => $properties, 'required' => ['id']];
            $responses = ['401' => ['description' => 'Token assente, scaduto o revocato'], '403' => ['description' => 'Scope mancante o account proprietario disabilitato'], '404' => ['description' => 'Risorsa inesistente o fuori ambito'], '422' => ['description' => 'Parametri non validi'], '429' => ['description' => 'Limite richieste superato']];
            $params = [
                ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50]],
                ['name' => 'updated_since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
            ];
            if ($resource !== 'absences') {
                $params[] = ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 200]];
            }
            foreach (array_intersect(CommonApiController::FIELDS[$resource], ['active', 'client_id', 'project_id', 'user_id', 'status']) as $field) {
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

        return response()->json(['openapi' => '3.0.3', 'info' => ['title' => 'Il Centro API comuni', 'version' => '1.0.0', 'description' => 'Lettura comune e sottoscrizioni webhook. Non include le operazioni specifiche Page Notes.'],
            'servers' => [['url' => 'https://centro.lu3g.com/api/v1']], 'security' => [['bearerAuth' => []]], 'paths' => $paths,
            'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']], 'schemas' => $schemas]])->header('Content-Disposition', 'attachment; filename="centro-common-api-v1.json"');
    }
}
