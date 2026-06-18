<?php

return [
    'accepted' => 'Il campo :attribute deve essere accettato.',
    'after' => 'Il campo :attribute deve essere successivo a :date.',
    'after_or_equal' => 'Il campo :attribute deve essere uguale o successivo a :date.',
    'array' => 'Il campo :attribute deve essere un elenco.',
    'before' => 'Il campo :attribute deve essere precedente a :date.',
    'before_or_equal' => 'Il campo :attribute deve essere uguale o precedente a :date.',
    'boolean' => 'Il campo :attribute deve essere vero o falso.',
    'confirmed' => 'La conferma del campo :attribute non corrisponde.',
    'date' => 'Il campo :attribute deve essere una data valida.',
    'date_format' => 'Il campo :attribute deve rispettare il formato :format.',
    'email' => 'Il campo :attribute deve essere un indirizzo email valido.',
    'exists' => 'Il valore selezionato per :attribute non e valido.',
    'image' => 'Il campo :attribute deve essere un’immagine.',
    'in' => 'Il valore selezionato per :attribute non e valido.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'max' => [
        'file' => 'Il file :attribute non puo superare :max kilobyte.',
        'numeric' => 'Il campo :attribute non puo essere maggiore di :max.',
        'string' => 'Il campo :attribute non puo superare :max caratteri.',
        'array' => 'Il campo :attribute non puo contenere piu di :max elementi.',
    ],
    'mimes' => 'Il campo :attribute deve essere un file di tipo: :values.',
    'min' => [
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'string' => 'Il campo :attribute deve contenere almeno :min caratteri.',
        'array' => 'Il campo :attribute deve contenere almeno :min elementi.',
    ],
    'nullable' => 'Il campo :attribute puo essere vuoto.',
    'numeric' => 'Il campo :attribute deve essere un numero.',
    'regex' => 'Il formato del campo :attribute non e valido.',
    'required' => 'Il campo :attribute e obbligatorio.',
    'required_if' => 'Il campo :attribute e obbligatorio.',
    'string' => 'Il campo :attribute deve essere testo.',
    'unique' => 'Il valore del campo :attribute e gia stato usato.',
    'uploaded' => 'Il caricamento del campo :attribute non e riuscito.',
    'url' => 'Il campo :attribute deve essere un URL valido.',
    'uuid' => 'Il campo :attribute deve essere un identificativo valido.',

    'custom' => [
        'inps_code' => [
            'required_if' => 'Il Codice INPS e obbligatorio per le richieste di malattia.',
        ],
        'start_time' => [
            'regex' => 'L’ora di inizio deve essere selezionata a ore intere.',
        ],
        'end_time' => [
            'regex' => 'L’ora di fine deve essere selezionata a ore intere.',
        ],
    ],

    'attributes' => [
        'type' => 'tipo richiesta',
        'start_date' => 'data inizio',
        'end_date' => 'data fine',
        'start_time' => 'ora inizio',
        'end_time' => 'ora fine',
        'notes' => 'note',
        'inps_code' => 'Codice INPS',
        'email' => 'email',
        'password' => 'password',
        'name' => 'nome',
    ],
];
