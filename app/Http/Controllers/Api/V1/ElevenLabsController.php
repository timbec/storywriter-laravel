<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;


class ElevenLabsController extends Controller
{
    /**
 * Start a conversation with ElevenLabs Conversational AI agent.
 * Returns the signed_url needed to establish WebSocket connection.
 */
public function sdkCredentials(Request $request)
{

     \Log::info('🎯 SDK Credentials endpoint HIT!', [
        'url' => $request->fullUrl(),
        'method' => $request->method(),
        'all_data' => $request->all(),
        'headers' => $request->headers->all()
    ]);

    // Temporary workaround - remove when ElevenLabs is fixed
if (app()->environment('local')) {
    \Log::warning('Using mock ElevenLabs response - REMOVE IN PRODUCTION');
    return response()->json([
        'signed_url' => 'wss://mock-websocket-url.elevenlabs.io',
        'conversation_id' => 'mock_conversation_' . time(),
        'agent_id' => $request->agentId,
        'expiresAt' => now()->addMinutes(15)->toISOString(),
    ]);
}

    $request->validate([
        'agentId' => 'required|string',
    ]);

    $apiKey = env('ELEVENLABS_API_KEY');
    if (!$apiKey) {
        \Log::error('ELEVENLABS_API_KEY missing in .env');
        return response()->json([
            'error' => 'ELEVENLABS_API_KEY is missing in .env'
        ], 500);
    }

    // Call ElevenLabs to start a conversation
    try {
        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.elevenlabs.io/v1/convai/conversation', [
            'agent_id' => $request->agentId,
        ]);

        // Log the response for debugging
        \Log::info('ElevenLabs Conversation API Response', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        if (!$response->successful()) {
            \Log::error('ElevenLabs API Error', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            return response()->json([
                'error' => 'Failed to start ElevenLabs conversation',
                'details' => $response->json()
            ], $response->status());
        }

        $data = $response->json();

        // Check if signed_url exists
        if (!isset($data['signed_url'])) {
            \Log::error('Missing signed_url in ElevenLabs response', ['data' => $data]);
            return response()->json([
                'error' => 'Missing signed_url in credentials response',
                'received_data' => $data
            ], 500);
        }

        // Return the conversation credentials to React Native
        return response()->json([
            'signed_url' => $data['signed_url'],
            'conversation_id' => $data['conversation_id'] ?? null,
            'agent_id' => $request->agentId,
            'expiresAt' => now()->addMinutes(15)->toISOString(),
        ]);

    } catch (\Exception $e) {
        \Log::error('ElevenLabs SDK Credentials Exception', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Exception calling ElevenLabs API',
            'message' => $e->getMessage()
        ], 500);
    }
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

        $apiKey = env('ELEVENLABS_API_KEY');
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
        $apiKey = env('ELEVENLABS_API_KEY');
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
