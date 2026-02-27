<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MyApprovalsController extends Controller
{
    /**
     * Default: approvals where the user can currently act (pending/in_progress).
     * If status is filtered to approved/rejected, show historical approvals assigned to the user/role.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $status = $request->input('status');

        // If user explicitly wants history, broaden the scope.
        $baseQuery = ($status === 'approved' || $status === 'rejected')
            ? ApprovalRequest::forApprover($user)
            : ApprovalRequest::pendingForApprover($user);

        $query = $baseQuery
            ->with(['requester', 'steps'])
            ->latest();

        if ($request->filled('module')) {
            $query->where('module', 'like', '%' . $request->module . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $approvals = $query->paginate(15);

        return view('approvals.my_index', compact('approvals'));
    }

    public function show(ApprovalRequest $approvalRequest)
    {
        $user = Auth::user();

        $isApprover = $approvalRequest->steps()->where(function ($q) use ($user) {
            $q->where('approver_user_id', $user->id)
              ->orWhereIn('approver_role_id', $user->roles->pluck('id'));
        })->exists();

        abort_unless($isApprover, 403);

        $approvalRequest->load(['steps.approverUser', 'steps.approverRole', 'requester', 'approvable']);

        return view('approvals.show', ['approval' => $approvalRequest]);
    }
}