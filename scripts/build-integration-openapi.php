<?php

// Generates the downloadable contract; no application boot or database access.
$ref = fn ($name) => ['$ref' => '#/components/schemas/'.$name];
$string = ['type' => 'string'];
$uuid = ['type' => 'string', 'format' => 'uuid'];
$schemas = [
    'Error' => ['type' => 'object', 'properties' => ['error' => ['type' => 'object', 'properties' => ['code' => $string, 'message' => $string, 'fields' => ['type' => 'object']]]]],
    'Page' => ['type' => 'object', 'properties' => ['page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer'], 'total' => ['type' => 'integer']]],
    'Client' => ['type' => 'object', 'properties' => ['id' => $uuid, 'name' => $string]],
    'Project' => ['type' => 'object', 'properties' => ['id' => $uuid, 'name' => $string, 'client_id' => $uuid, 'status' => $string]],
    'Task' => ['type' => 'object', 'properties' => ['id' => $uuid, 'task_id' => [...$uuid, 'nullable' => true], 'title' => $string, 'description' => [...$string, 'description' => 'HTML: sanitizzare prima di renderizzare nel plugin.'], 'status' => ['type' => 'string', 'enum' => ['open', 'done']], 'version' => ['type' => 'integer'], 'external_ref' => $string, 'external_url' => [...$string, 'nullable' => true], 'meta' => ['type' => 'object', 'additionalProperties' => true], 'client_id' => $uuid, 'project_id' => $uuid, 'url' => $string, 'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true]]],
    'CreateTask' => ['type' => 'object', 'required' => ['title', 'description', 'external_ref'], 'properties' => [
        'title' => [...$string, 'maxLength' => 255], 'description' => [...$string, 'maxLength' => 20000, 'description' => 'Testo semplice.'],
        'external_ref' => [...$string, 'maxLength' => 190, 'description' => 'ID stabile della task o nota sul sito. Stesso ID e payload restituiscono la risorsa esistente; payload diverso: 409.'],
        'external_url' => [...$string, 'format' => 'uri', 'maxLength' => 2000, 'nullable' => true], 'author_name' => [...$string, 'maxLength' => 255, 'nullable' => true], 'author_email' => [...$string, 'format' => 'email', 'nullable' => true],
        'meta' => ['type' => 'object', 'additionalProperties' => true, 'maxProperties' => 30, 'description' => 'Massimo 16000 byte JSON. Dati dichiarati dal sito, non verificati.'],
        'client_id' => [...$uuid, 'description' => 'Se presente deve coincidere con il collegamento.'], 'project_id' => [...$uuid, 'description' => 'Se presente deve coincidere con il collegamento.'],
        'status' => ['type' => 'string', 'enum' => ['open', 'done'], 'default' => 'open'], 'due_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
    ]],
    'UpdateSubtask' => ['type' => 'object', 'properties' => ['title' => [...$string, 'maxLength' => 255], 'description' => [...$string, 'maxLength' => 20000], 'status' => ['type' => 'string', 'enum' => ['open', 'done']], 'version' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Raccomandata: 409 se obsoleta. Completamento rifiutato con dipendenze o discendenti aperti.']]],
    'Comment' => ['type' => 'object', 'required' => ['body', 'external_ref'], 'properties' => ['body' => [...$string, 'maxLength' => 20000], 'external_ref' => [...$string, 'maxLength' => 190], 'author_name' => [...$string, 'maxLength' => 255, 'nullable' => true]]],
    'Id' => ['type' => 'object', 'properties' => ['id' => $uuid]],
    'Webhook' => ['type' => 'object', 'required' => ['task_id', 'url', 'events'], 'properties' => ['task_id' => $uuid, 'url' => [...$string, 'format' => 'uri', 'description' => 'HTTPS, stesso hostname del sito, porta 443; solo IP pubblici, niente redirect.'], 'events' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'enum' => ['subtask.updated', 'comment.created']]]]],
    'WebhookCreated' => ['type' => 'object', 'properties' => ['id' => $uuid, 'secret' => [...$string, 'description' => 'Segreto HMAC mostrato solo alla creazione.']]],
    'Me' => ['type' => 'object', 'properties' => ['connection_id' => $uuid, 'token_name' => $string, 'scopes' => ['type' => 'array', 'items' => $string], 'client_id' => $uuid, 'project_id' => $uuid, 'expires_at' => ['type' => 'string', 'format' => 'date-time']]],
    'Revoked' => ['type' => 'object', 'properties' => ['revoked' => ['type' => 'boolean']]],
    'Event' => ['type' => 'object', 'required' => ['id', 'event', 'occurred_at', 'origin', 'data'], 'properties' => ['id' => $uuid, 'event' => ['type' => 'string', 'enum' => ['subtask.updated', 'comment.created']], 'occurred_at' => ['type' => 'string', 'format' => 'date-time'], 'origin' => ['type' => 'string', 'enum' => ['centro']], 'data' => ['type' => 'object', 'properties' => ['subtask_id' => $uuid, 'external_ref' => $string, 'version' => ['type' => 'integer'], 'status' => ['type' => 'string', 'enum' => ['open', 'done']], 'title' => $string, 'description' => $string, 'comment_id' => $uuid, 'body' => $string, 'by' => [...$string, 'nullable' => true]]]]],
];
$paths = [];
$response = fn ($schema, $description = 'Operazione riuscita') => ['description' => $description, 'content' => ['application/json' => ['schema' => $schema]]];
$add = function ($path, $method, $summary, $scope, $result, $body = null, $list = false) use (&$paths, $ref, $response, $uuid) {
    $op = ['summary' => $summary, 'operationId' => $method.preg_replace('/[^a-zA-Z]/', '', ucwords($path, '/{}')), 'tags' => ['Page Notes'], 'description' => $scope ? 'Permesso richiesto: '.$scope.'. Solo risorse del collegamento.' : 'Token valido obbligatorio.', 'responses' => []];
    if (str_contains($path, '{id}')) {
        $op['parameters'][] = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => $uuid];
    }
    if ($list) {
        foreach (['page' => 1, 'per_page' => 50] as $name => $default) {
            $op['parameters'][] = ['name' => $name, 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, ...($name === 'per_page' ? ['maximum' => 100] : []), 'default' => $default]];
        }
        if ($path === '/clients') {
            $op['parameters'][] = ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 200]];
        }
        if ($path === '/tasks/{id}/subtasks') {
            $op['parameters'][] = ['name' => 'updated_since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']];
        }
    }
    $schema = $list ? ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $ref($result)], 'meta' => $ref('Page')]] : $ref($result);
    if ($method === 'delete') {
        $op['responses']['204'] = ['description' => 'Webhook eliminato'];
    } else {
        $status = $method === 'post' && $body ? '201' : '200';
        $op['responses'][$status] = $response($schema);
        if ($status === '201' && $path !== '/webhooks') {
            $op['responses']['200'] = $response($schema, 'Ripetizione idempotente: risorsa esistente');
        }
    }
    foreach ([401 => 'Token non valido', 403 => 'Permessi insufficienti', 404 => 'Risorsa non disponibile', 409 => 'Conflitto', 422 => 'Validazione', 429 => 'Limite richieste', 500 => 'Errore server'] as $code => $text) {
        $op['responses'][(string) $code] = $response($ref('Error'), $text);
    }
    $op['responses']['429']['headers']['Retry-After'] = ['schema' => ['type' => 'integer'], 'description' => 'Secondi prima del nuovo tentativo'];
    if ($body) {
        $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $ref($body)]]];
    }
    $paths[$path][$method] = $op;
};
$add('/me', 'get', 'Verifica collegamento', null, 'Me');
$add('/connect/revoke', 'post', 'Revoca il proprio collegamento', null, 'Revoked');
$add('/clients', 'get', 'Cliente autorizzato', 'clients:read', 'Client', null, true);
$add('/projects', 'get', 'Progetto autorizzato', 'projects:read', 'Project', null, true);
$add('/tasks', 'get', 'Task create dal collegamento', 'tasks:read', 'Task', null, true);
$add('/tasks', 'post', 'Crea task del sito', 'tasks:write', 'Task', 'CreateTask');
$add('/tasks/{id}', 'get', 'Leggi task', 'tasks:read', 'Task');
$add('/tasks/{id}/subtasks', 'get', 'Leggi note collegate', 'tasks:read', 'Task', null, true);
$add('/tasks/{id}/subtasks', 'post', 'Crea nota', 'tasks:write', 'Task', 'CreateTask');
$add('/subtasks/{id}', 'patch', 'Modifica nota', 'tasks:write', 'Task', 'UpdateSubtask');
$add('/subtasks/{id}/comments', 'post', 'Aggiungi commento', 'comments:write', 'Id', 'Comment');
$add('/webhooks', 'post', 'Registra webhook per task', 'webhooks:write', 'WebhookCreated', 'Webhook');
$add('/webhooks/{id}', 'delete', 'Elimina webhook', 'webhooks:write', 'Id');
$document = ['openapi' => '3.0.3', 'info' => ['title' => 'Il Centro - Integration API', 'version' => '1.0.0', 'description' => 'Prima integrazione: LU3G Page Notes. Token per sito, cliente e progetto. 60 richieste/minuto per collegamento e 120 per IP. Nessun OAuth in questa versione. Webhook rilevati ogni minuto: HMAC-SHA256(secret, timestamp + "." + corpo JSON originale), header X-Centro-Signature=sha256=<hex>, X-Centro-Timestamp e X-Centro-Event-ID. Verificare timestamp entro 5 minuti, firma in tempo costante e deduplicare per ID. Fino a 5 tentativi, ritardi 1/5/30/120 minuti; consegna almeno una volta, ordine non garantito.'], 'servers' => [['url' => 'https://centro.lu3g.com/api/v1']], 'security' => [['bearerAuth' => []]], 'paths' => $paths, 'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']], 'schemas' => $schemas]];
file_put_contents(__DIR__.'/../docs/integration-openapi.json', json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
