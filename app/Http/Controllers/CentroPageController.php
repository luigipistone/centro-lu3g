<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CentroPageController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'clients' => DB::table('clients')->count(),
                'activeProjects' => DB::table('projects')->where('status', 'active')->count(),
                'openTasks' => DB::table('tasks')->where('status', '!=', 'done')->count(),
                'urgentTasks' => DB::table('tasks')->where('priority', 'urgent')->where('status', '!=', 'done')->count(),
            ],
            'recentClients' => DB::table('clients')->latest()->limit(6)->get(['id', 'name', 'email', 'phone', 'created_at']),
            'upcomingTasks' => DB::table('tasks')->whereNotNull('due_date')->orderBy('due_date')->limit(8)->get(['id', 'title', 'status', 'priority', 'due_date']),
        ]);
    }

    public function index(string $section): Response
    {
        $config = match ($section) {
            'clients' => [
                'title' => 'Clienti',
                'description' => 'Anagrafica clienti, contatti, servizi collegati e dati di fatturazione.',
                'table' => 'clients',
                'columns' => ['name', 'legal_name', 'email', 'phone', 'city'],
            ],
            'projects' => [
                'title' => 'Progetti',
                'description' => 'Progetti collegati ai clienti con stato, colore e attivita.',
                'table' => 'projects',
                'columns' => ['name', 'status', 'color', 'created_at'],
            ],
            'tasks' => [
                'title' => 'Task',
                'description' => 'Attivita, assegnazioni, ricorrenze, priorita e calendario.',
                'table' => 'tasks',
                'columns' => ['title', 'status', 'priority', 'due_date', 'task_type'],
            ],
            'billing' => [
                'title' => 'Billing',
                'description' => 'Preventivi, proforma, fatture, pagamenti, abbonamenti e numerazioni.',
                'table' => 'documents',
                'columns' => ['number', 'doc_type', 'status', 'issue_date', 'total_amount'],
            ],
            'users' => [
                'title' => 'Utenti',
                'description' => 'Profili e ruoli applicativi equivalenti a superadmin, admin, editor e guest.',
                'table' => 'users',
                'columns' => ['name', 'email', 'created_at'],
            ],
            'settings' => [
                'title' => 'Impostazioni',
                'description' => 'Servizi, design, numerazione documenti, dati aziendali, email e backup.',
                'table' => 'services',
                'columns' => ['name', 'active', 'color', 'created_at'],
            ],
            default => abort(404),
        };

        return Inertia::render('Centro/Index', [
            ...$config,
            'rows' => DB::table($config['table'])->latest()->limit(50)->get(),
        ]);
    }
}
