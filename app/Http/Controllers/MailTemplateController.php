<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMailTemplateRequest;
use App\Http\Requests\UpdateMailTemplateRequest;
use App\Models\MailProfile;
use App\Models\MailTemplate;

use Illuminate\Http\Request;
class MailTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.mail_template.view')->only(['index']);
        $this->middleware('permission:core.mail_template.create')->only(['create', 'store']);
        $this->middleware('permission:core.mail_template.update')->only(['edit', 'update']);
        $this->middleware('permission:core.mail_template.delete')->only(['destroy']);
    }
    public function index(Request $request)
    {
        $profiles = MailProfile::orderBy('code')->get();
        $types = MailTemplate::query()
            ->select('type')
            ->distinct()
            ->whereNotNull('type')
            ->orderBy('type')
            ->pluck('type');

        $query = MailTemplate::with('mailProfile');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('type', 'like', $like)
                    ->orWhere('subject', 'like', $like);
            });
        }

        $type = $request->get('type');
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        $profileId = $request->get('mail_profile_id');
        if ($profileId !== null && $profileId !== '') {
            if ((string) $profileId === '0') {
                $query->whereNull('mail_profile_id');
            } else {
                $query->where('mail_profile_id', (int) $profileId);
            }
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['code', 'name', 'type', 'subject', 'mail_profile_id', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('code');
        }

        $templates = $query->paginate(25)->withQueryString();

        return view('mail_templates.index', compact('templates', 'profiles', 'types'));
    }

    public function create()
    {
        $template = new MailTemplate();
        $profiles = MailProfile::orderBy('code')->get();

        return view('mail_templates.create', compact('template', 'profiles'));
    }

    public function store(StoreMailTemplateRequest $request)
    {
        MailTemplate::create($request->validated());

        return redirect()
            ->route('mail-templates.index')
            ->with('success', 'Mail template created successfully.');
    }

    public function edit(MailTemplate $mail_template)
    {
        $template = $mail_template;
        $profiles = MailProfile::orderBy('code')->get();

        return view('mail_templates.edit', compact('template', 'profiles'));
    }

    public function update(UpdateMailTemplateRequest $request, MailTemplate $mail_template)
    {
        $mail_template->update($request->validated());

        return redirect()
            ->route('mail-templates.index')
            ->with('success', 'Mail template updated successfully.');
    }

    public function destroy(MailTemplate $mail_template)
    {
        $mail_template->delete();

        return redirect()
            ->route('mail-templates.index')
            ->with('success', 'Mail template deleted successfully.');
    }
}


