<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Party;
use App\Models\Project;
use App\Models\Tasks\Task;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:project.project.view')->only(['index', 'show']);
        $this->middleware('permission:project.project.create')->only(['create', 'store']);
        $this->middleware('permission:project.project.update')->only(['edit', 'update']);
        $this->middleware('permission:project.project.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Project::with(['client', 'tpi', 'quotation']);

        if ($code = trim($request->get('code', ''))) {
            $query->where('code', 'like', "%{$code}%");
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($clientId = $request->get('client_party_id')) {
            $query->where('client_party_id', $clientId);
        }

        $projects = $query
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        $clients    = Party::where('is_client', true)->orderBy('name')->get();
        $tpiParties = Party::where('is_contractor', true)->orderBy('name')->get();

        return view('projects.index', compact('projects', 'clients', 'tpiParties'));
    }

    public function create()
{
    $clients = Party::where('is_client', true)->orderBy('name')->get();
    $contractors = Party::where('is_contractor', true)->orderBy('name')->get();

    return view('projects.create', compact('clients', 'contractors'));
}


    public function store(StoreProjectRequest $request, ProjectService $projectService)
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = $projectService->generateProjectCode();
        }

        $data['created_by'] = $request->user()->id;

        $project = Project::create($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        $project->load(['client', 'tpi', 'lead', 'quotation', 'creator'])
            ->loadCount('boms');

        $taskStats = [
            'total' => 0,
            'open' => 0,
            'completed' => 0,
            'overdue' => 0,
        ];
        $recentTasks = collect();

        if (auth()->user()?->can('tasks.view') && Schema::hasTable('tasks')) {
            $taskQuery = Task::query()
                ->with(['status', 'priority', 'assignee'])
                ->where('project_id', $project->id)
                ->notArchived();

            $taskStats = [
                'total' => (clone $taskQuery)->count(),
                'open' => (clone $taskQuery)->open()->count(),
                'completed' => (clone $taskQuery)->closed()->count(),
                'overdue' => (clone $taskQuery)->overdue()->count(),
            ];

            $recentTasks = (clone $taskQuery)
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get();
        }

        return view('projects.show', compact('project', 'taskStats', 'recentTasks'));
    }

    public function edit(Project $project)
{
    $clients = Party::where('is_client', true)->orderBy('name')->get();
    $contractors = Party::where('is_contractor', true)->orderBy('name')->get();

    return view('projects.edit', compact('project', 'clients', 'contractors'));
}
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = $project->code;
        }

        $project->update($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        // Guardrail: later we can block delete if there are BOMs, DPR, etc.
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }
}
