<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ApprovalActionsController extends Controller
{
    public function approve(Request $request, ApprovalStep $approvalStep, NotificationService $notificationService): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($this->canActOnStep($approvalStep, $user), 403);

        if ($approvalStep->status !== 'pending') {
            return $this->backWithMessage($request, 'This step is not pending.', 'warning');
        }

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $remarks = $request->input('remarks');
        $approvalRequestId = $approvalStep->approval_request_id;

        DB::transaction(function () use ($approvalStep, $user, $remarks) {
            $approvalStep->markApproved($user->id, $remarks);
            $this->refreshRequestAfterStepAction($approvalStep->request, $user->id);
        });

        // Reload for notifications / logging
        $approvalRequest = ApprovalRequest::with([
            'steps.approverUser',
            'steps.approverRole',
            'requester',
            'approvable',
        ])->find($approvalRequestId);

        if ($approvalRequest) {
            $this->logApprovalAction('approved', $approvalStep, $approvalRequest, $remarks);
            $this->notifyAfterApproval($notificationService, $approvalStep, $approvalRequest, $remarks);
        }

        return $this->backWithMessage($request, 'Step approved successfully.', 'success');
    }

    public function reject(Request $request, ApprovalStep $approvalStep, NotificationService $notificationService): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($this->canActOnStep($approvalStep, $user), 403);

        if ($approvalStep->status !== 'pending') {
            return $this->backWithMessage($request, 'This step is not pending.', 'warning');
        }

        $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $remarks = $request->remarks;
        $approvalRequestId = $approvalStep->approval_request_id;

        DB::transaction(function () use ($approvalStep, $user, $remarks) {
            $approvalStep->markRejected($user->id, $remarks);

            $approvalRequest = $approvalStep->request;
            $approvalRequest->markRejected($user->id, $remarks);

            $this->handleDocumentOnRejection($approvalRequest);
        });

        $approvalRequest = ApprovalRequest::with([
            'steps.approverUser',
            'steps.approverRole',
            'requester',
            'approvable',
        ])->find($approvalRequestId);

        if ($approvalRequest) {
            $this->logApprovalAction('rejected', $approvalStep, $approvalRequest, $remarks);
            $this->notifyAfterRejection($notificationService, $approvalStep, $approvalRequest, $remarks);
        }

        return $this->backWithMessage($request, 'Step rejected successfully.', 'success');
    }

    private function canActOnStep(ApprovalStep $step, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $userRoleIds = $user->roles->pluck('id')->all();

        return ($step->approver_user_id && $step->approver_user_id == $user->id)
            || ($step->approver_role_id && in_array($step->approver_role_id, $userRoleIds));
    }

    private function refreshRequestAfterStepAction(ApprovalRequest $approvalRequest, int $userId): void
    {
        // If any mandatory steps are still pending, move to next
        $pendingMandatory = $approvalRequest->steps()
            ->where('is_mandatory', true)
            ->where('status', 'pending')
            ->orderBy('step_number')
            ->get();

        if ($pendingMandatory->isEmpty()) {
            $approvalRequest->markApproved($userId);
            $this->handleDocumentOnApproval($approvalRequest);
            return;
        }

        // Set request as in_progress and move current_step
        $nextStep = $pendingMandatory->first();
        $approvalRequest->status = 'in_progress';
        $approvalRequest->current_step = $nextStep->step_number;
        $approvalRequest->save();
    }

    private function handleDocumentOnApproval(ApprovalRequest $approvalRequest): void
    {
        $doc = $approvalRequest->approvable;
        if (!$doc) return;

        // Allow modules to override behavior
        if (method_exists($doc, 'onApprovalApproved')) {
            $doc->onApprovalApproved($approvalRequest);
            return;
        }

        // Safe fallback: only set fields that really exist on the loaded model attributes
        $attrs = $doc->getAttributes();

        if (array_key_exists('status', $attrs)) {
            $doc->status = 'approved';
        }
        if (array_key_exists('approved_by', $attrs) && $approvalRequest->closed_by) {
            $doc->approved_by = $approvalRequest->closed_by;
        }
        if (array_key_exists('approved_at', $attrs)) {
            $doc->approved_at = now();
        }

        $doc->save();
    }

    private function handleDocumentOnRejection(ApprovalRequest $approvalRequest): void
    {
        $doc = $approvalRequest->approvable;
        if (!$doc) return;

        if (method_exists($doc, 'onApprovalRejected')) {
            $doc->onApprovalRejected($approvalRequest);
            return;
        }

        $attrs = $doc->getAttributes();

        if (array_key_exists('status', $attrs)) {
            $doc->status = 'rejected';
        }
        if (array_key_exists('rejected_by', $attrs) && $approvalRequest->closed_by) {
            $doc->rejected_by = $approvalRequest->closed_by;
        }
        if (array_key_exists('rejected_at', $attrs)) {
            $doc->rejected_at = now();
        }

        $doc->save();
    }

    private function backWithMessage(Request $request, string $message, string $type): RedirectResponse
    {
        $redirectTo = $request->input('redirect_to');
        return $redirectTo
            ? redirect($redirectTo)->with($type, $message)
            : back()->with($type, $message);
    }

    private function approvalRequestUrl(ApprovalRequest $approvalRequest): string
    {
        try {
            return route('my-approvals.show', $approvalRequest);
        } catch (\Throwable $e) {
            return route('my-approvals.index');
        }
    }

    private function buildDocLabel(ApprovalRequest $approvalRequest): string
    {
        $doc = $approvalRequest->approvable;

        $ref = null;
        if ($doc) {
            foreach (['doc_no', 'number', 'voucher_no', 'po_number', 'indent_no', 'code', 'name', 'title'] as $k) {
                if (isset($doc->{$k}) && $doc->{$k}) {
                    $ref = $doc->{$k};
                    break;
                }
            }
        }

        $module = $approvalRequest->module ?: 'Document';
        return $ref ? "{$module} ({$ref})" : "{$module} (#{$approvalRequest->approvable_id})";
    }

    private function resolveStepApprovers(ApprovalStep $step): Collection
    {
        $users = collect();

        if ($step->approver_user_id) {
            $u = User::find($step->approver_user_id);
            if ($u) $users->push($u);
        }

        if ($step->approver_role_id) {
            $role = Role::find($step->approver_role_id);
            if ($role && method_exists($role, 'users')) {
                $users = $users->merge($role->users);
            }
        }

        return $users->filter()->unique('id')->values();
    }

    private function logApprovalAction(string $action, ApprovalStep $step, ApprovalRequest $request, ?string $remarks): void
    {
        try {
            $subject = $request->approvable ?? $request;

            $desc = strtoupper($action) . " approval step {$step->step_number} for request #{$request->id}";
            if ($remarks) {
                $desc .= " (Remarks: {$remarks})";
            }

            ActivityLog::logCustom(
                $action === 'approved' ? ActivityLog::ACTION_APPROVED : ActivityLog::ACTION_REJECTED,
                $desc,
                $subject,
                [
                    'approval_request_id' => $request->id,
                    'approval_step_id' => $step->id,
                    'module' => $request->module,
                    'sub_module' => $request->sub_module,
                    'request_action' => $request->action,
                    'remarks' => $remarks,
                ]
            );
        } catch (\Throwable $e) {
            // swallow
        }
    }

    private function notifyAfterApproval(NotificationService $ns, ApprovalStep $step, ApprovalRequest $request, ?string $remarks): void
    {
        $url = $this->approvalRequestUrl($request);
        $docLabel = $this->buildDocLabel($request);

        // Notify requester (step approved)
        if ($request->requester) {
            $ns->sendSystemAlertToUser(
                $request->requester,
                'Approval Update',
                "Step {$step->step_number} approved for {$docLabel}.",
                ['approval_request_id' => $request->id, 'approval_step_id' => $step->id],
                $url,
                'info',
                'approval'
            );
        }

        // If request is now fully approved → requester gets final success
        if ($request->status === 'approved') {
            if ($request->requester) {
                $ns->sendSystemAlertToUser(
                    $request->requester,
                    'Approval Completed',
                    "{$docLabel} has been fully approved.",
                    ['approval_request_id' => $request->id],
                    $url,
                    'success',
                    'approval'
                );
            }
            return;
        }

        // Notify next pending mandatory step approvers
        $nextStep = $request->steps
            ->where('is_mandatory', true)
            ->where('status', 'pending')
            ->sortBy('step_number')
            ->first();

        if ($nextStep) {
            $recipients = $this->resolveStepApprovers($nextStep);

            if ($recipients->isNotEmpty()) {
                $ns->sendSystemAlertToUsers(
                    $recipients,
                    'Approval Required',
                    "Approval required for {$docLabel} (Step {$nextStep->step_number}).",
                    ['approval_request_id' => $request->id, 'approval_step_id' => $nextStep->id],
                    $url,
                    'warning',
                    'approval'
                );
            }
        }
    }

    private function notifyAfterRejection(NotificationService $ns, ApprovalStep $step, ApprovalRequest $request, ?string $remarks): void
    {
        $url = $this->approvalRequestUrl($request);
        $docLabel = $this->buildDocLabel($request);

        if ($request->requester) {
            $msg = "{$docLabel} was rejected at Step {$step->step_number}.";
            if ($remarks) $msg .= " Remarks: {$remarks}";

            $ns->sendSystemAlertToUser(
                $request->requester,
                'Approval Rejected',
                $msg,
                ['approval_request_id' => $request->id, 'approval_step_id' => $step->id],
                $url,
                'danger',
                'approval'
            );
        }
    }
}