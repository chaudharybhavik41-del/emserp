<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PwaShareTargetController extends Controller
{
    protected const SESSION_KEY = 'pwa.share_target';

    public function receive(Request $request): RedirectResponse
    {
        $payload = $this->normalizePayload($request);

        if ($payload === []) {
            return redirect()
                ->route('dashboard')
                ->with('info', 'No share content was received.');
        }

        $request->session()->put(self::SESSION_KEY, $payload);

        if (!$request->user()) {
            $request->session()->put('url.intended', route('pwa.share-target.compose'));

            return redirect()
                ->route('login')
                ->with('info', 'Sign in to continue with the shared item.');
        }

        return redirect()->route('pwa.share-target.compose');
    }

    public function compose(Request $request): View|RedirectResponse
    {
        $payload = $request->session()->get(self::SESSION_KEY);

        if (!is_array($payload) || $payload === []) {
            return redirect()
                ->route('dashboard')
                ->with('info', 'No shared item is waiting to be imported.');
        }

        return view('pwa.share_target', [
            'payload' => $payload,
            'taskPrefill' => $this->taskPrefill($payload),
            'crmPrefill' => $this->crmPrefill($payload),
        ]);
    }

    public function toTask(Request $request): RedirectResponse
    {
        $payload = $request->session()->get(self::SESSION_KEY, []);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('tasks.create', $this->taskPrefill(is_array($payload) ? $payload : []));
    }

    public function toCrmLead(Request $request): RedirectResponse
    {
        $payload = $request->session()->get(self::SESSION_KEY, []);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('crm.leads.create', $this->crmPrefill(is_array($payload) ? $payload : []));
    }

    protected function normalizePayload(Request $request): array
    {
        $title = trim((string) $request->query('title', ''));
        $text = trim((string) $request->query('text', ''));
        $url = trim((string) $request->query('url', ''));

        $payload = array_filter([
            'title' => $title !== '' ? $title : null,
            'text' => $text !== '' ? $text : null,
            'url' => $url !== '' ? $url : null,
            'shared_at' => now()->toDateTimeString(),
        ], static fn ($value) => $value !== null && $value !== '');

        return $payload;
    }

    protected function taskPrefill(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $text = trim((string) ($payload['text'] ?? ''));
        $url = trim((string) ($payload['url'] ?? ''));

        if ($title === '') {
            $title = $text !== '' ? Str::limit(Str::replaceMatches('/\s+/', ' ', $text), 120, '') : ($url !== '' ? Str::limit($url, 120, '') : 'Shared item');
        }

        $lines = array_values(array_filter([
            $title !== '' ? "Shared item: {$title}" : null,
            $text !== '' ? $text : null,
            $url !== '' ? "Source URL: {$url}" : null,
        ]));

        return array_filter([
            'title' => Str::limit($title, 255, ''),
            'description' => implode("\n\n", $lines),
        ], static fn ($value) => filled($value));
    }

    protected function crmPrefill(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $text = trim((string) ($payload['text'] ?? ''));
        $url = trim((string) ($payload['url'] ?? ''));

        if ($title === '') {
            $title = $url !== '' ? $url : Str::limit(Str::replaceMatches('/\s+/', ' ', $text), 180, '');
        }

        $notes = implode("\n\n", array_values(array_filter([
            $text !== '' ? $text : null,
            $url !== '' ? "Source URL: {$url}" : null,
        ])));

        return array_filter([
            'title' => Str::limit($title !== '' ? $title : 'Shared lead', 255, ''),
            'notes' => $notes,
        ], static fn ($value) => filled($value));
    }
}
