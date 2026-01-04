<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Story;

class StoryGenerationController extends Controller
{
    /**
     * Generate a story AND images using Google Flash Image 2.5
     */
//     public function generate(Request $request)
//     {

//         // PREVENT TIMEOUTS (Images take time!)
//         set_time_limit(300);

//         $validated = $request->validate([
//             'transcript' => 'required|string',
//             'options' => 'nullable|array',
//         ]);

//         // Use the hardcoded key or .env
//         $apiKey = env('TOGETHER_API_KEY', "tgp_v1_QpJ-9lZgMShCFIgU2RSISouNlKccrL_s3yvoWUpcvZc");

//         if (!$apiKey) {
//             return response()->json(['error' => 'TOGETHER_API_KEY missing'], 500);
//         }

//         $options = $validated['options'] ?? [];
//         // Bump max tokens to 4000+ because images consume tokens!
//         $maxTokens   = $options['maxTokens']   ?? 4096; 
//         $temperature = $options['temperature'] ?? 0.7;

//         // 1. UPDATE THE PROMPT for Multimodal
//         // We wrap the user's transcript to ensure the model knows to format pages and generate images.
//         $userPrompt = "Write a 5-page children's story based on this idea: \n\n" . $validated['transcript'] . 
//                       "\n\nFORMATTING RULES:" .
//                       "\n1. Generate ONE cover image at the very beginning." . 
//                       "\n2. Do NOT generate images for the other pages." .
//                       "\n3. Separate pages with '---PAGE BREAK---'.";

//         $response = Http::withHeaders([
//             'Authorization' => 'Bearer ' . $apiKey,
//             'Content-Type'  => 'application/json',
//             'Accept'        => 'application/json',
//         ])
//         ->timeout(60) // <--- CRITICAL: Allow up to 60 seconds for the API to reply
//         ->post('https://api.together.xyz/v1/chat/completions', [
            
//             // 2. SWITCH TO GOOGLE FLASH IMAGE MODEL
//             // Note: If this specific ID fails, try 'google/gemini-2.5-flash-image'
//             'model' => 'google/flash-image-2.5', 
            
//             'messages' => [
//                 [
//                     'role' => 'system',
//                     // 3. INSTRUCT SYSTEM TO GENERATE IMAGES
//                     'content' => 'You are a professional children’s book author and illustrator. You write engaging stories and always generate a visual illustration for every page.',
//                 ],
//                 [
//                     'role' => 'user',
//                     'content' => $userPrompt,
//                 ]
//             ],
//             'max_tokens' => $maxTokens,
//             'temperature' => $temperature,
//             // 'response_format' => ['type' => 'json_object'] // Optional: Use if you want strict JSON later
//         ]);

//         \Log::info("TOGETHER RAW", [
//             'status' => $response->status(),
//             'body' => $response->body() // Uncomment to see full image URLs in logs
//         ]);

//         if (!$response->successful()) {
//             \Log::error('Together AI error', [
//                 'status' => $response->status(),
//                 'body'   => $response->json(),
//             ]);
//             return response()->json([
//                 'error' => 'AI generation failed',
//                 'details' => $response->json(),
//             ], $response->status());
//         }

//         // The content will now contain text mixed with markdown images like:
//         // "Page 1... \n ![](https://generated-image-url.com/...) \n ---PAGE BREAK---"
//         $generatedText = $response->json()['choices'][0]['message']['content'] ?? null;
//         //console.log('generatedText: ' + generatedText);

//         if (!$generatedText) {
//             return response()->json(['error' => 'Invalid AI response'], 500);
//         }

//         // --- DB SAVE ---
//         try {
//             $title = strtok($generatedText, "\n");
//             $title = str_replace(['Title:', '"', '#', '*'], '', $title);

//             $storyEntry = Story::create([
//                 'user_id' => auth()->id() ?? 1,
//                 'name'    => trim($title) ?: 'New Illustrated Story',
//                 'slug'    => Str::slug(trim($title) ?: 'story') . '-' . Str::random(4),
//                 'body'    => $generatedText, // Saves both text and image URLs
//                 'prompt'  => $validated['transcript'],
//             ]);

//             \Log::info("STORY SAVED", ['id' => $storyEntry->id]);

//         } catch (\Throwable $e) {
//             \Log::error("DB ERROR: " . $e->getMessage());
//         }

//         return response()->json([
//             'data' => [
//                 'story' => $generatedText,
//             ]
//         ]);
//     }

public function generate(Request $request)
    {
        set_time_limit(120); // Allow script to run for 2 minutes

        $validated = $request->validate([
            'transcript' => 'required|string',
            'options' => 'nullable|array',
        ]);

        $apiKey = env('TOGETHER_API_KEY', "tgp_v1_QpJ-9lZgMShCFIgU2RSISouNlKccrL_s3yvoWUpcvZc");
        if (!$apiKey) {
            return response()->json(['error' => 'TOGETHER_API_KEY missing'], 500);
        }

        $options = $validated['options'] ?? [];
        $maxTokens   = $options['maxTokens']   ?? 2000;
        $temperature = $options['temperature'] ?? 0.7;

        // ---------------------------------------------------------
        // STEP 1: GENERATE TEXT (Using Llama 3 - Reliable & Fast)
        // ---------------------------------------------------------
        $textResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->post('https://api.together.xyz/v1/chat/completions', [
            'model' => 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are a children's book author. Write a 5-page story. Separate pages with '---PAGE BREAK---'. Do NOT generate images, just text.",
                ],
                [
                    'role' => 'user',
                    'content' => $validated['transcript'],
                ]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]);

