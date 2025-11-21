<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;


/**
 * StoryGenerationTest
 *
 * This test suite verifies the complete behavior of the `/api/stories/generate`
 * endpoint, which accepts a transcript from the React Native app and uses the
 * TogetherAI API to generate a children's story.
 *
 * WHAT THIS TEST COVERS:
 * --------------------------------------------------------------
 * 1. Validation:
 *    - Ensures the `transcript` field is required.
 *
 * 2. Successful Story Generation:
 *    - Uses Http::fake() so the test never hits the real TogetherAI service.
 *    - Confirms Laravel forwards the request correctly.
 *    - Confirms the controller returns the expected JSON structure.
 *    - Confirms the generated story is passed back through the endpoint.
 *    - Includes a debug dump() to show the exact API response during development.
 *
 * 3. Error Handling:
 *    - Simulates TogetherAI returning a failure (400).
 *    - Ensures Laravel handles the failure gracefully.
 *    - Ensures the endpoint returns the correct error JSON format.
 *
 * WHY THIS TEST IS IMPORTANT:
 * --------------------------------------------------------------
 * - Verifies the endpoint behavior without calling external services.
 * - Protects against regressions while iterating on story generation logic.
 * - Ensures stable integration between React Native -> Laravel -> TogetherAI.
 * - Makes development safer even when experimenting, refactoring, or “vibe coding.”
 *
 * HOW TO RUN:
 * --------------------------------------------------------------
 *   php artisan test --filter=StoryGenerationTest
 *
 * NOTES:
 * --------------------------------------------------------------
 * - TogetherAI calls are fully mocked; these tests do not use the real model.
 * - Update the TogetherAI fake domain if API URL changes in the future.
 * - This suite intentionally tests the controller end-to-end (minus real API).
 */
class StoryGenerationTest extends TestCase
{
    /** @test */
    public function it_requires_transcript_field()
    {
        $response = $this->postJson('/api/stories/generate', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['transcript']);
    }

    /** @test */
    public function it_shows_me_exactly_what_the_generate_endpoint_returns()
    {
        Http::fake([
            // TogetherAI serverless endpoint
            'api.together.*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Debug story from test.'
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'transcript' => 'The child wants a story about a dragon.',
            'options' => [
                'maxTokens'   => 500,
                'temperature' => 0.7,
            ],
        ];

        $response = $this->postJson('/api/stories/generate', $payload);

        // Your original debug dump (kept)
        $response->dump();

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'story'
                 ])
                 ->assertJson([
                     'story' => 'Debug story from test.'
                 ]);
    }

    /** @test */
    public function it_handles_together_ai_failure_gracefully()
    {
        Http::fake([
            'api.together.*' => Http::response([
                'error' => [
                    'message' => 'Something went wrong'
                ]
            ], 400),
        ]);

        $payload = [
            'transcript' => 'Test transcript',
            'options' => []
        ];

        $response = $this->postJson('/api/stories/generate', $payload);

        $response->assertStatus(400)
                 ->assertJson([
                     'error' => 'AI generation failed',
                 ])
                 ->assertJsonStructure([
                     'details'
                 ]);
    }
}