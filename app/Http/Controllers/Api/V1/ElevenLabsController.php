<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;


class ElevenLabsController extends Controller
{
    /**
     * Create a signed session for ElevenLabs Conversational AI.
     *
     * Returns a server-generated session token that can be used to track usage.
     * The actual API calls should go through the server-side proxy endpoints.
     *
     * @deprecated Use the server-side proxy endpoints (tts, voices) instead of client SDK.
     */
    public function sdkCredentials(Request $request)
    {
        $request->validate([
            'agentId' => 'required|string',
        ]);

        // Log deprecation warning
        Log::warning('Deprecated endpoint /conversation/sdk-credentials called', [
            'user_id' => $request->user()?->id,
            'agent_id' => $request->agentId,
            'ip' => $request->ip(),
        ]);

        $apiKey = config('services.elevenlabs.api_key');
        if (!$apiKey) {
            return response()->json([
                'error' => 'ELEVENLABS_API_KEY is not configured'
            ], 500);
        }

        // Generate a signed session token instead of exposing the API key
        $sessionId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(15);

        // Store session server-side for validation
        Cache::put("elevenlabs_session:{$sessionId}", [
            'agent_id' => $request->agentId,
            'user_id' => $request->user()?->id,
            'expires_at' => $expiresAt->toISOString(),
        ], $expiresAt);

        // Return session info WITHOUT the API key
        // Clients should use server-side proxy endpoints
        return response()->json([
            'sessionId' => $sessionId,
            'agentId' => $request->agentId,
            'expiresAt' => $expiresAt->toISOString(),
            'message' => 'Use server-side endpoints /conversation/tts and /conversation/voices. Direct SDK access is deprecated.',
        ]);
    }

    /**
     * Proxy endpoint for ElevenLabs Conversational AI.
     * Use this instead of client-side SDK to keep API keys secure.
     */
    public function conversationProxy(Request $request)
    {
        $request->validate([
            'sessionId' => 'required|string|uuid',
            'action' => 'required|string|in:start,message,end',
            'agentId' => 'required|string',
            'message' => 'nullable|string|max:5000',
        ]);

        // Validate session
        $session = Cache::get("elevenlabs_session:{$request->sessionId}");
        if (!$session || $session['agent_id'] !== $request->agentId) {
            return response()->json(['error' => 'Invalid or expired session'], 401);
        }

        $apiKey = config('services.elevenlabs.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY is not configured'], 500);
        }

        // Handle different conversation actions
        switch ($request->action) {
            case 'start':
                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->post("https://api.elevenlabs.io/v1/convai/conversation", [
                    'agent_id' => $request->agentId,
                ]);
                break;

            case 'message':
                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->post("https://api.elevenlabs.io/v1/convai/conversation/{$request->sessionId}/message", [
                    'message' => $request->message,
                ]);
                break;

            case 'end':
                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                ])->delete("https://api.elevenlabs.io/v1/convai/conversation/{$request->sessionId}");
                Cache::forget("elevenlabs_session:{$request->sessionId}");
                break;

            default:
                return response()->json(['error' => 'Invalid action'], 400);
        }

        if (!$response->successful()) {
            return response()->json([
                'error' => 'ElevenLabs API request failed',
                'details' => $response->json()
            ], $response->status());
        }

        return response()->json($response->json());
    }


    /**
     * ElevenLabs Text-to-Speech (server-side version).
     * React Native can call this when the JS SDK is unavailable.
     */
    public function textToSpeech(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'voiceId' => 'required|string',
            'options' => 'nullable|array',
        ]);

        $apiKey = config('services.elevenlabs.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY missing'], 500);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept'     => 'audio/mpeg',
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$request->voiceId}", [
            'text' => $request->text,
            'model_id' => $request->options['model_id'] ?? 'eleven_multilingual_v2',
            'voice_settings' => $request->options['voice_settings'] ?? [],
        ]);

        if (!$response->successful()) {
            return response()->json([
                'error' => 'TTS request failed',
                'details' => $response->json()
            ], $response->status());
        }

        return response($response->body(), 200)
            ->header('Content-Type', 'audio/mpeg');
    }


    /**
     * Fetches voices from ElevenLabs (server-side)
     */
    public function voices()
    {
        $apiKey = config('services.elevenlabs.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY missing'], 500);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->get('https://api.elevenlabs.io/v1/voices');

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch voices',
                'details' => $response->json()
            ], $response->status());
        }

        return response()->json($response->json());
    }
}
