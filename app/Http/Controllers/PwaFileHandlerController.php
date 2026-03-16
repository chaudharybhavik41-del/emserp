<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PwaFileHandlerController extends Controller
{
    protected const SESSION_KEY = 'pwa.share_target';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(): View
    {
        return view('pwa.file_handler');
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:50000'],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
        ]);

        $request->session()->put(self::SESSION_KEY, array_filter([
            'title' => $validated['title'],
            'text' => trim(implode("\n\n", array_filter([
                'Imported from file: ' . $validated['file_name'],
                $validated['text'] ?? null,
            ]))),
            'shared_at' => now()->toDateTimeString(),
            'meta' => [
                'file_name' => $validated['file_name'],
                'mime_type' => $validated['mime_type'] ?? null,
                'source' => 'file_handler',
            ],
        ], static fn ($value) => $value !== null && $value !== ''));

        return response()->json([
            'ok' => true,
            'redirect' => route('pwa.share-target.compose', [], false),
        ]);
    }
}