        if (!$textResponse->successful()) {
            \Log::error('Text Generation Failed', ['body' => $textResponse->json()]);
            return response()->json(['error' => 'Story text generation failed'], 503);
        }

        $storyText = $textResponse->json()['choices'][0]['message']['content'] ?? '';

        // ---------------------------------------------------------
        // STEP 2: GENERATE COVER IMAGE (Using Flux.1 - Best quality)
        // ---------------------------------------------------------
        $imageUrl = null;
        
        // Create a simple image prompt based on the user's input
        $imagePrompt = "Children's book illustration, cover art, cute style: " . substr($validated['transcript'], 0, 200);

        try {
            $imageResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.together.xyz/v1/images/generations', [
                'model' => 'black-forest-labs/FLUX.1-schnell',
                'prompt' => $imagePrompt,
                'width' => 1024,
                'height' => 768,
                'steps' => 4, // Low steps = Fast (Schnell model is designed for this)
                'n' => 1,
            ]);

            if ($imageResponse->successful()) {
                $imageUrl = $imageResponse->json()['data'][0]['url'] ?? null;
            } else {
                \Log::error('Image Generation Failed', ['body' => $imageResponse->json()]);
            }

        } catch (\Exception $e) {
            \Log::error('Image Generation Exception: ' . $e->getMessage());
            // We don't stop the story if the image fails, we just continue without it.
        }

        // ---------------------------------------------------------
        // STEP 3: MERGE & SAVE
        // ---------------------------------------------------------
        
        // Inject the image at the top of the story text if we got one
        if ($imageUrl) {
            $storyText = "![]( $imageUrl )\n\n" . $storyText;
        }

        // --- DATABASE SAVE ---
        try {
            $title = strtok($storyText, "\n");
            $title = str_replace(['Title:', '"', '#', '*', '![]', '(', ')'], '', $title);

            $storyEntry = Story::create([
                'user_id' => auth()->id() ?? 1,
                'name'    => trim($title) ?: 'New Story',
                'slug'    => Str::slug(trim($title) ?: 'story') . '-' . Str::random(4),
                'body'    => $storyText,
                'prompt'  => $validated['transcript'],
            ]);

        } catch (\Throwable $e) {
            \Log::error("DB SAVE ERROR: " . $e->getMessage());
        }

        return response()->json([
            'data' => [
                'story' => $storyText,
            ]
        ]);
    }

}