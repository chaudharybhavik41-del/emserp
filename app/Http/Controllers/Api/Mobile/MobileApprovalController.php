<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class MobileApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = (string) $request->input('status', '');
        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));

        $baseQuery = in_array($status, ['approved', 'rejected'], true)
            ? ApprovalRequest::forApprover($user)
            : ApprovalRequest::pendingForApprover($user);

        $query = $baseQuery
            ->with(['requester', 'steps.approverUser', 'steps.approverRole', 'approvable'])
            ->latest();

        if ($request->filled('module')) {
            $query->where('module', 'like', '%' . $request->string('module') . '%');
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $approvals = $query->paginate($perPage);

        return response()->json([
            'data' => $approvals->getCollection()
                ->map(fn (ApprovalRequest $approval) => $this->serializeApproval($approval, $user, false))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ],
        ]);
    }

    public function show(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->isApprover($approvalRequest, $user), 403);

        $approvalRequest->load(['steps.approverUser', 'steps.approverRole', 'steps.actor', 'requester', 'approvable']);

        return response()->json([
            'data' => $this->serializeApproval($approvalRequest, $user, true),
        ]);
    }

    public function approve(Request $request, ApprovalStep $approvalStep, NotificationService $notificationService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canActOnStep($approvalStep, $user), 403);

        if ($approvalStep->status !== 'pending') {
            return response()->json([
                'message' => 'This step is not pending.',
            ], 422);
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $remarks = $validated['remarks'] ?? null;
        $approvalRequestId = $approvalStep->approval_request_id;

        DB::transaction(function () use ($approvalStep, $user, $remarks): void {
            $approvalStep->markApproved($user->id, $remarks);
            $this->refreshRequestAfterStepAction($approvalStep->request, $user->id);
        });

        $approvalRequest = $this->loadApprovalForResponse($approvalRequestId);

        if ($approvalRequest) {
            $this->logApprovalAction('approved', $approvalStep->fresh(), $approvalRequest, $remarks);
            $this->notifyAfterApproval($notificationService, $approvalStep->fresh(), $approvalRequest, $remarks);
        }

        return response()->json([
            'message' => 'Step approved successfully.',
            'data' => $approvalRequest ? $this->serializeApproval($approvalRequest, $user, true) : null,
        ]);
    }

    public function reject(Request $request, ApprovalStep $approvalStep, NotificationService $notificationService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canActOnStep($approvalStep, $user), 403);

        if ($approvalStep->status !== 'pending') {
            return response()->json([
                'message' => 'This step is not pending.',
            ], 422);
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $remarks = (string) $validated['remarks'];
        $approvalRequestId = $approvalStep->approval_request_id;

        DB::transaction(function () use ($approvalStep, $user, $remarks): void {
            $approvalStep->markRejected($user->id, $remarks);

            $approvalRequest = $approvalStep->request;
            $approvalRequest->markRejected($user->id, $remarks);

            $this->handleDocumentOnRejection($approvalRequest);
        });

        $approvalRequest = $this->loadApprovalForResponse($approvalRequestId);

        if ($approvalRequest) {
            $this->logApprovalAction('rejected', $approvalStep->fresh(), $approvalRequest, $remarks);
            $this->notifyAfterRejection($notificationService, $approvalStep->fresh(), $approvalRequest, $remarks);
        }

        return response()->json([
            'message' => 'Step rejected successfully.',
            'data' => $approvalRequest ? $this->serializeApproval($approvalRequest, $user, true) : null,
        ]);
    }

    private function loadApprovalForResponse(int $approvalRequestId): ?ApprovalRequest
    {
        return ApprovalRequest::with([
            'steps.approverUser',
            'steps.approverRole',
            'steps.actor',
            'requester',
            'approvable',
        ])->find($approvalRequestId);
    }

    private function serializeApproval(ApprovalRequest $approval, User $user, bool $includeSteps): array
    {
        $currentStep = $approval->currentStep();
        $approvable = $approval->approvable;

        $data = [
            'id' => $approval->id,
            'module' => $approval->module,
            'sub_module' => $approval->sub_module,
            'action' => $approval->action,
            'status' => $approval->status,
            'remarks' => $approval->remarks,
            'requested_at' => optional($approval->requested_at)->toIso8601String(),
            'closed_at' => optional($approval->closed_at)->toIso8601String(),
            'requester' => $approval->requester ? [
                'id' => $approval->requester->id,
                'name' => $approval->requester->name,
                'email' => $approval->requester->email,
            ] : null,
            'document' => [
                'type' => class_basename((string) $approval->approvable_type),
                'id' => $approval->approvable_id,
                'label' => $this->buildDocLabel($approval),
                'name' => $approvable?->name ?? $approvable?->title ?? $approvable?->code ?? null,
            ],
            'workflow' => [
                'current_step_number' => $approval->current_step,
                'current_step' => $currentStep ? [
                    'id' => $currentStep->id,
                    'step_number' => $currentStep->step_number,
                    'name' => $currentStep->name,
                    'status' => $currentStep->status,
                    'can_act' => $this->canActOnStep($currentStep, $user) && $currentStep->status === 'pending',
                ] : null,
            ],
        ];

        if ($includeSteps) {
            $data['steps'] = $approval->steps->map(function (ApprovalStep $step) use ($user): array {
                return [
                    'id' => $step->id,
                    'step_number' => $step->step_number,
                    'name' => $step->name,
                    'is_mandatory' => (bool) $step->is_mandatory,
                    'status' => $step->status,
                    'remarks' => $step->remarks,
                    'acted_at' => optional($step->acted_at)->toIso8601String(),
                    'can_act' => $this->canActOnStep($step, $user) && $step->status === 'pending',
                    'approver_user' => $step->approverUser ? [
                        'id' => $step->approverUser->id,
                        'name' => $step->approverUser->name,
                    ] : null,
                    'approver_role' => $step->approverRole ? [
                        'id' => $step->approverRole->id,
                        'name' => $step->approverRole->name,
                    ] : null,
                    'actor' => $step->actor ? [
                        'id' => $step->actor->id,
                        'name' => $step->actor->name,
                    ] : null,
                ];
            })->values()->all();
        }

        return $data;
    }

    private function isApprover(ApprovalRequest $approvalRequest, User $user): bool
    {
        return $approvalRequest->steps()->where(function ($query) use ($user): void {
            $query->where('approver_user_id', $user->id)
                ->orWhereIn('approver_role_id', $user->roles->pluck('id'));
        })->exists();
    }

    private function canActOnStep(ApprovalStep $step, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $userRoleIds = $user->roles->pluck('id')->all();

        return ($step->approver_user_id && $step->approver_user_id == $user->id)
            || ($step->approver_role_id && in_array($step->approver_role_id, $userRoleIds, true));
    }

    private function refreshRequestAfterStepAction(ApprovalRequest $approvalRequest, int $userId): void
    {
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

        $nextStep = $pendingMandatory->first();
        $approvalRequest->status = 'in_progress';
        $approvalRequest->current_step = $nextStep->step_number;
        $approvalRequest->save();
    }

    private function handleDocumentOnApproval(ApprovalRequest $approvalRequest): void
    {
        $doc = $approvalRequest->approvable;
        if (! $doc) {
            return;
        }

        if (method_exists($doc, 'onApprovalApproved')) {
            $doc->onApprovalApproved($approvalRequest);
            return;
        }

        $attributes = $doc->getAttributes();

        if (array_key_exists('status', $attributes)) {
            $doc->status = 'approved';
        }
        if (array_key_exists('approved_by', $attributes) && $approvalRequest->closed_by) {
            $doc->approved_by = $approvalRequest->closed_by;
        }
        if (array_key_exists('approved_at', $attributes)) {
            $doc->approved_at = now();
        }

        $doc->save();
    }

    private function handleDocumentOnRejection(ApprovalRequest $approvalRequest): void
    {
        $doc = $approvalRequest->approvable;
        if (! $doc) {
            return;
        }

        if (method_exists($doc, 'onApprovalRejected')) {
            $doc->onApprovalRejected($approvalRequest);
            return;
        }

        $attributes = $doc->getAttributes();

        if (array_key_exists('status', $attributes)) {
            $doc->status = 'rejected';
        }
        if (array_key_exists('rejected_by', $attributes) && $approvalRequest->closed_by) {
            $doc->rejected_by = $approvalRequest->closed_by;
        }
        if (array_key_exists('rejected_at', $attributes)) {
            $doc->rejected_at = now();
        }

        $doc->save();
    }

    private function buildDocLabel(ApprovalRequest $approvalRequest): string
    {
        $doc = $approvalRequest->approvable;

        $ref = null;
        if ($doc) {
            foreach (['doc_no', 'number', 'voucher_no', 'po_number', 'indent_no', 'code', 'name', 'title'] as $key) {
                if (isset($doc->{$key}) && $doc->{$key}) {
                    $ref = $doc->{$key};
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
            $user = User::find($step->approver_user_id);
            if ($user) {
                $users->push($user);
            }
        }

        if ($step->approver_role_id) {
            $role = Role::find($step->approver_role_id);
            if ($role && method_exists($role, 'users')) {
                $users = $users->merge($role->users);
            }
        }

        return $users->filter()->unique('id')->values();
    }

    private function approvalRequestUrl(ApprovalRequest $approvalRequest): string
    {
        try {
            return route('my-approvals.show', $approvalRequest);
        } catch (\Throwable) {
            return route('my-approvals.index');
        }
    }

    private function logApprovalAction(string $action, ApprovalStep $step, ApprovalRequest $request, ?string $remarks): void
    {
        try {
            $subject = $request->approvable ?? $request;

            $description = strtoupper($action) . " approval step {$step->step_number} for request #{$request->id}";
            if ($remarks) {
                $description .= " (Remarks: {$remarks})";
            }

            ActivityLog::logCustom(
                $action === 'approved' ? ActivityLog::ACTION_APPROVED : ActivityLog::ACTION_REJECTED,
                $description,
                $subject,
                [
                    'approval_request_id' => $request->id,
                    'approval_step_id' => $step->id,
                    'module' => $request->module,
                    'sub_module' => $request->sub_module,
                    'request_action' => $request->action,
                    'remarks' => $remarks,
                    'source' => 'mobile_companion_api',
                ]
            );
        } catch (\Throwable) {
            // Keep approval flow non-blocking if activity logging fails.
        }
    }

    private function notifyAfterApproval(NotificationService $notifications, ApprovalStep $step, ApprovalRequest $request, ?string $remarks): void
    {
        $url = $this->approvalRequestUrl($request);
        $docLabel = $this->buildDocLabel($request);

        if ($request->requester) {
            $notifications->sendSystemAlertToUser(
                $request->requester,
                'Approval Update',
                "Step {$step->step_number} approved for {$docLabel}.",
                ['approval_request_id' => $request->id, 'approval_step_id' => $step->id],
                $url,
                'info',
                'approval'
            );
        }

        if ($request->status === 'approved') {
            if ($request->requester) {
                $notifications->sendSystemAlertToUser(
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

        $nextStep = $request->steps
            ->where('is_mandatory', true)
            ->where('status', 'pending')
            ->sortBy('step_number')
            ->first();

        if (! $nextStep) {
            return;
        }

        $recipients = $this->resolveStepApprovers($nextStep);
        if ($recipients->isEmpty()) {
            return;
        }

        $notifications->sendSystemAlertToUsers(
            $recipients,
            'Approval Required',
            "Approval required for {$docLabel} (Step {$nextStep->step_number}).",
            ['approval_request_id' => $request->id, 'approval_step_id' => $nextStep->id],
            $url,
            'warning',
            'approval'
        );
    }

    private function notifyAfterRejection(NotificationService $notifications, ApprovalStep $step, ApprovalRequest $request, ?string $remarks): void
    {
        $url = $this->approvalRequestUrl($request);
        $docLabel = $this->buildDocLabel($request);

        if (! $request->requester) {
            return;
        }

        $message = "{$docLabel} was rejected at Step {$step->step_number}.";
        if ($remarks) {
            $message .= " Remarks: {$remarks}";
        }

        $notifications->sendSystemAlertToUser(
            $request->requester,
            'Approval Rejected',
            $message,
            ['approval_request_id' => $request->id, 'approval_step_id' => $step->id],
            $url,
            'danger',
            'approval'
        );
    }
}
